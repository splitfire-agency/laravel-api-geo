<?php

namespace Splitfire\ApiGeo\Data;

use Illuminate\Support\Arr;
use JsonSerializable;
use Splitfire\ApiGeo\Internal\Payload;

final readonly class Feature implements JsonSerializable
{
    public function __construct(
        /** Changes with each BAN release: do not store it. */
        public string $id,
        public FeatureType $type,
        public Coordinates $coordinates,
        public string $label,
        public string $name,
        public string $postcode,
        public string $city,
        public string $countryCode,
        public float $score,
        public string $source,
        public ?string $housenumber = null,
        public ?string $street = null,
        public ?string $citycode = null,
        public ?string $departmentCode = null,
        public ?string $department = null,
        public ?string $regionCode = null,
        public ?string $region = null,
        public ?float $distance = null,
        public ?int $population = null,
        public ?float $importance = null,
    ) {}

    public static function fromArray(array $data): ?self
    {
        $properties = Payload::object($data, 'properties');
        $type = FeatureType::tryFrom(Payload::string($properties, 'type'));
        // The contract may add types; clients must skip the ones they do not know.
        if ($type === null) {
            return null;
        }

        $point = Payload::list(Payload::object($data, 'geometry'), 'coordinates');
        if (count($point) !== 2) {
            throw Payload::invalid('coordinates');
        }

        return new self(
            id: Payload::string($properties, 'id'),
            type: $type,
            // GeoJSON order: [lon, lat].
            coordinates: new Coordinates(lat: Payload::number($point, 1), lon: Payload::number($point, 0)),
            label: Payload::string($properties, 'label'),
            name: Payload::string($properties, 'name'),
            postcode: Payload::string($properties, 'postcode'),
            city: Payload::string($properties, 'city'),
            countryCode: Payload::string($properties, 'country_code'),
            score: Payload::number($properties, 'score'),
            source: Payload::string($properties, 'source'),
            housenumber: Payload::optionalString($properties, 'housenumber'),
            street: Payload::optionalString($properties, 'street'),
            citycode: Payload::optionalString($properties, 'citycode'),
            departmentCode: Payload::optionalString($properties, 'department_code'),
            department: Payload::optionalString($properties, 'department'),
            regionCode: Payload::optionalString($properties, 'region_code'),
            region: Payload::optionalString($properties, 'region'),
            distance: Payload::optionalNumber($properties, 'distance'),
            population: Payload::optionalInteger($properties, 'population'),
            importance: Payload::optionalNumber($properties, 'importance'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$this->coordinates->lon, $this->coordinates->lat],
            ],
            'properties' => Arr::whereNotNull([
                'id' => $this->id,
                'type' => $this->type->value,
                'label' => $this->label,
                'name' => $this->name,
                'housenumber' => $this->housenumber,
                'street' => $this->street,
                'postcode' => $this->postcode,
                'city' => $this->city,
                'citycode' => $this->citycode,
                'department_code' => $this->departmentCode,
                'department' => $this->department,
                'region_code' => $this->regionCode,
                'region' => $this->region,
                'country_code' => $this->countryCode,
                'score' => $this->score,
                'distance' => $this->distance,
                'source' => $this->source,
                'population' => $this->population,
                'importance' => $this->importance,
            ]),
        ];
    }
}
