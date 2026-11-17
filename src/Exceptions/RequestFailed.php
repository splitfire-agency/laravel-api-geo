<?php

namespace Splitfire\ApiGeo\Exceptions;

use RuntimeException;
use Splitfire\ApiGeo\Data\ResponseMeta;
use Throwable;

abstract class RequestFailed extends RuntimeException implements ApiGeoException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?int $status = null,
        public readonly ?string $parameter = null,
        public readonly ResponseMeta $meta = new ResponseMeta,
        public readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
