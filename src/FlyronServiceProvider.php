<?php

namespace JobMetric\Flyron;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use JobMetric\Flyron\Console\Commands\FlyronExec;
use JobMetric\Flyron\Console\Commands\FlyronOptimize;
use JobMetric\Flyron\Console\Commands\FlyronProcessClean;
use JobMetric\Flyron\Console\Commands\FlyronProcessKill;
use JobMetric\Flyron\Console\Commands\FlyronProcessList;
use JobMetric\PackageCore\Exceptions\RegisterClassTypeNotFoundException;
use JobMetric\PackageCore\PackageCore;
use JobMetric\PackageCore\PackageCoreServiceProvider;

class FlyronServiceProvider extends PackageCoreServiceProvider
{
    /**
     * @param PackageCore $package
     *
     * @return void
     * @throws RegisterClassTypeNotFoundException
     */
    public function configuration(PackageCore $package): void
    {
        $package->name('flyron')
            ->hasConfig()
            ->registerCommand(FlyronProcessList::class)
            ->registerCommand(FlyronProcessClean::class)
            ->registerCommand(FlyronProcessKill::class)
            ->registerCommand(FlyronOptimize::class)
            ->registerCommand(FlyronExec::class)
            ->registerClass('flyron.async', Async::class)
            ->registerClass('flyron.async.process', AsyncProcess::class);
    }

    /**
     * Hook for console context to register schedules based on config.
     *
     * Do not override boot(); use this helper hook according to PackageCore.
     * Reads configuration from config('flyron.schedule').
     *
     * @return void
     */
    public function runInConsolePackage(): void
    {
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
            $cfg = (array)config('flyron.schedule', []);
            if (! ($cfg['enabled'] ?? true)) {
                return;
            }

            // Schedule process-clean
            $clean = (array)($cfg['process_clean'] ?? []);
            if ($clean['enabled'] ?? false) {
                $payloads = ($clean['payloads'] ?? true) ? ['--payloads' => true] : [];
                $event = $schedule->command('flyron:process-clean', $payloads);
                $this->applyFrequency($event, (string)($clean['frequency'] ?? 'hourly'));
            }

            // Optional: schedule flyron:optimize if enabled
            $opt = (array)($cfg['process_optimize'] ?? []);
            if ($opt['enabled'] ?? false) {
                $event = $schedule->command('flyron:optimize');
                $this->applyFrequency($event, (string)($opt['frequency'] ?? 'weekly'));
            }
        });
    }

    /**
     * Apply schedule rules to an Event with comprehensive coverage.
     *
     * @param Event $event
     * @param mixed $rules String, cron, or array of chained rules
     *
     * @return void
     */
    protected function applyFrequency($event, mixed $rules): void
    {
        if (is_array($rules)) {
            foreach ($rules as $rule) {
                $this->applyFrequency($event, $rule);
            }

            return;
        }

        if (! is_string($rules) || $rules === '') {
            return;
        }

        $rule = trim($rules);

        // Cron expression (5 space-separated fields)
        if ($this->looksLikeCron($rule)) {
            $event->cron($rule);

            return;
        }

        // Method with parentheses, e.g. dailyAt(13:30)
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*\((.*)\)\s*$/', $rule, $m)) {
            $method = $m[1];
            $args = $this->parseArgs($m[2]);
            $this->callEventMethod($event, $method, $args);

            return;
        }

        // Method with colon args, e.g. hourlyAt:15
        if (str_contains($rule, ':')) {
            [$method, $argStr] = array_pad(explode(':', $rule, 2), 2, '');
            $method = trim($method);
            $args = $this->parseArgs($argStr);
            $this->callEventMethod($event, $method, $args);

            return;
        }

        // Plain method
        $this->callEventMethod($event, $rule, []);
    }

    /**
     * Determine if a string resembles a cron expression (5 fields).
     */
    protected function looksLikeCron(string $expr): bool
    {
        return (bool)preg_match('/^\S+\s+\S+\s+\S+\s+\S+\s+\S+$/', $expr);
    }

    /**
     * Parse a comma-separated argument list into an array, trimming quotes.
     */
    protected function parseArgs(string $argStr): array
    {
        if ($argStr === '') {
            return [];
        }
        $parts = array_map(function ($v) {
            $v = trim($v);
            if (($v !== '') && ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'")))) {
                $v = substr($v, 1, -1);
            }

            return $v;
        }, array_map('trim', explode(',', $argStr)));

        return array_values(array_filter($parts, fn ($v) => $v !== ''));
    }

    /**
     * Call a scheduling method on the Event if it exists; otherwise fallback to cron if string is cron-like.
     */
    protected function callEventMethod($event, string $method, array $args): void
    {
        if (method_exists($event, $method)) {
            $event->{$method}(...$args);

            return;
        }

        if ($this->looksLikeCron($method)) {
            $event->cron($method);
        }
    }
}
