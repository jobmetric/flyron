<?php

namespace JobMetric\Flyron\Tests\Unit\Enums;

use JobMetric\Flyron\Enums\PromiseState;
use JobMetric\Flyron\Tests\TestCase;

class PromiseStateTest extends TestCase
{
    public function test_enum_values(): void
    {
        $this->assertSame('pending', PromiseState::PENDING->value);
        $this->assertSame('fulfilled', PromiseState::FULFILLED->value);
        $this->assertSame('rejected', PromiseState::REJECTED->value);
        $this->assertSame('cancelled', PromiseState::CANCELLED->value);
    }
}
