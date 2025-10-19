<?php

namespace JobMetric\Flyron\Facades;

use Illuminate\Support\Facades\Facade;
use JobMetric\Flyron\Concurrency\CancellationToken;
use JobMetric\Flyron\Concurrency\Promise;

/**
 * @mixin \JobMetric\Flyron\Async
 *
 * @method static Promise run(callable $callback, array $args = [], ?int $timeout = null, ?CancellationToken $token = null)
 * @method static void checkpoint(?CancellationToken $token, string $message = 'Operation cancelled.')
 * @method static void delay(int $ms)
 */
class Async extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'flyron.async';
    }
}
