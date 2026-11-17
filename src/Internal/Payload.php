<?php

namespace Splitfire\ApiGeo\Internal;

use UnexpectedValueException;

/**
 * @internal
 */
final class Payload
{
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value)) {
            throw self::invalid($key);
        }

        return $value;
    }

    public static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw self::invalid($key);
        }

        return $value;
    }

    public static function number(array $data, string $key): float
    {
        $value = $data[$key] ?? null;
        if (! is_int($value) && ! is_float($value)) {
            throw self::invalid($key);
        }

        return (float) $value;
    }

    public static function optionalNumber(array $data, string $key): ?float
    {
        return ($data[$key] ?? null) === null ? null : self::number($data, $key);
    }

    public static function optionalInteger(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_int($value)) {
            throw self::invalid($key);
        }

        return $value;
    }

    public static function object(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw self::invalid($key);
        }

        return $value;
    }

    public static function list(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (! is_array($value) || ! array_is_list($value)) {
            throw self::invalid($key);
        }

        return $value;
    }

    public static function invalid(string $key): UnexpectedValueException
    {
        return new UnexpectedValueException("Missing or invalid property {$key}.");
    }
}
