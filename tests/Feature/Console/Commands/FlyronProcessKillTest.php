<?php

namespace JobMetric\Flyron\Tests\Feature\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use JobMetric\Flyron\Tests\TestCase;

class FlyronProcessKillTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new Filesystem())->ensureDirectoryExists(storage_path('flyron/pids'));
    }

    public function test_fails_on_invalid_pid_argument(): void
    {
        $this->artisan('flyron:process-kill', ['pid' => 0])
            ->assertExitCode(1);
    }

    public function test_fails_when_pid_not_managed(): void
    {
        // Ensure there is no pid file for this PID
        $pid = 654321;
        @unlink(storage_path("flyron/pids/{$pid}.pid"));

        $this->artisan('flyron:process-kill', ['pid' => $pid])
            ->assertExitCode(1);
    }
}
