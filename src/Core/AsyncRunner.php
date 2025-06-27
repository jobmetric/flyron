<?php

namespace JobMetric\Flyron\Core;

use Fiber;
use RuntimeException;
use Throwable;

/**
 * Class AsyncRunner
 *
 * Provides utility to execute callbacks asynchronously using Fibers,
 * returning a Promise that resolves when the callback completes.
 *
 * @package JobMetric\Flyron
 */
class AsyncRunner
{
    /**
     * Execute a callback asynchronously and return a Promise.
     *
     * This method runs the given callback inside a Fiber, passing any provided arguments.
     * It also supports optional cancellation via CancellationToken and a timeout in milliseconds.
     * The returned Promise will be resolved with the callback's return value or rejected if an exception occurs.
     *
     * @template T
     *
     * @param callable(mixed ...): T $callback The callback function to execute asynchronously.
     * @param array $args Optional array of arguments to pass to the callback.
     * @param int|null $timeout Optional timeout in milliseconds to cancel the operation if it runs too long.
     * @param CancellationToken|null $token Optional cancellation token to allow cooperative cancellation.
     *
     * @return Promise<T> A promise that resolves with the callback's return value.
     * @throws RuntimeException If the operation is cancelled before or after the callback execution.
     */
    public static function async(callable $callback, array $args = [], ?int $timeout = null, ?CancellationToken $token = null): Promise
    {
        $fiber = new Fiber(function () use ($callback, $args, $token) {
            try {
                if ($token?->isCancelled()) {
                    throw new RuntimeException('Operation cancelled before start.');
                }

                $result = $callback(...$args);

                if ($token?->isCancelled()) {
                    throw new RuntimeException('Operation cancelled after execution.');
                }

                Fiber::suspend($result);
            } catch (Throwable $e) {
                // Optional logging if logger exists and app is in debug mode
                if (function_exists('logger') && env('APP_DEBUG')) {
                    logger()->error('AsyncRunner error', ['exception' => $e]);
                }

                Fiber::suspend($e);
            }
        });

        $promise = new Promise($fiber);

        if ($timeout !== null) {
            $promise->timeout($timeout);
        }

        return $promise;
    }
}
