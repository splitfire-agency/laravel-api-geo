<?php

namespace Splitfire\ApiGeo\Data;

enum FeatureType: string
{
    case Address = 'address';
    case Street = 'street';
    case Locality = 'locality';
    case Municipality = 'municipality';
}
