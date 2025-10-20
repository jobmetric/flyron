<?php

namespace JobMetric\Flyron\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use JobMetric\Flyron\AsyncProcess;
use JobMetric\Flyron\Tests\TestCase;
use Random\RandomException;

class AsyncProcessInternalsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new Filesystem())->ensureDirectoryExists(storage_path('flyron'));
    }

    /**
     * @throws RandomException
     */
    public function test_get_app_key_decodes_base64(): void
    {
        $binary = random_bytes(32);
        config()->set('app.key', 'base64:'.base64_encode($binary));

        $proc = new class extends AsyncProcess {
            public function exposeGetAppKey(): string
            {
                return $this->getAppKey();
            }
        };

        $this->assertSame($binary, $proc->exposeGetAppKey());
    }

    /**
     * @throws RandomException
     */
    public function test_ensure_directory_creates_path(): void
    {
        $dir = storage_path('flyron/tmp_test_dir_'.bin2hex(random_bytes(4)));
        @rmdir($dir);

        $proc = new class extends AsyncProcess {
            public function exposeEnsureDirectory(string $d): void
            {
                $this->ensureDirectory($d);
            }
        };

        $proc->exposeEnsureDirectory($dir);
        $this->assertDirectoryExists($dir);

        // cleanup
        @rmdir($dir);
    }
}
