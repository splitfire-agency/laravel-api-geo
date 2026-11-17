<?php

namespace Splitfire\ApiGeo\Facades;

use Illuminate\Support\Facades\Facade;
use Splitfire\ApiGeo\Contracts\Geocoder;

/**
 * @method static \Splitfire\ApiGeo\Data\FeatureCollection search(\Splitfire\ApiGeo\Query\SearchQuery|string $query)
 * @method static \Splitfire\ApiGeo\Data\FeatureCollection reverse(\Splitfire\ApiGeo\Query\ReverseQuery $query)
 * @method static \Splitfire\ApiGeo\Contracts\Geocoder withRequestId(?string $requestId)
 *
 * @see Geocoder
 */
final class ApiGeo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Geocoder::class;
    }
}
