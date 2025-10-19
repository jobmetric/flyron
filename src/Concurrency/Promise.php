<?php

namespace JobMetric\Flyron\Concurrency;

use Closure;
use Fiber;
use InvalidArgumentException;
use JobMetric\Flyron\Enums\PromiseState;
use RuntimeException;
use Throwable;

/**
 * Class Promise
 *
 * Represents an asynchronous operation that will complete in the future,
 * wrapping a Fiber for concurrency control.
 *
 * Supports chaining with then, catching errors with catch,
 * and finalizing with finally.
 *
 * @template T
 * @package JobMetric\Flyron
 */
class Promise
{
    /**
     * @var Fiber Fiber running the asynchronous operation.
     */
    protected Fiber $fiber;

    /**
     * @var T|null Result value after fulfillment.
     */
    protected mixed $result = null;

    /**
     * @var Throwable|null Exception if the promise was rejected.
     */
    protected ?Throwable $exception = null;

    /**
     * @var PromiseState Current state of the promise.
     */
    protected PromiseState $state = PromiseState::PENDING;

    /**
     * @var Closure|null Callback to run on rejection.
     */
    protected ?Closure $catch = null;

    /**
     * @var Closure|null Callback to run when settled (fulfilled or rejected).
     */
    protected ?Closure $finally = null;

    /**
     * Track whether catch and finally have been invoked to avoid double-calls.
     *
     * This prevents executing user-provided handlers multiple times when
     * methods such as catch()/finally() are attached after settlement or
     * when run() is called repeatedly.
     */
    protected bool $catchInvoked = false;

    protected bool $finallyInvoked = false;

    /**
     * @var int|null Timeout in milliseconds.
     */
    protected ?int $timeoutMs = null;

    /**
     * @var float Timestamp when the promise was created.
     */
    protected float $startTime;

    /**
     * Promise constructor.
     *
     * @param Fiber $fiber Fiber wrapping the async operation.
     */
    public function __construct(Fiber $fiber)
    {
        $this->fiber = $fiber;
        $this->startTime = microtime(true);
    }

    /**
     * Run the promise and return its result.
     *
     * Behavior:
     * - If already settled (fulfilled/rejected/cancelled), immediately returns value or throws error.
     * - If the wrapped Fiber throws, the promise is rejected and the exception is thrown.
     * - If the Fiber returns a Throwable (e.g. Async suspended with an error), it is treated as rejection.
     * - Invokes catch()/finally() handlers exactly once where applicable.
     *
     * Timeout:
     * A pre-start and post-execution best-effort timeout check is performed.
     * Due to the cooperative nature of Fibers here, long-running synchronous
     * work cannot be preempted.
     *
     * Chaining:
     * Any further transformation should be done with then()/map()/recover(),
     * which return new Promise instances.
     *
     * @return mixed The fulfilled value.
     * @throws Throwable If the promise is rejected or cancelled.
     */
    public function run(): mixed
    {
        if ($this->state !== PromiseState::PENDING) {
            return $this->getResultOrThrow();
        }

        if ($this->timeoutMs !== null && (microtime(true) - $this->startTime) * 1000 > $this->timeoutMs) {
            $this->state = PromiseState::CANCELLED;
            $this->exception = new RuntimeException("Promise timed out after {$this->timeoutMs}ms");

            return $this->getResultOrThrow();
        }

        try {
            $value = $this->fiber->start();
        } catch (Throwable $e) {
            $this->exception = $e;
            $this->state = PromiseState::REJECTED;

            $this->invokeCatch($e);
            $this->invokeFinally();

            return $this->getResultOrThrow();
        }

        // If the fiber returned a Throwable via Async suspension, treat as rejection
        if ($value instanceof Throwable) {
            $this->exception = $value;
            $this->state = PromiseState::REJECTED;

            $this->invokeCatch($value);
            $this->invokeFinally();

            return $this->getResultOrThrow();
        }

        $this->result = $value;
        $this->state = PromiseState::FULFILLED;

        // Post-execution timeout check (best-effort; cannot preempt running fiber)
        if ($this->timeoutMs !== null && (microtime(true) - $this->startTime) * 1000 > $this->timeoutMs) {
            $this->state = PromiseState::CANCELLED;
            $this->exception = new RuntimeException("Promise timed out after {$this->timeoutMs}ms");

            $this->invokeFinally();

            return $this->getResultOrThrow();
        }

        $this->invokeFinally();

        return $this->getResultOrThrow();
    }

