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
        // service_disabled means the service is switched off in the gateway, not an outage.
        return $this->errorCode !== 'service_disabled';
    }
}
