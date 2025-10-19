<?php

namespace JobMetric\Flyron\Concurrency;

use Throwable;

/**
 * Class Deferred
 *
 * A Deferred represents a controllable Promise that can be resolved or rejected manually.
 * It is useful for adapting callback-style or event-driven APIs to Promise-based flows.
 *
 * Usage:
 *  $d = Deferred::create();
 *  $promise = $d->promise();
 *  // later
 *  $d->resolve($value); // or $d->reject($e)
 */
class Deferred
{
    protected Promise $promise;

    /**
     * Create a new Deferred with a pending Promise.
     *
     * @return self
     */
    public static function create(): self
    {
        $p = new Promise(new \Fiber(fn () => null));
        $d = new self($p);

        return $d;
    }

    /**
     * Deferred constructor.
     *
     * @param Promise $promise Internal pending promise.
     */
    public function __construct(Promise $promise)
    {
        $this->promise = $promise;
    }

    /**
     * Get the underlying Promise.
     *
     * @return Promise
     */
    public function promise(): Promise
    {
        return $this->promise;
    }

    /**
     * Resolve the promise with a value.
     *
     * @param mixed $value
     *
     * @return void
     */
    public function resolve(mixed $value): void
    {
        $this->promise->resolveInternal($value);
    }

    /**
     * Reject the promise with an error.
     *
     * @param Throwable $e
     *
     * @return void
     */
    public function reject(Throwable $e): void
    {
        $this->promise->rejectInternal($e);
    }
}

