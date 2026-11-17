<?php

namespace Splitfire\ApiGeo\Internal;

use Illuminate\Http\Client\Response;
use Splitfire\ApiGeo\Data\FeatureCollection;
use Splitfire\ApiGeo\Data\RateLimit;
use Splitfire\ApiGeo\Data\ResponseMeta;
use Splitfire\ApiGeo\Exceptions\AccessDenied;
use Splitfire\ApiGeo\Exceptions\QuotaExceeded;
use Splitfire\ApiGeo\Exceptions\RequestFailed;
use Splitfire\ApiGeo\Exceptions\RequestRejected;
use Splitfire\ApiGeo\Exceptions\ServiceUnavailable;
use Splitfire\ApiGeo\Exceptions\UnexpectedResponse;
use UnexpectedValueException;

/**
 * @internal
 */
final class ResponseReader
{
    public static function read(Response $response): FeatureCollection
    {
        $meta = new ResponseMeta(
            requestId: self::header($response, 'X-Request-Id'),
            cacheStatus: self::header($response, 'X-Cache'),
            rateLimit: self::rateLimit($response),
        );
        $status = $response->status();
        $body = json_decode($response->body(), true);

        if ($status !== 200) {
            throw self::failure($response, $status, $body, $meta);
        }

        try {
            if (! is_array($body)) {
                throw new UnexpectedValueException('The body is not a JSON object.');
            }

            return FeatureCollection::fromArray($body, $meta);
        } catch (UnexpectedValueException $e) {
            throw new UnexpectedResponse('api-geo answered outside its contract: '.$e->getMessage(), status: $status, meta: $meta, previous: $e);
        }
    }

    private static function failure(Response $response, int $status, mixed $body, ResponseMeta $meta): RequestFailed
    {
        $error = is_array($body) && is_array($body['error'] ?? null) && is_string($body['error']['code'] ?? null) ? $body['error'] : null;

        $class = match (true) {
            $status === 400 => RequestRejected::class,
            $status === 401, $status === 403 => AccessDenied::class,
            $status === 402, $status === 429 => QuotaExceeded::class,
            // Traefik answers a bare 404 while the gateway container is down; a contract 404 carries an error body.
            $status >= 500, $status === 404 && $error === null => ServiceUnavailable::class,
            default => UnexpectedResponse::class,
        };

        return new $class(
            is_string($error['message'] ?? null) ? $error['message'] : "api-geo answered with HTTP {$status}.",
            $error['code'] ?? null,
            $status,
            is_string($error['parameter'] ?? null) ? $error['parameter'] : null,
            $meta,
            // The gateway sends Retry-After on a 429 only; on a 402, RateLimit-Reset describes the limit that refused.
            self::integer($response, 'Retry-After') ?? ($class === QuotaExceeded::class ? $meta->rateLimit?->reset : null),
        );
    }

    private static function rateLimit(Response $response): ?RateLimit
    {
        $limit = self::integer($response, 'RateLimit-Limit');
        $remaining = self::integer($response, 'RateLimit-Remaining');
        $reset = self::integer($response, 'RateLimit-Reset');

        return $limit === null || $remaining === null || $reset === null ? null : new RateLimit($limit, $remaining, $reset);
    }

    private static function integer(Response $response, string $name): ?int
    {
        $value = $response->header($name);

        return ctype_digit($value) ? (int) $value : null;
    }

    private static function header(Response $response, string $name): ?string
    {
        $value = $response->header($name);

        return $value === '' ? null : $value;
    }
}
