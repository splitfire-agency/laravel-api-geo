<?php

namespace Splitfire\ApiGeo\Data;

final readonly class RateLimit
{
    public function __construct(
        public int $limit,
        public int $remaining,
        public int $reset,
    ) {}
}
