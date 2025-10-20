<?php

namespace JobMetric\Flyron\Tests\Unit;

use JobMetric\Flyron\Concurrency\Promise;
use JobMetric\Flyron\Facades\Async as AsyncFacade;
use JobMetric\Flyron\Facades\AsyncProcess as AsyncProcessFacade;
use JobMetric\Flyron\Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_async_helper_proxies_to_facade(): void
    {
        $promise = Promise::from(fn () => 'ok');

        AsyncFacade::shouldReceive('run')
            ->once()
            ->withArgs(function ($callback, $args, $timeout, $token) {
                return is_callable($callback) && $args === [41] && $timeout === null && $token === null;
            })
            ->andReturn($promise);

        $p = async(fn (int $x) => $x + 1, [41]);
        $this->assertInstanceOf(Promise::class, $p);
        $this->assertSame('ok', $p->run());
    }

    public function test_async_process_helper_proxies_to_facade(): void
    {
        AsyncProcessFacade::shouldReceive('run')
            ->once()
            ->withArgs(function ($callback, $args, $options) {
                return is_callable($callback) && $args === [1, 2] && $options === ['label' => 'job'];
            })
            ->andReturn(4321);

        $pid = async_process(fn ($a, $b) => $a + $b, [1, 2], ['label' => 'job']);

        $this->assertSame(4321, $pid);
    }
}
