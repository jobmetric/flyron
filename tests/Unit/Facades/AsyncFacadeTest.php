<?php

namespace JobMetric\Flyron\Tests\Unit\Facades;

use JobMetric\Flyron\Async as AsyncService;
use JobMetric\Flyron\Concurrency\Promise;
use JobMetric\Flyron\Facades\Async;
use JobMetric\Flyron\Tests\TestCase;
use Throwable;

class AsyncFacadeTest extends TestCase
{
    public function test_facade_root_is_service_instance(): void
    {
        $root = Async::getFacadeRoot();
        $this->assertInstanceOf(AsyncService::class, $root);
    }

    /**
     * @throws Throwable
     */
    public function test_run_via_facade_returns_promise_and_resolves(): void
    {
        $p = Async::run(fn () => 'ok');
        $this->assertInstanceOf(Promise::class, $p);
        $this->assertSame('ok', $p->run());
    }
}
