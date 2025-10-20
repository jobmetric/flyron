<?php

namespace JobMetric\Flyron\Tests\Unit;

use JobMetric\Flyron\Async;
use JobMetric\Flyron\Concurrency\CancellationToken;
use JobMetric\Flyron\Tests\TestCase;
use RuntimeException;
use Throwable;

class AsyncTest extends TestCase
{
    /**
     * @throws Throwable
     */
    public function test_async_run_resolves_value(): void
    {
        $async = new Async;
        $promise = $async->run(fn (int $x) => $x * 2, [21]);
        $this->assertSame(42, $promise->run());
        $this->assertTrue($promise->isFulfilled());
    }

    public function test_checkpoint_throws_when_cancelled(): void
    {
        $token = new CancellationToken();
        $token->cancel();

        $this->expectException(RuntimeException::class);
        Async::checkpoint($token);
    }
}
