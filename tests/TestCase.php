<?php

namespace Splitfire\ApiGeo\Tests;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
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

    protected static function fixtureArray(string $name): array
    {
        return json_decode(self::fixture($name), true, 512, JSON_THROW_ON_ERROR);
    }

    protected static function errorResponse(string $code, int $status, array $headers = [], ?string $parameter = null): PromiseInterface
    {
        return Http::response(['error' => Arr::whereNotNull(['code' => $code, 'message' => 'Fixed message.', 'parameter' => $parameter])], $status, $headers);
    }

    protected static function sentQuery(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query;
    }

    protected static function assertMatchesContract(string $json): void
    {
        $schema = json_decode(self::fixture('geo-v1.schema.json'), false, 512, JSON_THROW_ON_ERROR);
        $result = (new Validator)->validate(json_decode($json, false, 512, JSON_THROW_ON_ERROR), $schema);

        self::assertTrue($result->isValid(), $result->isValid() ? '' : json_encode((new ErrorFormatter)->format($result->error()), JSON_PRETTY_PRINT));
    }
}
