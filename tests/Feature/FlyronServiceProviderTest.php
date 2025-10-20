<?php

namespace JobMetric\Flyron\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use JobMetric\Flyron\Tests\TestCase;

class FlyronServiceProviderTest extends TestCase
{
    public function test_process_clean_is_scheduled_from_config(): void
    {
        config()->set('flyron.schedule.enabled', true);
        config()->set('flyron.schedule.environments', []);
        config()->set('flyron.schedule.process_clean.enabled', true);
        config()->set('flyron.schedule.process_clean.payloads', true);
        config()->set('flyron.schedule.process_clean.frequency', 'hourlyAt(15)');

        $schedule = $this->app->make(Schedule::class);
        $events = $schedule->events();

        $found = false;
        foreach ($events as $event) {
            if (str_contains((string)$event->command, 'flyron:process-clean')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'flyron:process-clean should be scheduled');
    }
}
