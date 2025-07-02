<?php

namespace JobMetric\Flyron\Scheduler\Task;

/**
 * Class AsyncProcessTask
 *
 * Represents a background process task using a callable.
 *
 * This task encapsulates a `callable` that is expected to dispatch
 * or trigger a background process (such as using AsyncProcess).
 *
 * It conforms to the Flyron `TaskInterface` for compatibility with the Scheduler.
 *
 * @package JobMetric\Flyron
 */
class AsyncProcessTask implements TaskInterface
{
    /**
     * A callable to trigger the async process logic.
     *
     * @var callable
     */
    protected $callable;

    /**
     * AsyncProcessTask constructor.
     *
     * @param callable $callable The callable that initiates the background process.
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * Run the async process task.
     *
     * Executes the stored callable which is expected to dispatch a process.
     * The return value depends on the callable behavior.
     *
     * @return mixed
     */
    public function run(): mixed
    {
        return ($this->callable)();
    }
}
