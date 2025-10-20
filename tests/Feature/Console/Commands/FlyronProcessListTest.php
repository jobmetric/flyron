<?php

namespace JobMetric\Flyron\Tests\Feature\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use JobMetric\Flyron\Tests\TestCase;

class FlyronProcessListTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new Filesystem())->ensureDirectoryExists(storage_path('flyron/pids'));
        (new Filesystem())->ensureDirectoryExists(storage_path('flyron/payloads'));
    }

    public function test_process_list_runs_with_meta(): void
    {
        $pidFile = storage_path('flyron/pids/12345.pid');
        file_put_contents($pidFile, json_encode(['pid' => 12345, 'label' => 'demo', 'created_at' => date('c'), 'payload' => storage_path('flyron/payloads/x.json'),]));

        $this->artisan('flyron:process-list')
            ->assertExitCode(0);
    }
}
