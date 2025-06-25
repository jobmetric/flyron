<?php

namespace JobMetric\Flyron;

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
            ->hasConfig();
    }
}
