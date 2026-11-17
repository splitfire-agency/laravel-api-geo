<?php

namespace Splitfire\ApiGeo\Internal;

use Splitfire\ApiGeo\Data\Coordinates;
use Splitfire\ApiGeo\Exceptions\InvalidQuery;

/**
 * Mirrors the parameter checks of apps/geo/internal/geocode/params.go in api-geo.
 *
 * @internal
 */
final class Rules
{
    private const PATTERNS = [
        'postcode' => '/^[0-9]{5}$/D',
        'citycode' => '/^([0-9]{5}|2[AB][0-9]{3})$/D',
        'department_code' => '/^(0[1-9]|1[0-9]|2[1-9AB]|[3-8][0-9]|9[0-5]|97[1-8])$/D',
        'region_code' => '/^(01|02|03|04|06|11|24|27|28|32|44|52|53|75|76|84|93|94)$/D',
    ];

    private const BOUNDS = ['lat' => 90.0, 'lon' => 180.0];

    public static function text(string $raw): string
    {
        if (! mb_check_encoding($raw, 'UTF-8') || preg_match('/\p{Cc}/u', $raw) === 1) {
            throw InvalidQuery::invalid('q');
        }

        $text = (string) preg_replace('/^\s+|\s+$/Du', '', $raw);
        $length = mb_strlen($text, 'UTF-8');
        if ($length < 3) {
            throw InvalidQuery::tooShort();
        }
        if ($length > 200) {
            throw InvalidQuery::invalid('q');
        }

        return $text;
    }

    public static function limit(int $limit): int
    {
        if ($limit < 1 || $limit > 20) {
            throw InvalidQuery::invalid('limit');
        }

        return $limit;
    }

    public static function code(string $parameter, string $value): string
    {
        if (preg_match(self::PATTERNS[$parameter], $value) !== 1) {
            throw InvalidQuery::invalid($parameter);
        }

        return $value;
    }

    public static function point(Coordinates $point, int $decimals): Coordinates
    {
        return new Coordinates(self::coordinate('lat', $point->lat, $decimals), self::coordinate('lon', $point->lon, $decimals));
    }

    public static function coordinate(string $parameter, float|string $value, int $decimals): float
    {
        $raw = is_string($value) ? self::parseDecimal($parameter, $value) : self::formatFinite($parameter, $value);
        $dot = strpos($raw, '.');
        $truncated = (float) ($dot === false ? $raw : substr($raw, 0, $dot + 1 + $decimals));
        if (abs($truncated) > self::BOUNDS[$parameter]) {
            throw InvalidQuery::invalid($parameter);
        }

        return $truncated === 0.0 ? 0.0 : $truncated;
    }

    public static function decimal(float $value): string
    {
        if ($value === 0.0) {
            return '0';
        }

        for ($digits = 1; $digits <= 17; $digits++) {
            $repr = sprintf('%.'.$digits.'H', $value);
            if ((float) $repr === $value) {
                break;
            }
        }
        if (stripos($repr, 'e') !== false) {
            $repr = sprintf('%.20F', $value);
        }

        return str_contains($repr, '.') ? rtrim(rtrim($repr, '0'), '.') : $repr;
    }

    public static function integer(string $parameter, string $raw): int
    {
        if (preg_match('/^[+-]?[0-9]+$/D', $raw) !== 1) {
            throw InvalidQuery::invalid($parameter);
        }

        return (int) $raw;
    }

    public static function boolean(string $parameter, string $raw): bool
    {
        return match ($raw) {
            'true', '1' => true,
            'false', '0' => false,
            default => throw InvalidQuery::invalid($parameter),
        };
    }

    private static function parseDecimal(string $parameter, string $raw): string
    {
        if (preg_match('/^[+-]?[0-9]*\.?[0-9]*$/D', $raw) === 1 && preg_match('/[0-9]/', $raw) === 1) {
            return $raw;
        }
        if (preg_match('/^[+-]?([0-9]+\.?[0-9]*|\.[0-9]+)[eE][+-]?[0-9]+$/D', $raw) !== 1) {
            throw InvalidQuery::invalid($parameter);
        }

        return self::formatFinite($parameter, (float) $raw);
    }

    private static function formatFinite(string $parameter, float $value): string
    {
        if (! is_finite($value)) {
            throw InvalidQuery::invalid($parameter);
        }

        return self::decimal($value);
    }
}
