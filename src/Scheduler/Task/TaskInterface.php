<?php

namespace JobMetric\Flyron\Scheduler\Task;

/**
 * Interface TaskInterface
 *
 * Represents a unit of work that can be scheduled and executed by the Flyron Scheduler.
 *
 * Any class implementing this interface must provide a `run` method, which will be invoked
 * by the Scheduler when it's time to execute the task.
 *
 * Implementations can wrap anything from a simple closure, a Fiber-based async call,
 * to a background process execution.
 *
 * @package JobMetric\Flyron
 */
interface TaskInterface
{
    /**
     * Execute the task logic.
     *
     * This method is called by the Scheduler to perform the task's operation.
     *
     * @return mixed The result of the task execution.
     */
    public function run(): mixed;
}
