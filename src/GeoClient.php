<?php

namespace Splitfire\ApiGeo;

use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Splitfire\ApiGeo\Contracts\Geocoder;
use Splitfire\ApiGeo\Data\FeatureCollection;
use Splitfire\ApiGeo\Exceptions\AccessDenied;
use Splitfire\ApiGeo\Exceptions\ServiceUnavailable;
use Splitfire\ApiGeo\Internal\ResponseReader;
use Splitfire\ApiGeo\Query\ReverseQuery;
use Splitfire\ApiGeo\Query\SearchQuery;
use Throwable;

final class GeoClient implements Geocoder
{
    public function __construct(
        private readonly ?string $key,
        private readonly string $baseUrl,
        private readonly float $timeout,
        private readonly float $connectTimeout,
        private readonly int $retries,
        private readonly int $retrySleepMs,
        private readonly ?string $requestId = null,
    ) {}

    public static function fromConfig(array $config): self
    {
        $key = $config['key'] ?? null;
        $baseUrl = $config['base_url'] ?? null;
        if (! is_string($baseUrl) || $baseUrl === '') {
            throw self::invalidConfig('base_url');
        }

        return new self(
            key: is_string($key) && $key !== '' ? $key : null,
            baseUrl: $baseUrl,
            timeout: self::seconds('timeout', $config['timeout'] ?? null),
            connectTimeout: self::seconds('connect_timeout', $config['connect_timeout'] ?? null),
            retries: self::count('retry_times', $config['retry_times'] ?? null),
            retrySleepMs: self::count('retry_sleep_ms', $config['retry_sleep_ms'] ?? null),
        );
    }

    public function withRequestId(?string $requestId): static
    {
        // The gateway replaces an X-Request-Id that is not 1 to 128 printable ASCII characters.
        if ($requestId !== null && preg_match('/^[!-~]{1,128}$/D', $requestId) !== 1) {
            throw new InvalidArgumentException('A request id is 1 to 128 printable ASCII characters.');
        }

        return new self(...['requestId' => $requestId] + get_object_vars($this));
    }

    public function search(SearchQuery|string $query): FeatureCollection
    {
        $query = is_string($query) ? SearchQuery::for($query) : $query;

        return $this->get('search', $query->toParameters());
    }

    public function reverse(ReverseQuery $query): FeatureCollection
    {
        return $this->get('reverse', $query->toParameters());
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function get(string $path, array $parameters): FeatureCollection
    {
        if ($this->key === null) {
            throw AccessDenied::missingKey();
        }

        return retry(
            $this->retries + 1,
            fn () => ResponseReader::read($this->send($path, $parameters)),
            $this->retrySleepMs,
            static fn (Throwable $e) => $e instanceof ServiceUnavailable && $e->isTransient(),
        );
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function send(string $path, array $parameters): Response
    {
        try {
            return Http::baseUrl($this->baseUrl)
                ->withToken((string) $this->key)
                ->acceptJson()
                ->withHeaders($this->requestId === null ? [] : ['X-Request-Id' => $this->requestId])
                // timeout() only takes integers and connectTimeout() is missing in Laravel 8.
                ->withOptions(['timeout' => $this->timeout, 'connect_timeout' => $this->connectTimeout])
                ->get($path, $parameters);
        } catch (ConnectionException|RequestException|TransferException $e) {
            // Depending on the Laravel version, a transfer error surfaces as a Guzzle exception or as a RequestException.
            throw ServiceUnavailable::unreachable($e, $this->requestId);
        }
    }

    private static function seconds(string $name, mixed $value): float
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            throw self::invalidConfig($name);
        }

        return (float) $value;
    }

    private static function count(string $name, mixed $value): int
    {
        $count = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($count === false) {
            throw self::invalidConfig($name);
        }

        return $count;
    }

    private static function invalidConfig(string $name): InvalidArgumentException
    {
        return new InvalidArgumentException("Invalid api-geo configuration value: {$name}.");
    }
}
