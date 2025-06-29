<?php

namespace JobMetric\Flyron\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \JobMetric\Flyron\AsyncProcess
 *
 * @method static int|null run(callable $callback, array $args = [])
 */
class AsyncProcess extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'flyron.async.process';
    }
}
