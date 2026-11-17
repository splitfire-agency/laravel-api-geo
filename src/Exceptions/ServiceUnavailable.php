<?php

namespace Splitfire\ApiGeo\Exceptions;

use Splitfire\ApiGeo\Data\ResponseMeta;

final class ServiceUnavailable extends RequestFailed
{
    public static function unreachable(?string $reason, ?string $requestId): self
    {
        return new self('api-geo is unreachable'.($reason === null ? '' : ": {$reason}").'.', meta: new ResponseMeta(requestId: $requestId));
    }

    public function isTransient(): bool
    {
        // service_disabled means the service is switched off in the gateway, not an outage.
        return $this->errorCode !== 'service_disabled';
    }
}
