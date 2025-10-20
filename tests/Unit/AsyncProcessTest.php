<?php

namespace JobMetric\Flyron\Tests\Unit;

use JobMetric\Flyron\AsyncProcess;
use JobMetric\Flyron\Tests\TestCase;
use Laravel\SerializableClosure\Exceptions\PhpVersionNotSupportedException;
use RuntimeException;

class AsyncProcessTest extends TestCase
{
    /**
     * @throws PhpVersionNotSupportedException
     */
    public function test_throws_on_invalid_php_path(): void
    {
        config()->set('flyron.php_path', '');
        $this->expectException(RuntimeException::class);
        (new AsyncProcess())->run(fn () => null);
    }

    /**
     * @throws PhpVersionNotSupportedException
     */
    public function test_throws_when_artisan_missing(): void
    {
        config()->set('flyron.php_path', PHP_BINARY);
        config()->set('flyron.artisan_path', base_path('artisan_not_exists'));
        $this->expectException(RuntimeException::class);
        (new AsyncProcess())->run(fn () => null);
    }
}
