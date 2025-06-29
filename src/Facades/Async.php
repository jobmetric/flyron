<?php

namespace JobMetric\Flyron\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \JobMetric\Flyron\Async
 *
 * @method static \JobMetric\Flyron\Concurrency\Promise run(callable $callback, array $args = [], ?int $timeout = null, ?\JobMetric\Flyron\Concurrency\CancellationToken $token = null)
 */
class Async extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'flyron.async';
    }
}
