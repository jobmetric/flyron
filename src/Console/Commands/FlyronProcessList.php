<?php

namespace JobMetric\Flyron\Console\Commands;

use Illuminate\Console\Command;
use JobMetric\PackageCore\Commands\ConsoleTools;

/**
 * Class FlyronProcessList
 *
 * Displays a list of background processes managed by Flyron.
 * Scans the storage PID directory and checks if the related process is still alive.
 *
 * Supports Windows, macOS, and Linux.
 *
 * @package JobMetric\Flyron
 */
class FlyronProcessList extends Command
{
    use ConsoleTools;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flyron:process-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all registered Flyron background processes and their status.';

    /**
     * Handle the execution of the command.
     *
     * @return int
     */
    public function handle(): int
    {
        $path = storage_path('flyron/pids');

        if (!is_dir($path)) {
            $this->warn('No PID directory found.');
            return self::SUCCESS;
        }

        $files = glob("{$path}/*.pid");

        if (empty($files)) {
            $this->info('No running Flyron processes.');
            return self::SUCCESS;
        }

        $this->line('');
        $this->info("📋 Flyron Background Processes");
        $this->line(str_repeat('-', 40));

        foreach ($files as $file) {
            $pid = basename($file, '.pid');
            $status = $this->isRunning((int)$pid) ? '✅ Alive' : '❌ Dead';
            $this->line("🧩 PID: {$pid} | Status: {$status}");
        }

        $this->line(str_repeat('-', 40));
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Determine whether a process with the given PID is running.
     *
     * @param int $pid
     * @return bool
     */
    protected function isRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        $os = strtoupper(substr(PHP_OS, 0, 3));

        if ($os === 'WIN') {
            // Tasklist command for Windows
            exec("tasklist /FI \"PID eq {$pid}\" 2>NUL", $output);
            foreach ($output as $line) {
                if (preg_match('/\b' . preg_quote((string)$pid, '/') . '\b/', $line)) {
                    return true;
                }
            }
            return false;
        }

        // POSIX systems (Linux/macOS)
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        // Fallback
        exec("ps -p {$pid}", $output);
        return isset($output[1]);
    }
}
