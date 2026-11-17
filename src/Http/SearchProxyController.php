<?php

namespace Splitfire\ApiGeo\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Splitfire\ApiGeo\Contracts\Geocoder;
use Splitfire\ApiGeo\Exceptions\InvalidQuery;
use Splitfire\ApiGeo\Exceptions\QuotaExceeded;
use Splitfire\ApiGeo\Exceptions\RequestFailed;
use Splitfire\ApiGeo\Exceptions\RequestRejected;
use Splitfire\ApiGeo\Query\SearchQuery;

final class SearchProxyController
{
    private const MESSAGES = [
        'missing_parameter' => 'A required parameter is missing.',
        'invalid_parameter' => 'A parameter has an invalid value.',
        'query_too_short' => 'The query must contain at least 3 characters.',
        'service_unavailable' => 'The geocoding service is temporarily unavailable.',
    ];

    private const JSON_OPTIONS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(
        private readonly Geocoder $geocoder,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $results = $this->geocoder->search(SearchQuery::fromParameters($request->query()));
        } catch (InvalidQuery $e) {
            return self::error($e->errorCode, $e->parameter, 400);
        } catch (RequestRejected $e) {
            return self::error($e->errorCode ?? 'invalid_parameter', $e->parameter, 400);
        } catch (RequestFailed $e) {
            if (! $e instanceof QuotaExceeded) {
                report($e);
            }

            return self::error('service_unavailable', null, 503, $e->retryAfter);
        }

        return new JsonResponse($results, 200, [], self::JSON_OPTIONS);
    }

    private static function error(string $code, ?string $parameter, int $status, ?int $retryAfter = null): JsonResponse
    {
        $code = isset(self::MESSAGES[$code]) ? $code : 'invalid_parameter';

        return new JsonResponse(
            ['error' => Arr::whereNotNull(['code' => $code, 'message' => self::MESSAGES[$code], 'parameter' => $parameter])],
            $status,
            $retryAfter === null ? [] : ['Retry-After' => (string) $retryAfter],
            self::JSON_OPTIONS,
        );
    }
}
