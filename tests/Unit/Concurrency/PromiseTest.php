<?php

namespace JobMetric\Flyron\Tests\Unit\Concurrency;

use JobMetric\Flyron\Concurrency\Deferred;
use JobMetric\Flyron\Concurrency\Promise;
use JobMetric\Flyron\Tests\TestCase;
use RuntimeException;
use Throwable;

class PromiseTest extends TestCase
{
    /**
     * @throws Throwable
     */
    public function test_from_resolves_value(): void
    {
        $p = Promise::from(fn (int $a, int $b) => $a + $b, [2, 3]);
        $this->assertSame(5, $p->run());
        $this->assertTrue($p->isFulfilled());
        $this->assertSame('fulfilled', $p->getState());
    }

    /**
     * @throws Throwable
     */
    public function test_from_rejects_exception(): void
    {
        $p = Promise::from(function (): int {
            throw new RuntimeException('boom');
        });

        $this->expectException(RuntimeException::class);
        try {
            $p->run();
        } finally {
            $this->assertTrue($p->isRejected());
        }
    }

    /**
     * @throws Throwable
     */
    public function test_with_timeout_times_out(): void
    {
        $slow = Promise::from(function (): string {
            usleep(120_000);

            return 'ok';
        });

        $timed = Promise::withTimeout($slow, 50);

        $this->expectException(RuntimeException::class);
        $timed->run();
    }

    /**
     * @throws Throwable
     */
    public function test_deferred_resolve_and_reject(): void
    {
        $d = Deferred::create();
        $p = $d->promise();

        $d->resolve('ready');
        $this->assertSame('ready', $p->run());
        $this->assertTrue($p->isFulfilled());

        $d2 = Deferred::create();
        $p2 = $d2->promise();
        $e = new RuntimeException('nope');
        $d2->reject($e);

        $this->expectException(RuntimeException::class);
        try {
            $p2->run();
        } finally {
            $this->assertTrue($p2->isRejected());
            $this->assertInstanceOf(Throwable::class, $p2->getException());
        }
    }

    /**
     * @throws Throwable
     */
    public function test_then_map_tap_recover_finally_and_cancel(): void
    {
        $tapCount = 0;
        $finallyCount = 0;

        $p = Promise::from(fn () => 10)
            ->tap(function ($v) use (&$tapCount) {
                $tapCount++;
            })
            ->map(fn ($v) => $v + 5)
            ->then(fn ($v) => Promise::from(fn () => $v * 2))
            ->finally(function () use (&$finallyCount) {
                $finallyCount++;
            });

        $this->assertSame(30, $p->run());
        $this->assertSame(1, $tapCount);
        $this->assertSame(1, $finallyCount);

        $p2 = Promise::from(fn () => 1);
        $this->assertTrue($p2->isPending());
        $p2->cancel();
        $this->assertTrue($p2->isCancelled());

        $this->expectException(RuntimeException::class);
        $p2->run();
    }
}
