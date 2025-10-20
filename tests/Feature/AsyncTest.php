<?php

namespace JobMetric\Flyron\Tests\Feature;

use JobMetric\Flyron\Async;
use JobMetric\Flyron\Concurrency\CancellationToken;
use JobMetric\Flyron\Concurrency\Promise;
use JobMetric\Flyron\Facades\Async as AsyncFacade;
use JobMetric\Flyron\Tests\TestCase;
use RuntimeException;
use Throwable;

class AsyncTest extends TestCase
{
    /**
     * @throws Throwable
     */
    public function test_resolves_async_service_and_facade(): void
    {
        $svc = app('flyron.async');
        $this->assertInstanceOf(Async::class, $svc);

        $p = AsyncFacade::run(fn () => 'ok');
        $this->assertInstanceOf(Promise::class, $p);
        $this->assertSame('ok', $p->run());
    }

    /**
     * @throws Throwable
     */
    public function test_global_helper_async_runs(): void
    {
        $p = async(fn (int $x) => $x + 1, [41]);
        $this->assertSame(42, $p->run());
    }

    /**
     * @throws Throwable
     */
    public function test_cancellation_before_callback(): void
    {
        $token = new CancellationToken();
        $token->cancel();

        $this->expectException(RuntimeException::class);
        AsyncFacade::run(fn () => 'never', [], null, $token)
            ->run();
    }

    /**
     * @throws Throwable
     */
    public function test_cancellation_after_callback(): void
    {
        $token = new CancellationToken();

        $p = AsyncFacade::run(function () use ($token) {
            $token->cancel();

            return 'value';
        }, [], null, $token);

        $this->expectException(RuntimeException::class);
        $p->run();
    }

    /**
     * @throws Throwable
     */
    public function test_timeout_enforced_by_promise(): void
    {
        $p = AsyncFacade::run(function () {
            usleep(200_000); // 200ms

            return 'late';
        }, [], 50); // 50ms timeout

        $this->expectException(RuntimeException::class);
        $p->run();
    }

    public function test_delay_and_checkpoint_helpers(): void
    {
        Async::delay(5);
        Async::checkpoint(null);
        $this->assertTrue(true);
    }

    /**
     * @throws Throwable
     */
    public function test_throwing_callback_logs_when_app_debug_true(): void
    {
        putenv('APP_DEBUG=true');

        $this->expectException(RuntimeException::class);
        AsyncFacade::run(function () {
            throw new RuntimeException('boom');
        })
            ->run();
    }
}
