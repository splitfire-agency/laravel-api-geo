<?php

namespace Splitfire\ApiGeo;

use Illuminate\Support\ServiceProvider;
use Splitfire\ApiGeo\Contracts\Geocoder;
use Splitfire\ApiGeo\Http\SearchProxyController;

final class ApiGeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api-geo.php', 'api-geo');

        $this->app->singleton(GeoClient::class, static fn ($app) => GeoClient::fromConfig($app['config']->get('api-geo')));
        $this->app->alias(GeoClient::class, Geocoder::class);

        $this->app->when(SearchProxyController::class)
            ->needs(Geocoder::class)
            ->give(static fn ($app) => GeoClient::fromConfig([
                'timeout' => $app['config']->get('api-geo.proxy_timeout'),
                'retry_times' => 0,
            ] + $app['config']->get('api-geo')));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/api-geo.php' => $this->app->configPath('api-geo.php')], 'api-geo-config');
        }

        if (filter_var($this->app['config']->get('api-geo.proxy_enabled'), FILTER_VALIDATE_BOOL)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api-geo.php');
        }
    }
}
