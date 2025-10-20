<?php

namespace JobMetric\Flyron\Tests\Unit\Concurrency;

use JobMetric\Flyron\Concurrency\CancellationToken;
use JobMetric\Flyron\Tests\TestCase;

class CancellationTokenTest extends TestCase
{
    public function test_cancel_and_is_cancelled(): void
    {
        $t = new CancellationToken();
        $this->assertFalse($t->isCancelled());
        $t->cancel();
        $this->assertTrue($t->isCancelled());
    }
}
