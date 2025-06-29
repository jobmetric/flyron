<?php

namespace JobMetric\Flyron;

use JobMetric\Flyron\Console\Commands\FlyronExec;
use JobMetric\Flyron\Console\Commands\FlyronOptimize;
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
            ->registerCommand(FlyronProcessKill::class)
            ->registerCommand(FlyronOptimize::class)
            ->registerCommand(FlyronExec::class)
            ->registerClass('flyron.async', Async::class)
            ->registerClass('flyron.async.process', AsyncProcess::class);
    }
}
