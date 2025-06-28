<?php

namespace JobMetric\Flyron;

use JobMetric\Flyron\Console\Commands\FlyronExec;
use JobMetric\Flyron\Console\Commands\FlyronOptimize;
use JobMetric\Flyron\Console\Commands\FlyronProcessKill;
use JobMetric\Flyron\Console\Commands\FlyronProcessList;
use JobMetric\PackageCore\PackageCore;
use JobMetric\PackageCore\PackageCoreServiceProvider;

class FlyronServiceProvider extends PackageCoreServiceProvider
{
    /**
     * @param PackageCore $package
     *
     * @return void
     */
    public function configuration(PackageCore $package): void
    {
        $package->name('flyron')
            ->hasConfig()
            ->registerCommand(FlyronProcessList::class)
            ->registerCommand(FlyronProcessKill::class)
            ->registerCommand(FlyronOptimize::class)
            ->registerCommand(FlyronExec::class);
    }
}
