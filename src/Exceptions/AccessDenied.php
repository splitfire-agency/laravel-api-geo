<?php

namespace Splitfire\ApiGeo\Exceptions;

final class AccessDenied extends RequestFailed
{
    public static function missingKey(): self
    {
        return new self('No api-geo service key is configured (API_GEO_KEY).', 'missing_key');
    }
}
