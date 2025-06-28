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
     * @var Closure[] Queue of callbacks for then chaining.
     */
    protected array $thenQueue = [];

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
     * If the promise is already settled (fulfilled, rejected, or cancelled),
     * it returns the result or throws the exception.
     *
     * If a timeout is set and exceeded, cancels the promise.
     *
     * Runs all chained then callbacks in order.
     *
     * @return T The fulfilled value.
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

            if ($this->catch) {
                ($this->catch)($e);
            }

            if ($this->finally) {
                ($this->finally)();
            }

            return $this->getResultOrThrow();
        }

        foreach ($this->thenQueue as $then) {
            $value = $then($value);

            // Unwrap nested promise if returned
            if ($value instanceof self) {
                $value = $value->run();
            }
        }

        $this->result = $value;
        $this->state = PromiseState::FULFILLED;

        if ($this->finally) {
            try {
                ($this->finally)();
            } catch (Throwable $e) {
                // Exceptions in finally are swallowed
            }
        }

        return $this->getResultOrThrow();
    }

    /**
     * Attach a callback to be executed when the promise is fulfilled.
     *
     * The callback receives the resolved value and can return a new value or promise.
     *
     * @param Closure(T): mixed $callback
     *
     * @return static Returns self for chaining.
     */
    public function then(Closure $callback): self
    {
        $this->thenQueue[] = $callback;

        return $this;
    }

    /**
     * Attach a callback to be executed when the promise is rejected.
     *
     * The callback receives the Throwable and can handle the error.
     *
     * @param Closure(Throwable): void $callback
     *
     * @return static Returns self for chaining.
     */
    public function catch(Closure $callback): self
    {
        $this->catch = $callback;

        return $this;
    }

    /**
     * Attach a callback to be executed when the promise is settled
     * (fulfilled or rejected).
     *
     * The callback receives no arguments.
     *
     * @param Closure(): void $callback
     *
     * @return static Returns self for chaining.
     */
    public function finally(Closure $callback): self
    {
        $this->finally = $callback;

        return $this;
    }

    /**
     * Set a timeout in milliseconds for this promise.
     *
     * If the promise does not settle before timeout, it will be cancelled.
     *
     * @param int $ms Timeout duration in milliseconds.
     *
     * @return static Returns self for chaining.
     */
    public function timeout(int $ms): self
    {
        $this->timeoutMs = $ms;

        return $this;
    }

    /**
     * Get the result of the promise or throw if rejected or cancelled.
     *
     * @return T The resolved value.
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

        $results = [];
        foreach ($promises as $key => $promise) {
            $results[$key] = $promise->run();
        }
        return $results;
    }

    /**
     * Wait for the first promise to resolve (fulfill or reject) and return its result.
     *
     * @template T
     * @param self<T>[] $promises
     *
     * @return T
     * @throws Throwable If the resolved promise rejects or errors.
     * @throws InvalidArgumentException If any element is not a Promise instance.
     */
    public static function race(array $promises): mixed
    {
        self::validatePromises($promises);

        while (true) {
            foreach ($promises as $promise) {
                if (!$promise->isPending()) {
                    return $promise->getResultOrThrow();
                }
            }
            usleep(1000);
        }
    }

    /**
     * Create a resolved promise with a given value.
     *
     * @template T
     * @param T $value
     *
     * @return self<T>
     */
    public static function resolve(mixed $value): self
    {
        return new self(new Fiber(fn() => $value));
    }

    /**
     * Create a rejected promise with a given exception.
     *
     * @param Throwable $e
     *
     * @return self<never>
     */
    public static function reject(Throwable $e): self
    {
        return new self(new Fiber(fn() => throw $e));
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
            if (!$promise instanceof Promise) {
                throw new InvalidArgumentException("All elements must be Promise instances");
            }
        }
    }
}