    /**
     * Attach a callback to transform the fulfilled value.
     *
     * The callback receives the resolved value and may return either a plain
     * value or another Promise. If a Promise is returned, it will be unwrapped.
     * When the current promise is already settled:
     * - fulfilled: the callback is executed immediately (fast-path)
     * - rejected/cancelled: the rejection is propagated as-is
     *
     * @template TResult
     * @param Closure(T): (TResult|self<TResult>) $callback
     *
     * @return self<TResult>
     */
    public function then(Closure $callback): self
    {
        // Fast path if already settled
        if ($this->state === PromiseState::FULFILLED) {
            try {
                $mapped = $callback($this->result);
                if ($mapped instanceof self) {
                    // Unwrap immediately if possible
                    try {
                        $val = $mapped->run();

                        return self::makeFulfilled($val);
                    } catch (Throwable $e) {
                        return self::makeRejected($e);
                    }
                }

                return self::makeFulfilled($mapped);
            } catch (Throwable $e) {
                return self::makeRejected($e);
            }
        }

        if ($this->state === PromiseState::REJECTED || $this->state === PromiseState::CANCELLED) {
            // Propagate rejection/cancellation unchanged
            return self::makeRejected($this->exception ?? new RuntimeException('Promise was cancelled.'));
        }

        // Lazy path: wrap in a new Promise
        $parent = $this;

        return new self(new Fiber(function () use ($parent, $callback) {
            try {
                $value = $parent->run();
                $mapped = $callback($value);

                if ($mapped instanceof self) {
                    $mapped = $mapped->run();
                }

                Fiber::suspend($mapped);
            } catch (Throwable $e) {
                Fiber::suspend($e);
            }
        }));
    }

    /**
     * Attach a rejection handler to observe or handle errors.
     *
     * If the promise is already rejected when you attach the handler, it will
     * be invoked immediately (once). Exceptions from the handler are swallowed.
     *
     * @param Closure(Throwable): void $callback
     *
     * @return self<T> Returns the same promise for fluent chaining.
     */
    public function catch(Closure $callback): self
    {
        $this->catch = $callback;

        if ($this->state === PromiseState::REJECTED && ! $this->catchInvoked && $this->exception) {
            $this->invokeCatch($this->exception);
        }

        return $this;
    }

    /**
     * Attach a finalizer to run once when the promise settles (success or error).
     *
     * If the promise is already settled when you attach the finalizer, it will
     * be invoked immediately (once). Exceptions from the handler are swallowed.
     *
     * @param Closure(): void $callback
     *
     * @return self<T> Returns the same promise for fluent chaining.
     */
    public function finally(Closure $callback): self
    {
        $this->finally = $callback;

        if ($this->state !== PromiseState::PENDING && ! $this->finallyInvoked) {
            $this->invokeFinally();
        }

        return $this;
    }

    /**
     * Set a best-effort timeout in milliseconds.
     *
     * If the promise does not settle before timeout, it will be marked
     * as cancelled and a timeout exception is thrown on run().
     *
     * @param int $ms Timeout duration in milliseconds.
     *
     * @return self<T>
     */
    public function timeout(int $ms): self
    {
        $this->timeoutMs = $ms;

        return $this;
    }

    /**
     * Cancel the promise if it is still pending (cooperative).
     *
     * This does not forcibly stop the running Fiber. It only updates the state
     * to CANCELLED and ensures finally() is invoked. Subsequent run() calls will throw.
     *
     * @return self<T>
     */
    public function cancel(): self
    {
        if ($this->state === PromiseState::PENDING) {
            $this->state = PromiseState::CANCELLED;
            $this->exception = new RuntimeException('Promise was cancelled.');
            $this->invokeFinally();
        }

        return $this;
    }

