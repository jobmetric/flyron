<?php

namespace JobMetric\Flyron\Tests;

use JobMetric\Flyron\FlyronServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [FlyronServiceProvider::class,];
    }
}
