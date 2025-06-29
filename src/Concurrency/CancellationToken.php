<?php

namespace JobMetric\Flyron\Concurrency;

/**
 * Class CancellationToken
 *
 * Represents a token that signals whether an operation has been cancelled.
 * Useful for cooperative cancellation in asynchronous or long-running processes.
 *
 * This token can be passed to asynchronous tasks or fibers to allow them to
 * check whether they should stop execution early.
 *
 * @package JobMetric\Flyron
 */
class CancellationToken
{
    /**
     * Indicates whether cancellation has been requested.
     *
     * @var bool
     */
    private bool $cancelled = false;

    /**
     * Request cancellation.
     *
     * Marks the token as cancelled, signaling any observers or tasks that
     * they should attempt to stop execution as soon as possible.
     *
     * @return void
     */
    public function cancel(): void
    {
        $this->cancelled = true;
    }

    /**
     * Check if cancellation has been requested.
     *
     * @return bool Returns true if cancellation has been requested; otherwise false.
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}
