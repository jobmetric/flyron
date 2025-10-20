<?php

namespace JobMetric\Flyron\Tests\Unit;

use JobMetric\Flyron\Async;
use JobMetric\Flyron\AsyncProcess;
use JobMetric\Flyron\Tests\TestCase;

class ServiceProviderBindingsTest extends TestCase
{
    public function test_bindings_are_resolved(): void
    {
        $this->assertInstanceOf(Async::class, app('flyron.async'));
        $this->assertInstanceOf(AsyncProcess::class, app('flyron.async.process'));
    }
}