    /**
     * Get the result of the promise or throw if rejected or cancelled.
     *
     * @return mixed The resolved value.
     * @throws Throwable If promise was rejected or cancelled.
     */
    protected function getResultOrThrow(): mixed
    {
        if ($this->state === PromiseState::REJECTED) {
            throw $this->exception;
        }

        if ($this->state === PromiseState::CANCELLED) {
            throw new RuntimeException("Promise was cancelled.");
        }

        return $this->result;
    }

    /**
     * Get the resolved value (if any) without throwing.
     *
     * Useful for diagnostics/telemetry after settlement.
     *
     * @return T|null
     */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * Get the exception (if rejected or cancelled) without throwing.
     *
     * @return Throwable|null
     */
    public function getException(): ?Throwable
    {
        return $this->exception;
    }

    /**
     * Returns true if the promise is still pending (not settled).
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->state === PromiseState::PENDING;
    }

    /**
     * Returns true if the promise has been fulfilled.
     *
     * @return bool
     */
    public function isFulfilled(): bool
    {
        return $this->state === PromiseState::FULFILLED;
    }

    /**
     * Returns true if the promise has been rejected.
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->state === PromiseState::REJECTED;
    }

    /**
     * Returns true if the promise has been cancelled.
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->state === PromiseState::CANCELLED;
    }

    /**
     * Get the current state of the promise as a string.
     *
     * @return string One of the PromiseState enum values.
     */
    public function getState(): string
    {
        return $this->state->value;
    }

    /**
     * Map the fulfilled value to another value using a callback.
     *
     * Shorthand for then() where the callback is pure (no side-effects).
     *
     * @template TResult
     * @param Closure(T): TResult $callback
     *
     * @return self<TResult>
     */
    public function map(Closure $callback): self
    {
        return $this->then($callback);
    }

    /**
     * Run a side-effect on the fulfilled value and pass it through unchanged.
     *
     * @param Closure(T): void $callback
     *
     * @return self<T>
     */
    public function tap(Closure $callback): self
    {
        return $this->then(function ($value) use ($callback) {
            $callback($value);

            return $value;
        });
    }

    /**
     * Recover from a rejection by returning a fallback value or Promise.
     *
     * If fulfilled, the value passes through. If rejected/cancelled, the
     * recovery callback is executed. If it returns a Promise, it is unwrapped.
     *
     * @template TRecovered
     * @param Closure(Throwable): (TRecovered|self<TRecovered>) $callback
     *
     * @return self<T|TRecovered>
     */
    public function recover(Closure $callback): self
    {
        // Fast path if already settled
        if ($this->state === PromiseState::FULFILLED) {
            return self::makeFulfilled($this->result);
        }

        if ($this->state === PromiseState::REJECTED || $this->state === PromiseState::CANCELLED) {
            try {
                $recovered = $callback($this->exception ?? new RuntimeException('Promise was cancelled.'));
                if ($recovered instanceof self) {
                    try {
                        $val = $recovered->run();

                        return self::makeFulfilled($val);
                    } catch (Throwable $e) {
                        return self::makeRejected($e);
                    }
                }

                return self::makeFulfilled($recovered);
            } catch (Throwable $e) {
                return self::makeRejected($e);
            }
        }

        // Lazy path
        $parent = $this;

        return new self(new Fiber(function () use ($parent, $callback) {
            try {
                $value = $parent->run();
                Fiber::suspend($value);
            } catch (Throwable $e) {
                try {
                    $recovered = $callback($e);
                    if ($recovered instanceof self) {
                        $recovered = $recovered->run();
                    }
                    Fiber::suspend($recovered);
                } catch (Throwable $re) {
                    Fiber::suspend($re);
                }
            }
        }));
    }

    /**
     * Wait for all promises to resolve and return their results.
     *
     * @template TKey
     * @template TValue
     * @param self<TValue>[] $promises
     *
     * @return array<TKey, TValue> Array of results keyed by the promises keys.
     * @throws Throwable If any promise rejects or errors.
     * @throws InvalidArgumentException If any element is not a Promise instance.
     */
    public static function all(array $promises): array
    {
        self::validatePromises($promises);

        return array_map(function ($promise) {
            return $promise->run();
        }, $promises);
    }

