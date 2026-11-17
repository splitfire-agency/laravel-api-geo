<?php

namespace Splitfire\ApiGeo\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Splitfire\ApiGeo\ApiGeoServiceProvider;
use Splitfire\ApiGeo\Contracts\Geocoder;
use Splitfire\ApiGeo\Facades\ApiGeo;
use Splitfire\ApiGeo\GeoClient;
use Splitfire\ApiGeo\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function test_configuration_defaults_are_merged(): void
    {
        self::assertSame('https://api.services.splitfire.fr/geo', config('api-geo.base_url'));
        self::assertSame(8, config('api-geo.timeout'));
        self::assertFalse(config('api-geo.proxy_enabled'));
    }

    public function test_geocoder_is_a_shared_client(): void
    {
        $geocoder = $this->app->make(Geocoder::class);

        self::assertInstanceOf(GeoClient::class, $geocoder);
        self::assertSame($geocoder, $this->app->make(Geocoder::class));
        self::assertSame($geocoder, $this->app->make(GeoClient::class));
    }

    public function test_partial_application_config_keeps_defaults(): void
    {
        config(['api-geo' => ['key' => self::KEY, 'retry_times' => 3, 'proxy_enabled' => true, 'proxy_middleware' => []]]);

        (new ApiGeoServiceProvider($this->app))->register();

        self::assertSame(3, config('api-geo.retry_times'));
        self::assertSame(1000, config('api-geo.retry_sleep_ms'));
        self::assertSame('api-geo', config('api-geo.proxy_prefix'));
        self::assertSame(3, config('api-geo.proxy_timeout'));
        self::assertSame([], config('api-geo.proxy_middleware'));
        self::assertSame('https://api.services.splitfire.fr/geo', config('api-geo.base_url'));
    }

    public function test_applications_can_substitute_the_geocoder(): void
    {
        $fake = $this->createStub(Geocoder::class);
        $this->app->instance(Geocoder::class, $fake);

        self::assertSame($fake, $this->app->make(Geocoder::class));
        self::assertSame($fake, ApiGeo::getFacadeRoot());
    }

    public function test_facade_proxies_the_geocoder(): void
    {
        Http::fake(['*' => Http::response(self::fixture('search.json'))]);

        self::assertCount(3, ApiGeo::search('8 bd du port'));
    }

    public function test_proxy_is_disabled_by_default(): void
    {
        self::assertFalse(Route::has('api-geo.search'));
        $this->get('/api-geo/search?q=paris')->assertNotFound();
    }
}
