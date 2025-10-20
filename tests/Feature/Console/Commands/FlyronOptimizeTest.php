<?php

namespace JobMetric\Flyron\Tests\Feature\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use JobMetric\Flyron\Tests\TestCase;

class FlyronOptimizeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new Filesystem())->ensureDirectoryExists(storage_path('flyron/pids'));
        (new Filesystem())->ensureDirectoryExists(storage_path('flyron/payloads'));
    }

    public function test_removes_stale_pid_files(): void
    {
        $stale = storage_path('flyron/pids/0.pid');
        file_put_contents($stale, json_encode(['pid' => 0, 'created_at' => date('c')]));

        $this->artisan('flyron:optimize')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($stale);
    }
}