    /**
     * Resolve to the first settled promise (deterministic sequential evaluation).
     *
     * Promises are evaluated in the order provided; the first one that settles
     * determines the outcome. In this cooperative model there is no true
     * concurrency between entries unless the underlying tasks yield.
     *
     * @template TAny
     * @param self<TAny>[] $promises
     *
     * @return TAny
     * @throws Throwable If the first settled promise rejects.
     */
    public static function race(array $promises): mixed
    {
        self::validatePromises($promises);

        foreach ($promises as $promise) {
            try {
                return $promise->run();
            } catch (Throwable $e) {
                // First settled being a rejection: propagate immediately
                throw $e;
            }
        }

        // Should not reach here with a non-empty array
        throw new InvalidArgumentException('No promises provided to race.');
    }

    /**
     * Create a resolved (already-fulfilled) promise for a given value.
     *
     * @template TValue
     * @param TValue $value
     *
     * @return self<TValue>
     */
    public static function resolve(mixed $value): self
    {
        return self::makeFulfilled($value);
    }

    /**
     * Create a rejected (already-rejected) promise for a given exception.
     *
     * @param Throwable $e
     *
     * @return self<never>
     */
    public static function reject(Throwable $e): self
    {
        return self::makeRejected($e);
    }

    /**
     * Validate that all elements in the given array are Promise instances.
     *
     * @param array $promises Array to validate.
     *
     * @return void
     * @throws InvalidArgumentException If any element is not a Promise.
     */
    public static function validatePromises(array $promises): void
    {
        foreach ($promises as $promise) {
            if (! $promise instanceof Promise) {
                throw new InvalidArgumentException("All elements must be Promise instances");
            }
        }
    }

    /**
     * Wait for all promises to settle and return their outcomes.
     *
     * Returns an array preserving keys with entries in the form:
     *  - ['status' => 'fulfilled', 'value' => mixed]
     *  - ['status' => 'rejected',  'reason' => Throwable]
     *
     * @param self[] $promises
     *
     * @return array<string|int, array{status: string, value?: mixed, reason?: Throwable}>
     */
    public static function allSettled(array $promises): array
    {
        self::validatePromises($promises);

        $results = [];
        foreach ($promises as $key => $promise) {
            try {
                $results[$key] = ['status' => PromiseState::FULFILLED->value, 'value' => $promise->run(),];
            } catch (Throwable $e) {
                $results[$key] = ['status' => PromiseState::REJECTED->value, 'reason' => $e,];
            }
        }

        return $results;
    }

    /**
     * Internal helper to build an already-fulfilled Promise without Fiber overhead.
     *
     * @param mixed $value
     *
     * @return self
     */
    protected static function makeFulfilled(mixed $value): self
    {
        // Create with a no-op fiber; run() returns immediately due to settled state
        $promise = new self(new Fiber(fn () => null));
        $promise->result = $value;
        $promise->state = PromiseState::FULFILLED;

        return $promise;
    }

    /**
     * Internal helper to build an already-rejected Promise without Fiber overhead.
     *
     * @param Throwable $e
     *
     * @return self
     */
    protected static function makeRejected(Throwable $e): self
    {
        $promise = new self(new Fiber(fn () => null));
        $promise->exception = $e;
        $promise->state = PromiseState::REJECTED;

        return $promise;
    }

    /**
     * Invoke the catch handler once if present.
     *
     * @param Throwable $e
     *
     * @return void
     */
    protected function invokeCatch(Throwable $e): void
    {
        if ($this->catch && ! $this->catchInvoked) {
            $this->catchInvoked = true;
            try {
                ($this->catch)($e);
            } catch (Throwable) {
                // Swallow exceptions from catch handlers
            }
        }
    }

    /**
     * Invoke the finally handler once if present.
     *
     * @return void
     */
    protected function invokeFinally(): void
    {
        if ($this->finally && ! $this->finallyInvoked) {
            $this->finallyInvoked = true;
            try {
                ($this->finally)();
            } catch (Throwable) {
                // Swallow exceptions from finally handlers
            }
        }
    }
}
