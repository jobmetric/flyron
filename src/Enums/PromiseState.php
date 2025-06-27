<?php

namespace JobMetric\Flyron\Enums;

/**
 * Enum PromiseState
 *
 * Represents the various states a Promise can be in during its lifecycle.
 *
 * States:
 * - PENDING:   The promise is still pending and has not been settled yet.
 * - FULFILLED: The promise has been fulfilled successfully.
 * - REJECTED:  The promise has been rejected with an error or exception.
 * - CANCELLED: The promise execution was cancelled before completion.
 *
 * @package JobMetric\Flyron
 */
enum PromiseState: string
{
    case PENDING = 'pending';
    case FULFILLED = 'fulfilled';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
}
