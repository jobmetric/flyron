<?php

namespace JobMetric\Flyron\Scheduler\Task;

use JobMetric\Flyron\Concurrency\Promise;
use Throwable;

/**
 * Class AsyncTask
 *
 *  Represents an asynchronous task based on a Flyron Promise.
 *
 *  This task is designed to be executed within the Flyron Scheduler.
 *  It wraps a Promise and ensures its execution when the scheduler calls `run()`.
 *  Useful for queuing and managing Fiber-based async operations.
 *
 * @package JobMetric\Flyron
 */
class AsyncTask implements TaskInterface
{
    /**
     * The promise instance representing the asynchronous operation.
     *
     * @var Promise
     */
    protected Promise $promise;

    /**
     * Create a new AsyncTask instance.
     *
     * @param Promise $promise The promise that encapsulates asynchronous logic.
     */
    public function __construct(Promise $promise)
    {
        $this->promise = $promise;
    }

    /**
     * Execute the asynchronous task.
     *
     * @return mixed The result returned by the Promise.
     *
     * @throws Throwable If the Promise execution fails.
     */
    public function run(): mixed
    {
        return $this->promise->run();
    }
}
