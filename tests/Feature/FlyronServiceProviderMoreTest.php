<?php

namespace JobMetric\Flyron\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use JobMetric\Flyron\Tests\TestCase;

class FlyronServiceProviderMoreTest extends TestCase
{
    public function test_optimize_is_scheduled_from_config(): void
    {
        config()->set('flyron.schedule.enabled', true);
        config()->set('flyron.schedule.environments', []);
        config()->set('flyron.schedule.process_optimize.enabled', true);
        config()->set('flyron.schedule.process_optimize.frequency', 'daily');

        $schedule = $this->app->make(Schedule::class);
        $events = $schedule->events();

        $found = false;
        foreach ($events as $event) {
            if (str_contains((string)$event->command, 'flyron:optimize')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'flyron:optimize should be scheduled');
    }

    public function test_frequency_parsing_supports_cron_expression(): void
    {
        config()->set('flyron.schedule.enabled', true);
        config()->set('flyron.schedule.environments', []);
        config()->set('flyron.schedule.process_clean.enabled', true);
        config()->set('flyron.schedule.process_clean.payloads', false);
        config()->set('flyron.schedule.process_clean.frequency', '0 12 * * *'); // cron at 12:00

        $schedule = $this->app->make(Schedule::class);
        $events = $schedule->events();

        $found = false;
        foreach ($events as $event) {
            if (str_contains((string)$event->command, 'flyron:process-clean')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'flyron:process-clean should be scheduled via cron expression');
    }

    public function test_frequency_parsing_supports_method_with_parentheses(): void
    {
        config()->set('flyron.schedule.enabled', true);
        config()->set('flyron.schedule.environments', []);
        config()->set('flyron.schedule.process_clean.enabled', true);
        config()->set('flyron.schedule.process_clean.payloads', false);
        config()->set('flyron.schedule.process_clean.frequency', 'dailyAt(13:30)');

        $schedule = $this->app->make(Schedule::class);
        $events = $schedule->events();

        $found = false;
        foreach ($events as $event) {
            if (str_contains((string)$event->command, 'flyron:process-clean')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'flyron:process-clean should be scheduled via method with parentheses');
    }

    public function test_frequency_parsing_supports_method_with_colon(): void
    {
        config()->set('flyron.schedule.enabled', true);
        config()->set('flyron.schedule.environments', []);
        config()->set('flyron.schedule.process_clean.enabled', true);
        config()->set('flyron.schedule.process_clean.payloads', false);
        config()->set('flyron.schedule.process_clean.frequency', 'hourlyAt:15');

        $schedule = $this->app->make(Schedule::class);
        $events = $schedule->events();

        $found = false;
        foreach ($events as $event) {
            if (str_contains((string)$event->command, 'flyron:process-clean')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'flyron:process-clean should be scheduled via method with colon args');
    }
}
