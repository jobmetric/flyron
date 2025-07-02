<?php

namespace JobMetric\Flyron\Traits;

use Closure;
use Throwable;

/**
 * Trait Thenable
 *
 * Provides a promise-like interface to chain callbacks for asynchronous or deferred logic.
 *
 * This trait allows chaining of:
 * - `then()` for success handling
 * - `catch()` for error handling
 * - `finally()` for finalizing logic regardless of outcome
 *
 * Intended to be used in async-related classes like Promise, Task, AsyncProcess, etc.
 *
 * @package JobMetric\Flyron
 */
trait Thenable
{
    /**
     * Callback to be executed on successful completion.
     *
     * @var Closure|null
     */
    protected ?Closure $then = null;

    /**
     * Callback to be executed when an exception occurs.
     *
     * @var Closure|null
     */
    protected ?Closure $catch = null;

    /**
     * Callback to be executed after completion (regardless of success or failure).
     *
     * @var Closure|null
     */
    protected ?Closure $finally = null;

    /**
     * Define a callback to execute on success.
     *
     * @param callable $callback A closure or callable function.
     * @return static
     */
    public function then(callable $callback): static
    {
        $this->then = $callback instanceof Closure ? $callback : $callback(...);

        return $this;
    }

    /**
     * Define a callback to execute on failure (exception thrown).
     *
     * @param callable $callback A closure or callable function.
     * @return static
     */
    public function catch(callable $callback): static
    {
        $this->catch = $callback instanceof Closure ? $callback : $callback(...);

        return $this;
    }

    /**
     * Define a callback to execute after task is finished (regardless of outcome).
     *
     * @param callable $callback A closure or callable function.
     *
     * @return static
     */
    public function finally(callable $callback): static
    {
        $this->finally = $callback instanceof Closure ? $callback : $callback(...);

        return $this;
    }

    /**
     * Call the success callback if defined.
     *
     * @param mixed $result
     *
     * @return void
     */
    protected function triggerThen(mixed $result): void
    {
        if ($this->then instanceof Closure) {
            ($this->then)($result);
        }
    }

    /**
     * Call the error callback if defined.
     *
     * @param Throwable $e
     *
     * @return void
     */
    protected function triggerCatch(Throwable $e): void
    {
        if ($this->catch instanceof Closure) {
            ($this->catch)($e);
        }
    }

    /**
     * Call the final callback if defined.
     *
     * @return void
     */
    protected function triggerFinally(): void
    {
        if ($this->finally instanceof Closure) {
            ($this->finally)();
        }
    }
}
