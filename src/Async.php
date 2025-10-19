<?php

namespace JobMetric\Flyron;

use Fiber;
use JobMetric\Flyron\Concurrency\CancellationToken;
use JobMetric\Flyron\Concurrency\Promise;
use RuntimeException;
use Throwable;

/**
 * Class Async
 *
 * Provides utility to execute callbacks asynchronously using Fibers,
 * returning a Promise that resolves when the callback completes.
 *
 * @package JobMetric\Flyron
 */
class Async
{
    /**
     * Execute a callback asynchronously and return a Promise.
     *
     * This method runs the given callback inside a Fiber, passing any provided arguments.
     * The callback’s return value is suspended and used to fulfill the Promise.
     * If the callback throws, the exception is suspended and treated as a rejection.
     *
     * Features:
     * - Optional cancellation via CancellationToken (cooperative; checked before and after the callback).
     * - Optional timeout (best-effort) on the returned Promise.
     * - Optional debug logging of exceptions if a logger/env('APP_DEBUG') exist.
     *
     * @template T
     * @param callable(mixed ...): T $callback The callback function to execute asynchronously.
     * @param array                  $args     Optional arguments to pass to the callback.
     * @param int|null               $timeout  Optional timeout in milliseconds to cancel if it runs too long.
     * @param CancellationToken|null $token    Optional cancellation token for cooperative cancellation.
     *
     * @return Promise<T> A promise that resolves with the callback's return value.
     * @throws RuntimeException If the operation is cancelled before or after the callback execution.
     */
    public function run(callable $callback, array $args = [], ?int $timeout = null, ?CancellationToken $token = null): Promise
    {
        $fiber = new Fiber(function () use ($callback, $args, $token) {
            try {
                $this->checkCancellation($token, 'before');

                $result = $callback(...$args);

                $this->checkCancellation($token, 'after');

                Fiber::suspend($result);
            } catch (Throwable $e) {
                // Optional logging if logger exists and app is in debug mode (Laravel-style)
                $appDebug = function_exists('env') ? (bool)env('APP_DEBUG') : false;
                if (function_exists('logger') && $appDebug) {
                    logger()->error('Async error', ['exception' => $e]);
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

    /**
     * Check if the operation has been cancelled and throw an exception if so.
     *
     * @param CancellationToken|null $token
     * @param string                 $when Position in lifecycle: 'before' or 'after'
     *
     * @throws RuntimeException
     */
    private function checkCancellation(?CancellationToken $token, string $when): void
    {
        if ($token?->isCancelled()) {
            throw new RuntimeException("Operation cancelled {$when} execution.");
        }
    }
}
