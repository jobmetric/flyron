<?php

namespace JobMetric\Flyron\Tests\Unit\Traits;

use JobMetric\Flyron\Tests\TestCase;
use JobMetric\Flyron\Traits\Cancelable;

class CancelableTest extends TestCase
{
    public function test_cancel_and_check_state(): void
    {
        $obj = new class {
            use Cancelable;
        };

        $this->assertFalse($obj->isCancelled());
        $obj->cancel();
        $this->assertTrue($obj->isCancelled());
    }
}
