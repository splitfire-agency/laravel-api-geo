<?php

namespace Splitfire\ApiGeo\Tests\Feature;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Splitfire\ApiGeo\Contracts\Geocoder;
use Splitfire\ApiGeo\Data\Coordinates;
use Splitfire\ApiGeo\Data\FeatureCollection;
use Splitfire\ApiGeo\Exceptions\AccessDenied;
use Splitfire\ApiGeo\Exceptions\InvalidQuery;
use Splitfire\ApiGeo\Exceptions\QuotaExceeded;
use Splitfire\ApiGeo\Exceptions\RequestFailed;
use Splitfire\ApiGeo\Exceptions\RequestRejected;
use Splitfire\ApiGeo\Exceptions\ServiceUnavailable;
use Splitfire\ApiGeo\Exceptions\UnexpectedResponse;
use Splitfire\ApiGeo\GeoClient;
use Splitfire\ApiGeo\Query\ReverseQuery;
use Splitfire\ApiGeo\Query\SearchQuery;
use Splitfire\ApiGeo\Tests\TestCase;

final class GeoClientTest extends TestCase
{
    public function test_search_sends_an_authenticated_get_through_the_gateway(): void
    {
        Http::fake(['*' => Http::response(self::fixture('search.json'))]);

        $this->geocoder()->search(SearchQuery::for('8 bd du port')->limit(3));

        Http::assertSent(static fn (Request $request) => $request->method() === 'GET'
            && strtok($request->url(), '?') === 'https://api.services.splitfire.fr/geo/search'
            && self::sentQuery($request) === ['q' => '8 bd du port', 'limit' => '3']
            && $request->header('Authorization') === ['Bearer '.self::KEY]
            && $request->header('Accept') === ['application/json']
            && ! $request->hasHeader('X-Request-Id'));
    }

    public function test_search_returns_the_features_and_response_metadata(): void
    {
        Http::fake(['*' => Http::response(self::fixture('search.json'), 200, [
            'x-request-id' => 'req-42',
            'X-Cache' => 'HIT',
            'RateLimit-Limit' => '600',
            'RateLimit-Remaining' => '599',
            'RateLimit-Reset' => '37',
        ])]);

        $results = $this->geocoder()->search('8 bd du port');

        self::assertCount(3, $results);
        self::assertSame('8 Boulevard du Port 80000 Amiens', $results->first()?->label);
        self::assertSame('req-42', $results->meta->requestId);
        self::assertSame('HIT', $results->meta->cacheStatus);
        self::assertSame(600, $results->meta->rateLimit?->limit);
        self::assertSame(599, $results->meta->rateLimit?->remaining);
        self::assertSame(37, $results->meta->rateLimit?->reset);
    }

    public function test_metadata_is_null_when_headers_are_absent(): void
    {
        Http::fake(['*' => Http::response(self::fixture('empty.json'))]);

        $results = $this->geocoder()->search('nowhere');

        self::assertTrue($results->isEmpty());
        self::assertNull($results->meta->requestId);
        self::assertNull($results->meta->cacheStatus);
        self::assertNull($results->meta->rateLimit);
    }

    public function test_reverse_sends_the_truncated_point(): void
    {
        Http::fake(['*' => Http::response(self::fixture('reverse.json'))]);

        $results = $this->geocoder()->reverse(ReverseQuery::at(new Coordinates(lat: 49.897443, lon: 2.290084))->limit(1));

        self::assertSame(6.2, $results->first()?->distance);
        Http::assertSent(static fn (Request $request) => strtok($request->url(), '?') === 'https://api.services.splitfire.fr/geo/reverse'
            && self::sentQuery($request) === ['lat' => '49.8974', 'lon' => '2.29', 'limit' => '1']);
    }

