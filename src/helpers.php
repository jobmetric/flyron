<?php

use JobMetric\Flyron\Concurrency\CancellationToken;
use JobMetric\Flyron\Concurrency\Promise;
use JobMetric\Flyron\Facades\Async;

if (! function_exists('async')) {
    /**
     * Run an async task with Flyron easily.
     *
     * @param callable $callback
     * @param array $args
     * @param int|null $timeout
     * @param CancellationToken|null $token
     *
     * @return Promise
     */
    function async(callable $callback, array $args = [], ?int $timeout = null, ?CancellationToken $token = null): Promise
    {
        return Async::run($callback, $args, $timeout, $token);
    }
}
