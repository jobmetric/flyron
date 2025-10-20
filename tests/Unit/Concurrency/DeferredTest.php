<?php

namespace JobMetric\Flyron\Tests\Unit\Concurrency;

use JobMetric\Flyron\Concurrency\Deferred;
use JobMetric\Flyron\Concurrency\Promise;
use JobMetric\Flyron\Tests\TestCase;
use RuntimeException;
use Throwable;

class DeferredTest extends TestCase
{
    public function test_create_returns_pending_promise(): void
    {
        $d = Deferred::create();
        $p = $d->promise();

        $this->assertInstanceOf(Promise::class, $p);
        $this->assertTrue($p->isPending());
    }

    /**
     * @throws Throwable
     */
    public function test_resolve_fulfills_promise(): void
    {
        $d = Deferred::create();
        $p = $d->promise();

        $d->resolve('value');

        $this->assertSame('value', $p->run());
        $this->assertTrue($p->isFulfilled());
    }

    /**
     * @throws Throwable
     */
    public function test_reject_sets_rejected_state(): void
    {
        $d = Deferred::create();
        $p = $d->promise();

        $err = new RuntimeException('boom');
        $d->reject($err);

        $this->expectException(RuntimeException::class);
        try {
            $p->run();
        } finally {
            $this->assertTrue($p->isRejected());
            $this->assertInstanceOf(Throwable::class, $p->getException());
        }
    }
}
