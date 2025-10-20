<?php

namespace JobMetric\Flyron\Tests\Unit\Traits;

use JobMetric\Flyron\Tests\TestCase;
use JobMetric\Flyron\Traits\Retryable;

class RetryableTest extends TestCase
{
    public function test_retry_flow_methods(): void
    {
        $obj = new class {
            use Retryable;

            public function attempt(): void
            {
                $this->incrementRetry();
            }
        };

        $this->assertSame(0, $obj->getRetryCount());
        $this->assertSame(0, $obj->getMaxRetries());
        $this->assertFalse($obj->shouldRetry());

        $obj->retry(2);
        $this->assertSame(2, $obj->getMaxRetries());
        $this->assertTrue($obj->shouldRetry());

        $obj->attempt();
        $this->assertSame(1, $obj->getRetryCount());
        $this->assertTrue($obj->shouldRetry());

        $obj->attempt();
        $this->assertSame(2, $obj->getRetryCount());
        $this->assertFalse($obj->shouldRetry());
    }
}