    public function test_invalid_query_is_refused_before_any_request(): void
    {
        Http::fake();

        $this->expectException(InvalidQuery::class);

        try {
            $this->geocoder()->search('ab');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_missing_key_is_refused_before_any_request(): void
    {
        Http::fake();

        $e = self::failure(fn () => $this->client(['key' => ''])->search('paris'));

        self::assertInstanceOf(AccessDenied::class, $e);
        self::assertSame('missing_key', $e->errorCode);
        Http::assertNothingSent();
    }

    public function test_request_id_is_propagated_on_a_copy(): void
    {
        Http::fake(['*' => Http::response(self::fixture('empty.json'))]);
        $geocoder = $this->geocoder();

        $geocoder->withRequestId('trace-123')->search('paris');
        $geocoder->search('lyon');

        $sent = Http::recorded();
        self::assertSame(['trace-123'], $sent[0][0]->header('X-Request-Id'));
        self::assertFalse($sent[1][0]->hasHeader('X-Request-Id'));
    }

    /**
     * @dataProvider invalidRequestIds
     */
    #[DataProvider('invalidRequestIds')]
    public function test_invalid_request_id_is_not_sent(string $requestId): void
    {
        Http::fake(['*' => Http::response(self::fixture('empty.json'))]);

        $this->geocoder()->withRequestId($requestId)->search('paris');

        Http::assertSent(static fn (Request $request) => ! $request->hasHeader('X-Request-Id'));
    }

    public static function invalidRequestIds(): array
    {
        return [
            'empty' => [''],
            'space' => ['bad id'],
            'trailing newline' => ["abc\n"],
            'too long' => [str_repeat('a', 129)],
        ];
    }

    /**
     * @dataProvider invalidConfigs
     */
    #[DataProvider('invalidConfigs')]
    public function test_invalid_configuration_is_refused(array $overrides, string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($name);

        $this->client($overrides);
    }

    public static function invalidConfigs(): array
    {
        return [
            'empty base url' => [['base_url' => ''], 'base_url'],
            'empty timeout' => [['timeout' => ''], 'timeout'],
            'zero timeout' => [['timeout' => 0], 'timeout'],
            'timeout under a millisecond' => [['timeout' => 0.0005], 'timeout'],
            'infinite timeout' => [['timeout' => '1e309'], 'timeout'],
            'negative connect timeout' => [['connect_timeout' => '-1'], 'connect_timeout'],
            'negative retries' => [['retry_times' => -1], 'retry_times'],
            'empty retry sleep' => [['retry_sleep_ms' => ''], 'retry_sleep_ms'],
        ];
    }

    /**
     * @dataProvider contractErrors
     */
    #[DataProvider('contractErrors')]
    public function test_contract_errors_are_mapped_to_exceptions(int $status, string $code, string $class): void
    {
        Http::fake(['*' => self::errorResponse($code, $status, ['X-Request-Id' => 'req-1'])]);

        $e = self::failure(fn () => $this->geocoder()->search('paris'));

        self::assertInstanceOf($class, $e);
        self::assertSame($code, $e->errorCode);
        self::assertSame($status, $e->status);
        self::assertSame('req-1', $e->meta->requestId);
        self::assertSame('Fixed message.', $e->getMessage());
    }

    public static function contractErrors(): array
    {
        return [
            'gateway missing_key' => [401, 'missing_key', AccessDenied::class],
            'gateway invalid_key' => [401, 'invalid_key', AccessDenied::class],
            'gateway revoked_key' => [401, 'revoked_key', AccessDenied::class],
            'gateway expired_key' => [401, 'expired_key', AccessDenied::class],
            'gateway project_suspended' => [403, 'project_suspended', AccessDenied::class],
            'gateway insufficient_scope' => [403, 'insufficient_scope', AccessDenied::class],
            'gateway origin_not_allowed' => [403, 'origin_not_allowed', AccessDenied::class],
            'gateway ip_not_allowed' => [403, 'ip_not_allowed', AccessDenied::class],
            'gateway route_not_found' => [404, 'route_not_found', UnexpectedResponse::class],
            'gateway rate_limit_minute' => [429, 'rate_limit_minute', QuotaExceeded::class],
            'gateway rate_limit_day' => [429, 'rate_limit_day', QuotaExceeded::class],
            'gateway hard_cap_reached' => [402, 'hard_cap_reached', QuotaExceeded::class],
            'gateway service_disabled' => [503, 'service_disabled', ServiceUnavailable::class],
            'gateway gateway_unavailable' => [503, 'gateway_unavailable', ServiceUnavailable::class],
            'gateway upstream_unavailable 502' => [502, 'upstream_unavailable', ServiceUnavailable::class],
            'gateway upstream_unavailable 504' => [504, 'upstream_unavailable', ServiceUnavailable::class],
            'geo missing_parameter' => [400, 'missing_parameter', RequestRejected::class],
            'geo invalid_parameter' => [400, 'invalid_parameter', RequestRejected::class],
            'geo query_too_short' => [400, 'query_too_short', RequestRejected::class],
            'geo engine_unavailable 502' => [502, 'engine_unavailable', ServiceUnavailable::class],
            'geo engine_unavailable 504' => [504, 'engine_unavailable', ServiceUnavailable::class],
            'geo internal_error' => [500, 'internal_error', ServiceUnavailable::class],
            'geo service_unavailable' => [503, 'service_unavailable', ServiceUnavailable::class],
            'geo forbidden' => [403, 'forbidden', AccessDenied::class],
            'geo route_not_found' => [404, 'route_not_found', UnexpectedResponse::class],
        ];
    }

    /**
     * @dataProvider unknownErrors
     */
    #[DataProvider('unknownErrors')]
    public function test_unknown_errors_are_mapped_by_status(int $status, string $body, string $class): void
    {
        Http::fake(['*' => Http::response($body, $status)]);

        $e = self::failure(fn () => $this->geocoder()->search('paris'));

        self::assertInstanceOf($class, $e);
        self::assertSame($status, $e->status);
    }

    public static function unknownErrors(): array
    {
        $unknown = '{"error":{"code":"brand_new","message":"New."}}';

        return [
            'unknown code 400' => [400, $unknown, RequestRejected::class],
            'unknown code 401' => [401, $unknown, AccessDenied::class],
            'unknown code 429' => [429, $unknown, QuotaExceeded::class],
            'unknown code 503' => [503, $unknown, ServiceUnavailable::class],
            'unknown code 418' => [418, $unknown, UnexpectedResponse::class],
            'unknown code 404' => [404, $unknown, UnexpectedResponse::class],
            'intermediary 404 page' => [404, '404 page not found', ServiceUnavailable::class],
            'intermediary 502 page' => [502, '<html>Bad Gateway</html>', ServiceUnavailable::class],
        ];
    }

    public function test_rejected_parameter_is_exposed(): void
    {
        Http::fake(['*' => self::errorResponse('invalid_parameter', 400, [], 'postcode')]);

        $e = self::failure(fn () => $this->geocoder()->search('paris'));

        self::assertInstanceOf(RequestRejected::class, $e);
        self::assertSame('postcode', $e->parameter);
    }

    public function test_quota_errors_carry_retry_after_and_rate_limit(): void
    {
        Http::fake(['*' => self::errorResponse('rate_limit_minute', 429, [
            'Retry-After' => '12',
            'RateLimit-Limit' => '600',
            'RateLimit-Remaining' => '0',
            'RateLimit-Reset' => '12',
        ])]);

        $e = self::failure(fn () => $this->geocoder()->search('paris'));

        self::assertInstanceOf(QuotaExceeded::class, $e);
        self::assertSame(12, $e->retryAfter);
        self::assertSame(0, $e->meta->rateLimit?->remaining);
        Http::assertSentCount(1);
    }

    public function test_monthly_cap_retry_after_falls_back_to_the_rate_limit_reset(): void
    {
        Http::fake(['*' => self::errorResponse('hard_cap_reached', 402, [
            'RateLimit-Limit' => '100000',
            'RateLimit-Remaining' => '0',
            'RateLimit-Reset' => '864000',
        ])]);

        $e = self::failure(fn () => $this->geocoder()->search('paris'));

        self::assertInstanceOf(QuotaExceeded::class, $e);
        self::assertSame(864000, $e->retryAfter);
    }

    /**
     * @dataProvider invalidBodies
     */
    #[DataProvider('invalidBodies')]
    public function test_success_outside_the_contract_is_unexpected(string $body): void
    {
        Http::fake(['*' => Http::response($body)]);

        $e = self::failure(fn () => $this->geocoder()->search('paris'));

        self::assertInstanceOf(UnexpectedResponse::class, $e);
        self::assertSame(200, $e->status);
        Http::assertSentCount(1);
    }

    public static function invalidBodies(): array
    {
        return [
            'not json' => ['<html></html>'],
            'json scalar' => ['"ok"'],
            'no features' => ['{"type":"FeatureCollection","attribution":"BAN"}'],
        ];
    }

    public function test_transient_failure_is_retried_once(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['error' => ['code' => 'gateway_unavailable', 'message' => 'Down.']], 503)
            ->push(self::fixture('search.json'))]);

