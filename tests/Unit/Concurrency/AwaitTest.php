<?php

namespace JobMetric\Flyron\Tests\Unit\Concurrency;

use JobMetric\Flyron\Concurrency\Await;
use JobMetric\Flyron\Concurrency\Promise;
use JobMetric\Flyron\Tests\TestCase;
use RuntimeException;
use Throwable;

class AwaitTest extends TestCase
{
    /**
     * @throws Throwable
     */
    public function test_all_resolves_values(): void
    {
        $p1 = Promise::from(fn () => 1);
        $p2 = Promise::from(fn () => 2);
        $p3 = Promise::from(fn () => 3);

        $res = Await::all([$p1, $p2, $p3]);
        $this->assertSame([1, 2, 3], array_values($res));
    }

    /**
     * @throws Throwable
     */
    public function test_race_returns_first_settled(): void
    {
        $p1 = Promise::from(function () {
            usleep(10_000);

            return 'late';
        });
        $p2 = Promise::from(fn () => 'early');

        $val = Await::race([$p2, $p1]);
        $this->assertSame('early', $val);
    }

    /**
     * @throws Throwable
     */
    public function test_any_returns_first_fulfilled(): void
    {
        $rej = Promise::from(function () {
            throw new RuntimeException('no');
        });
        $ok = Promise::from(function () {
            return 'yes';
        });

        $val = Await::any([$rej, $ok]);
        $this->assertSame('yes', $val);
    }

    /**
     * @throws Throwable
     */
    public function test_any_throws_if_all_rejected(): void
    {
        $p1 = Promise::from(function () {
            throw new RuntimeException('bad');
        });
        $p2 = Promise::from(function () {
            throw new RuntimeException('worse');
        });

        $this->expectException(RuntimeException::class);
        Await::any([$p1, $p2]);
    }

    public function test_allSettled_returns_statuses(): void
    {
        $ok = Promise::from(fn () => 7);
        $bad = Promise::from(function () {
            throw new RuntimeException('x');
        });

        $out = Await::allSettled([$ok, $bad]);
        $this->assertSame('fulfilled', $out[0]['status']);
        $this->assertSame(7, $out[0]['value']);
        $this->assertSame('rejected', $out[1]['status']);
        $this->assertInstanceOf(Throwable::class, $out[1]['reason']);
    }

    public function test_until_success_and_timeout(): void
    {
        $i = 0;
        $val = Await::until(function () use (&$i) {
            $i++;

            return $i >= 3 ? 'ok' : null;
        }, 200, 10);
        $this->assertSame('ok', $val);

        $this->expectException(RuntimeException::class);
        Await::until(fn () => false, 50, 10);
    }
}
