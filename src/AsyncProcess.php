<?php

namespace JobMetric\Flyron;

use Laravel\SerializableClosure\Exceptions\PhpVersionNotSupportedException;
use Laravel\SerializableClosure\SerializableClosure;
use RuntimeException;
use Symfony\Component\Process\Process;

class AsyncProcess
{
    /**
     * Dispatch a callable to be run in a separate process.
     *
     * @param callable $callback
     * @param array $args
     * @return int|null
     * @throws PhpVersionNotSupportedException
     */
    public function run(callable $callback, array $args = []): ?int
    {
        $serializable = serialize(new SerializableClosure(fn () => $callback(...$args)));
        $escaped = escapeshellarg($serializable);

        $phpPath = config('flyron.php_path', PHP_BINARY);
        $artisan = config('flyron.artisan_path', base_path('artisan'));

        if (!file_exists($artisan)) {
            throw new RuntimeException("Artisan file not found at: {$artisan}");
        }

        $command = "{$phpPath} {$artisan} flyron:exec {$escaped}";

        $process = Process::fromShellCommandline($command);
        $process->start();

        $pid = $process->getPid();

        if ($pid) {
            file_put_contents(storage_path("flyron/pids/{$pid}.pid"), $command);
        }

        return $pid;
    }
}
