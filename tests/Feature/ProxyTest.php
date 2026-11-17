<?php

namespace Splitfire\ApiGeo\Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Splitfire\ApiGeo\Tests\RecordingHandler;
use Splitfire\ApiGeo\Tests\TestCase;

final class ProxyTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('api-geo.proxy_enabled', true);
        $app['config']->set('logging.default', 'null');
    }

    public function test_route_is_registered_with_its_middleware(): void
    {
        $route = Route::getRoutes()->getByName('api-geo.search');

        self::assertNotNull($route);
        self::assertSame('api-geo/search', $route->uri());
        self::assertContains('throttle:60,1', $route->gatherMiddleware());
    }

    public function test_search_returns_the_contract_and_forwards_known_parameters_only(): void
    {
        Http::fake(['*' => Http::response(self::fixture('search.json'), 200, ['X-Request-Id' => 'req-1'])]);

        $response = $this->get('/api-geo/search?q=8%20bd%20du%20port&limit=3&country=BE&key=leak');

        $response->assertOk();
        self::assertMatchesContract((string) $response->getContent());
        self::assertSame(self::fixtureArray('search.json'), $response->json());
        Http::assertSent(static fn (Request $request) => self::sentQuery($request) === ['q' => '8 bd du port', 'limit' => '3']
            && $request->header('Authorization') === ['Bearer '.self::KEY]);
    }

    public function test_invalid_query_is_answered_locally(): void
    {
        Http::fake();

        $this->get('/api-geo/search?q=ab')
            ->assertStatus(400)
            ->assertExactJson(['error' => [
                'code' => 'query_too_short',
                'message' => 'The query must contain at least 3 characters.',
                'parameter' => 'q',
            ]]);

        $this->get('/api-geo/search')
            ->assertStatus(400)
            ->assertExactJson(['error' => [
                'code' => 'missing_parameter',
                'message' => 'A required parameter is missing.',
                'parameter' => 'q',
            ]]);

        Http::assertNothingSent();
    }

    public function test_rejection_by_the_api_is_forwarded(): void
    {
        Http::fake(['*' => self::errorResponse('invalid_parameter', 400, [], 'postcode')]);

        $this->get('/api-geo/search?q=paris')
            ->assertStatus(400)
            ->assertExactJson(['error' => [
                'code' => 'invalid_parameter',
                'message' => 'A parameter has an invalid value.',
                'parameter' => 'postcode',
            ]]);
    }

    public function test_quota_errors_ask_the_browser_to_wait(): void
    {
        Http::fake(['*' => self::errorResponse('rate_limit_minute', 429, ['Retry-After' => '20'])]);
        $this->app->instance(ExceptionHandler::class, $handler = new RecordingHandler($this->app));

        $this->get('/api-geo/search?q=paris')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '20');

        self::assertSame([], $handler->reported);
    }

    public function test_proxy_does_not_retry(): void
    {
        Http::fake(['*' => self::errorResponse('engine_unavailable', 502)]);

        $this->get('/api-geo/search?q=paris')->assertStatus(503);

        Http::assertSentCount(1);
    }

    public function test_other_failures_hide_their_cause(): void
    {
        Http::fake(['*' => self::errorResponse('invalid_key', 401)]);
        $this->app->instance(ExceptionHandler::class, $handler = new RecordingHandler($this->app));

        $this->get('/api-geo/search?q=paris')
            ->assertStatus(503)
            ->assertExactJson(['error' => [
                'code' => 'service_unavailable',
                'message' => 'The geocoding service is temporarily unavailable.',
            ]]);

        self::assertCount(1, $handler->reported);
    }
}
