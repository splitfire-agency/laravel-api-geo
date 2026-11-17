<?php

namespace Splitfire\ApiGeo\Exceptions;

use Splitfire\ApiGeo\Data\ResponseMeta;
use Throwable;

final class ServiceUnavailable extends RequestFailed
{
    public static function unreachable(Throwable $previous, ?string $requestId): self
    {
        return new self('api-geo is unreachable: '.$previous->getMessage(), meta: new ResponseMeta(requestId: $requestId), previous: $previous);
    }

    public function isTransient(): bool
    {
        return $this->errorCode !== 'service_disabled';
    }
}
