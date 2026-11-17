<?php

namespace Splitfire\ApiGeo\Exceptions;

use InvalidArgumentException;

final class InvalidQuery extends InvalidArgumentException implements ApiGeoException
{
    private function __construct(
        public readonly string $errorCode,
        public readonly string $parameter,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function missing(string $parameter): self
    {
        return new self('missing_parameter', $parameter, "The parameter {$parameter} is required.");
    }

    public static function invalid(string $parameter): self
    {
        return new self('invalid_parameter', $parameter, "The parameter {$parameter} has an invalid value.");
    }

    public static function tooShort(): self
    {
        return new self('query_too_short', 'q', 'The query must contain at least 3 characters.');
    }
}
