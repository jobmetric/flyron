<?php

namespace JobMetric\Flyron\Console\Commands;

use Illuminate\Console\Command;
use JobMetric\PackageCore\Commands\ConsoleTools;

/**
 * Class FlyronProcessKill
 *
 * Kill a background process that was started by Flyron and is tracked via its PID file.
 *
 * This command ensures that only processes managed by Flyron (i.e., those with
 * corresponding `.pid` files in the `storage/flyron/pids/` directory) can be terminated.
 *
 * Supports Windows, macOS, and Linux.
 *
 * @package JobMetric\Flyron
 */
class FlyronProcessKill extends Command
{
    use ConsoleTools;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flyron:process-kill
                                {pid : Process ID to kill}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kill a Flyron-managed background process by its PID';

    /**
     * Execute the console command.
     *
     * @return int 0 on success, 1 on failure.
     */
    public function handle(): int
    {
        $pid = (int)$this->argument('pid');

        if ($pid <= 0) {
            $this->error("Invalid PID provided.");

            return self::FAILURE;
        }

        $pidFile = storage_path("flyron/pids/{$pid}.pid");

        // Check that the process is managed by Flyron
        if (! file_exists($pidFile)) {
            $this->error("PID {$pid} is not managed by Flyron or has already been removed.");

            return self::FAILURE;
        }

        // Determine platform
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        // Graceful kill: try TERM first, then KILL
        if ($isWindows) {
            exec("taskkill /PID {$pid} /T", $output, $exitCode);
            if ($exitCode !== 0) {
                // Force kill as fallback
                exec("taskkill /F /PID {$pid} /T", $output, $exitCode);
            }
        } else {
            // Send SIGTERM, wait a bit, then SIGKILL if needed
            exec("kill -15 {$pid}", $output, $exitCode);
            if ($exitCode !== 0 || $this->isRunning($pid)) {
                usleep(300000);
                exec("kill -9 {$pid}", $output, $exitCode);
            }
        }

        // Clean up .pid file if successful
        if ($exitCode === 0) {
            @unlink($pidFile);
            $this->info("✅ Process {$pid} killed successfully.");
        } else {
            $this->error("❌ Failed to kill process {$pid}.");
        }

        return $exitCode;
    }
}