        $results = $this->geocoder()->search('paris');

        self::assertCount(3, $results);
        Http::assertSentCount(2);
    }

    public function test_retries_are_bounded(): void
    {
        Http::fake(['*' => self::errorResponse('engine_unavailable', 502)]);

        self::assertInstanceOf(ServiceUnavailable::class, self::failure(fn () => $this->geocoder()->search('paris')));
        Http::assertSentCount(2);
    }

    public function test_retries_can_be_disabled(): void
    {
        Http::fake(['*' => self::errorResponse('engine_unavailable', 502)]);

        self::failure(fn () => $this->client(['retry_times' => 0])->search('paris'));

        Http::assertSentCount(1);
    }

    /**
     * @dataProvider notRetried
     */
    #[DataProvider('notRetried')]
    public function test_non_transient_failures_are_not_retried(int $status, string $code): void
    {
        Http::fake(['*' => self::errorResponse($code, $status)]);

        self::failure(fn () => $this->geocoder()->search('paris'));

        Http::assertSentCount(1);
    }

    public static function notRetried(): array
    {
        return [
            'service disabled' => [503, 'service_disabled'],
            'rejected' => [400, 'invalid_parameter'],
            'access denied' => [401, 'invalid_key'],
            'quota' => [429, 'rate_limit_day'],
        ];
    }

    public function test_connection_failure_is_unavailable_and_retried(): void
    {
        $attempts = 0;
        Http::fake(static function () use (&$attempts) {
            $attempts++;

            throw new ConnectException('Connection refused', new PsrRequest('GET', 'https://api.services.splitfire.fr/geo/search'));
        });

        $e = self::failure(fn () => $this->geocoder()->search('paris'));

        self::assertInstanceOf(ServiceUnavailable::class, $e);
        self::assertNull($e->errorCode);
        self::assertNull($e->status);
        self::assertSame(2, $attempts);
    }

    public function test_unreachable_message_does_not_carry_the_searched_address(): void
    {
        $url = 'https://api.services.splitfire.fr/geo/search?q=12%20rue%20des%20lilas';
        Http::fake(static function () use ($url) {
            throw new ConnectException(
                "cURL error 7: Failed to connect to api.services.splitfire.fr port 443: Connection refused for {$url}",
                new PsrRequest('GET', $url),
                null,
                ['errno' => 7, 'error' => 'Failed to connect to api.services.splitfire.fr port 443: Connection refused', 'url' => $url],
            );
        });

        $e = self::failure(fn () => $this->geocoder()->search('12 rue des lilas'));

        self::assertSame('api-geo is unreachable: cURL error 7: Failed to connect to api.services.splitfire.fr port 443: Connection refused.', $e->getMessage());
        self::assertNull($e->getPrevious());
    }

    public function test_transfer_failure_with_a_partial_response_is_unavailable(): void
    {
        Http::fake(static function () {
            throw new GuzzleRequestException('cURL error 18: transfer closed', new PsrRequest('GET', 'https://api.services.splitfire.fr/geo/search'), new PsrResponse(502));
        });

        $e = self::failure(fn () => $this->geocoder()->search('paris'));

        self::assertInstanceOf(ServiceUnavailable::class, $e);
        self::assertNull($e->errorCode);
        self::assertSame(502, $e->status);
    }

    public function test_transfer_failure_after_an_error_status_keeps_its_meaning(): void
    {
        $attempts = 0;
        Http::fake(static function () use (&$attempts) {
            $attempts++;

            throw new GuzzleRequestException('cURL error 18: transfer closed', new PsrRequest('GET', 'https://api.services.splitfire.fr/geo/search'), new PsrResponse(429, ['Retry-After' => '30']));
        });

        $e = self::failure(fn () => $this->geocoder()->search('paris'));

        self::assertInstanceOf(QuotaExceeded::class, $e);
        self::assertSame(429, $e->status);
        self::assertSame(30, $e->retryAfter);
        self::assertSame(1, $attempts);
    }

    public function test_transfer_failure_after_a_success_status_is_unavailable(): void
    {
        Http::fake(static function () {
            throw new GuzzleRequestException('cURL error 18: transfer closed', new PsrRequest('GET', 'https://api.services.splitfire.fr/geo/search'), new PsrResponse(200));
        });

        $e = self::failure(fn () => $this->geocoder()->search('paris'));

        self::assertInstanceOf(ServiceUnavailable::class, $e);
        self::assertNull($e->status);
    }

    public function test_base_url_is_configurable(): void
    {
        Http::fake(['*' => Http::response(self::fixture('empty.json'))]);

        $this->client(['base_url' => 'https://api.api-gateway.dev.localhost/geo/'])->search('paris');

        Http::assertSent(static fn (Request $request) => strtok($request->url(), '?') === 'https://api.api-gateway.dev.localhost/geo/search');
    }

    public function test_results_can_be_cached_as_json(): void
    {
        Http::fake(['*' => Http::response(self::fixture('search.json'))]);
        $results = $this->geocoder()->search('8 bd du port');

        $restored = FeatureCollection::fromArray(json_decode((string) json_encode($results), true));

        self::assertSame($results->attribution, $restored->attribution);
        self::assertEquals($results->features, $restored->features);
    }

    private function geocoder(): Geocoder
    {
        return $this->app->make(Geocoder::class);
    }

    private function client(array $overrides): GeoClient
    {
        return GeoClient::fromConfig($overrides + config('api-geo'));
    }

    private static function failure(callable $call): RequestFailed
    {
        try {
            $call();
        } catch (RequestFailed $e) {
            return $e;
        }
        self::fail('Expected a RequestFailed exception.');
    }
}
