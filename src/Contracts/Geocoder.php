<?php

namespace Splitfire\ApiGeo\Contracts;

use Splitfire\ApiGeo\Data\FeatureCollection;
use Splitfire\ApiGeo\Query\ReverseQuery;
use Splitfire\ApiGeo\Query\SearchQuery;

interface Geocoder
{
    public function search(SearchQuery|string $query): FeatureCollection;

    public function reverse(ReverseQuery $query): FeatureCollection;

    public function withRequestId(?string $requestId): static;
}
