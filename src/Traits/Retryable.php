<?php

namespace JobMetric\Flyron\Traits;

/**
 * Trait Retryable
 *
 * Provides retry support for tasks that may fail and need to be attempted again.
 *
 * Useful in task schedulers, async processes, or promises where temporary failures
 * (like network errors, I/O exceptions, etc.) should be retried a fixed number of times.
 *
 * @package JobMetric\Flyron
 */
trait Retryable
{
    /**
     * Number of times the task has already been retried.
     *
     * @var int
     */
    protected int $retryCount = 0;

    /**
     * Maximum number of retries allowed.
     *
     * @var int
     */
    protected int $maxRetries = 0;

    /**
     * Set the maximum number of retry attempts.
     *
     * @param int $times Number of times to retry on failure.
     *
     * @return static
     */
    public function retry(int $times): static
    {
        $this->maxRetries = max(0, $times);

        return $this;
    }

    /**
     * Get the current retry attempt count.
     *
     * @return int
     */
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * Get the maximum number of allowed retry attempts.
     *
     * @return int
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Determine if the task can be retried again.
     *
     * @return bool
     */
    public function shouldRetry(): bool
    {
        return $this->retryCount < $this->maxRetries;
    }

    /**
     * Increase the retry attempt counter.
     *
     * This should be called each time a retry is attempted.
     *
     * @return void
     */
    public function incrementRetry(): void
    {
        $this->retryCount++;
    }
}
