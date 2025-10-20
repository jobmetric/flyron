<?php

namespace JobMetric\Flyron\Tests\Feature\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use JobMetric\Flyron\Tests\TestCase;
use Laravel\SerializableClosure\Exceptions\PhpVersionNotSupportedException;
use Laravel\SerializableClosure\SerializableClosure;
use Random\RandomException;

class FlyronExecTest extends TestCase
{
    /**
     * @throws RandomException
     */
    protected function setUp(): void
    {
        parent::setUp();

        (new Filesystem())->ensureDirectoryExists(storage_path('flyron/payloads'));
        (new Filesystem())->ensureDirectoryExists(storage_path('flyron/pids'));

        $key = random_bytes(32);
        config()->set('app.key', 'base64:'.base64_encode($key));
    }

    /**
     * @throws PhpVersionNotSupportedException
     */
    public function test_exec_runs_signed_payload(): void
    {
        $outFile = storage_path('flyron/test_exec_ok.txt');
        @unlink($outFile);

        $closure = new SerializableClosure(function () use ($outFile) {
            file_put_contents($outFile, 'done');
        });

        $serialized = serialize($closure);
        $secret = $this->appKeyBinary();
        $payload = ['c' => base64_encode($serialized), 'h' => hash_hmac('sha256', $serialized, $secret), 't' => time(),];

        $path = storage_path('flyron/payloads/exec_ok.json');
        file_put_contents($path, json_encode($payload));

        $this->artisan('flyron:exec', ['payload_path' => $path])
            ->assertExitCode(0);

        $this->assertFileExists($outFile);
        $this->assertSame('done', file_get_contents($outFile));
    }

    /**
     * @throws PhpVersionNotSupportedException
     */
    public function test_exec_fails_on_bad_hmac(): void
    {
        $closure = new SerializableClosure(function () {
        });
        $serialized = serialize($closure);
        $payload = ['c' => base64_encode($serialized), 'h' => 'deadbeef', 't' => time(),];
        $path = storage_path('flyron/payloads/exec_bad.json');
        file_put_contents($path, json_encode($payload));

        $this->artisan('flyron:exec', ['payload_path' => $path])
            ->assertExitCode(1);
    }

    /**
     * @throws PhpVersionNotSupportedException
     */
    public function test_exec_rejects_outside_payloads_path(): void
    {
        $closure = new SerializableClosure(function () {
        });
        $serialized = serialize($closure);
        $secret = $this->appKeyBinary();
        $payload = ['c' => base64_encode($serialized), 'h' => hash_hmac('sha256', $serialized, $secret), 't' => time(),];
        $path = storage_path('flyron/outside.json');
        file_put_contents($path, json_encode($payload));

        $this->artisan('flyron:exec', ['payload_path' => $path])
            ->assertExitCode(1);
    }

    private function appKeyBinary(): string
    {
        $key = config('app.key');
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return (string)$key;
    }
}
