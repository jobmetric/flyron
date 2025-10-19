<?php

use JobMetric\Flyron\Concurrency\CancellationToken;
use JobMetric\Flyron\Concurrency\Promise;
use JobMetric\Flyron\Facades\Async;
use JobMetric\Flyron\Facades\AsyncProcess;

if (! function_exists('async')) {
    /**
     * Run an async task with Flyron easily.
     *
     * @param callable               $callback
     * @param array                  $args
     * @param int|null               $timeout
     * @param CancellationToken|null $token
     *
     * @return Promise
     */
    function async(callable $callback, array $args = [], ?int $timeout = null, ?CancellationToken $token = null): Promise
    {
        return Async::run($callback, $args, $timeout, $token);
    }
}

if (! function_exists('async_process')) {
    /**
     * Run a background process using Flyron AsyncProcess with a simple helper.
     *
     * The callable will be serialized and executed via `flyron:exec` in a separate
     * PHP process. See config('flyron.process') for security and throttling options.
     *
     * @param callable $callback The closure to run in background.
     * @param array    $args     Arguments passed to the closure.
     * @param array    $options  example: {cwd?: string|null, env?: array|null, timeout?: float|null, idle_timeout?: float|null, disable_output?: bool|null, label?: string|null}
     *
     * @return int|null The spawned process PID or null if unavailable.
     */
    function async_process(callable $callback, array $args = [], array $options = []): ?int
    {
        return AsyncProcess::run($callback, $args, $options);
    }
}
