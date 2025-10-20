<?php

namespace JobMetric\Flyron\Tests\Feature\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use JobMetric\Flyron\Tests\TestCase;

class FlyronProcessCleanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new Filesystem())->ensureDirectoryExists(storage_path('flyron/payloads'));
    }

    public function test_process_clean_removes_old_payloads(): void
    {
        $payload = storage_path('flyron/payloads/old.json');
        file_put_contents($payload, '{}');
        @touch($payload, time() - 3600);

        config()->set('flyron.process.payload_ttl_seconds', 1);

        $this->artisan('flyron:process-clean', ['--payloads' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($payload);
    }
}
