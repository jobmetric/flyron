<?php

namespace JobMetric\Flyron\Console\Commands;

use Illuminate\Console\Command;
use JobMetric\PackageCore\Commands\ConsoleTools;

/**
 * Class FlyronProcessClean
 *
 * Cleans up stale PID files and payloads for Flyron-managed background processes.
 * It removes PID files whose processes are no longer running, and optionally
 * removes orphaned payload files not referenced by any running PID.
 */
class FlyronProcessClean extends Command
{
    use ConsoleTools;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flyron:process-clean {--payloads : Also clean orphaned payload files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up stale Flyron PID files (and optionally payload files)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $pidDir = storage_path('flyron/pids');
        $payloadDir = storage_path('flyron/payloads');

        $removed = 0;
        $pids = [];

        if (is_dir($pidDir)) {
            foreach (glob($pidDir.'/*.pid') as $file) {
                $pid = (int)basename($file, '.pid');
                $pids[] = $pid;
                if ($pid > 0 && ! $this->isRunning($pid)) {
                    @unlink($file);
                    $removed++;
                }
            }
        }

        $this->message("Removed {$removed} stale PID files.");

        if ($this->option('payloads') && is_dir($payloadDir)) {
            $removedPayloads = 0;
            // Remove payloads older than 1 day with no corresponding running process
            $threshold = time() - 86400;
            foreach (glob($payloadDir.'/*.json') as $pfile) {
                $mtime = filemtime($pfile) ?: 0;
                if ($mtime < $threshold) {
                    @unlink($pfile);
                    $removedPayloads++;
                }
            }
            $this->message("Removed {$removedPayloads} old payload files.");
        }

        return self::SUCCESS;
    }

    /**
     * Determine whether a process with the given PID is running.
     *
     * @param int $pid
     *
     * @return bool
     */
    protected function isRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        $os = strtoupper(substr(PHP_OS, 0, 3));

        if ($os === 'WIN') {
            exec("tasklist /FI \"PID eq {$pid}\" 2>NUL", $output);
            foreach ($output as $line) {
                if (preg_match('/\\b'.preg_quote((string)$pid, '/').'\\b/', $line)) {
                    return true;
                }
            }

            return false;
        }

        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        exec("ps -p {$pid}", $output);

        return isset($output[1]);
    }
}

