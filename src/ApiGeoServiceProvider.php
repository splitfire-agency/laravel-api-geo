<?php

namespace Splitfire\ApiGeo;

use Illuminate\Support\ServiceProvider;
use Splitfire\ApiGeo\Contracts\Geocoder;

final class ApiGeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api-geo.php', 'api-geo');

        $this->app->singleton(GeoClient::class, static fn ($app) => GeoClient::fromConfig($app['config']->get('api-geo') ?? []));
        $this->app->alias(GeoClient::class, Geocoder::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/api-geo.php' => $this->app->configPath('api-geo.php')], 'api-geo-config');
        }
    }
}
