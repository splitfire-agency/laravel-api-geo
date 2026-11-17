<?php

namespace Splitfire\ApiGeo\Data;

final readonly class ResponseMeta
{
    public function __construct(
        public ?string $requestId = null,
        public ?string $cacheStatus = null,
        public ?RateLimit $rateLimit = null,
    ) {}
}
