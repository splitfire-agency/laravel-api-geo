<?php

namespace Splitfire\ApiGeo\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Splitfire\ApiGeo\ApiGeoServiceProvider;

abstract class TestCase extends Orchestra
{
    protected const KEY = 'sfg_live_TestKey0_00000000000000000000000000000000';

    protected function getPackageProviders($app): array
    {
        return [ApiGeoServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('api-geo.key', self::KEY);
        $app['config']->set('api-geo.retry_sleep_ms', 0);
    }

    protected static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/Fixtures/'.$name);
    }
}
