<?php

namespace JobMetric\Flyron\Tests\Unit\Facades;

use JobMetric\Flyron\AsyncProcess as AsyncProcessService;
use JobMetric\Flyron\Facades\AsyncProcess;
use JobMetric\Flyron\Tests\TestCase;

class AsyncProcessFacadeTest extends TestCase
{
    public function test_facade_root_is_service_instance(): void
    {
        $root = AsyncProcess::getFacadeRoot();
        $this->assertInstanceOf(AsyncProcessService::class, $root);
    }

    public function test_run_via_facade_is_mockable(): void
    {
        AsyncProcess::shouldReceive('run')
            ->once()
            ->andReturn(111);

        $pid = AsyncProcess::run(fn () => null);
        $this->assertSame(111, $pid);
    }
}
