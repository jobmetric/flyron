<?php

namespace JobMetric\Flyron\Traits;

/**
 * Trait Cancelable
 *
 * Provides cancellation control for tasks or async flows.
 *
 * @package JobMetric\Flyron
 */
trait Cancelable
{
    /**
     * Indicates whether the task has been cancelled.
     *
     * @var bool
     */
    protected bool $cancelled = false;

    /**
     * Cancel the task manually.
     *
     * @return void
     */
    public function cancel(): void
    {
        $this->cancelled = true;
    }

    /**
     * Check if the task has been cancelled.
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    /**
     * Cancel the task if the condition evaluates to true.
     *
     * @param callable(): bool $condition
     * @return void
     */
    public function cancelIf(callable $condition): void
    {
        if ($condition()) {
            $this->cancel();
        }
    }

    /**
     * Continuously check a condition and cancel when it becomes true.
     *
     * Warning: This is a blocking loop. Should be used in async contexts only.
     *
     * @param callable(): bool $condition
     * @param int $intervalMs Interval in milliseconds to recheck condition (default: 100ms)
     *
     * @return void
     */
    public function cancelWhen(callable $condition, int $intervalMs = 100): void
    {
        while (!$this->cancelled) {
            if ($condition()) {
                $this->cancel();
                break;
            }

            usleep($intervalMs * 1000);
        }
    }

    /**
     * Cancel the task if a condition is true, but only after a delay.
     *
     * This is useful for scenarios where cancellation should be postponed briefly
     * after detecting a trigger condition (e.g., debounce-like behavior).
     *
     * @param callable(): bool $condition Condition that triggers cancellation.
     * @param int $delayMs Delay in milliseconds before cancellation (default: 500).
     *
     * @return void
     */
    public function cancelAfterIf(callable $condition, int $delayMs = 500): void
    {
        if ($condition()) {
            usleep($delayMs * 1000);
            $this->cancel();
        }
    }

    /**
     * Reset the cancellation state.
     *
     * @return void
     */
    public function resetCancellation(): void
    {
        $this->cancelled = false;
    }
}
