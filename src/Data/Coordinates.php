<?php

namespace Splitfire\ApiGeo\Data;

final readonly class Coordinates
{
    public function __construct(
        public float $lat,
        public float $lon,
    ) {}
}
