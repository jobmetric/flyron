<?php

namespace JobMetric\Flyron\Console\Commands;

use Illuminate\Console\Command;
use JobMetric\PackageCore\Commands\ConsoleTools;

/**
 * Class FlyronOptimize
 *
 * Cleans up stale (dead) background process PID files created by Flyron.
 * Ensures that only processes which are no longer running are removed from tracking.
 *
 * Supports Linux, macOS, and Windows.
 *
 * @package JobMetric\Flyron
 */
class FlyronOptimize extends Command
{
    use ConsoleTools;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flyron:optimize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up stale PID files for background processes managed by Flyron';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $pidPath = storage_path('flyron/pids');

        if (!is_dir($pidPath)) {
            $this->info('✅ No PID directory found. Nothing to optimize.');
            return 0;
        }

        $pidFiles = glob("{$pidPath}/*.pid");

        if (empty($pidFiles)) {
            $this->info('✅ No PID files to clean.');
            return 0;
        }

        $cleaned = 0;

        foreach ($pidFiles as $file) {
            $pid = (int) basename($file, '.pid');

            if (!$this->isRunning($pid)) {
                if (@unlink($file)) {
                    $this->line("🧹 Removed stale PID file: {$pid}.pid");
                    $cleaned++;
                } else {
                    $this->warn("⚠️ Could not delete PID file: {$pid}.pid");
                }
            }
        }

        $cleaned
            ? $this->info("✅ Cleanup complete. {$cleaned} dead process file(s) removed.")
            : $this->info("🎉 All PID files belong to active processes.");

        return 0;
    }

    /**
     * Determine whether a process with the given PID is still running.
     *
     * @param int $pid The process ID to check.
     *
     * @return bool True if process is running; false otherwise.
     */
    protected function isRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("tasklist /FI \"PID eq {$pid}\" 2>NUL", $output);
            return isset($output[1]) && str_contains($output[1], (string)$pid);
        }

        return function_exists('posix_kill') && posix_kill($pid, 0);
    }
}
