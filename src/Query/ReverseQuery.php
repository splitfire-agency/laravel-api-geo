<?php

namespace Splitfire\ApiGeo\Query;

use Illuminate\Support\Arr;
use Splitfire\ApiGeo\Data\Coordinates;
use Splitfire\ApiGeo\Data\FeatureType;
use Splitfire\ApiGeo\Internal\Rules;

final readonly class ReverseQuery
{
    private function __construct(
        public Coordinates $point,
        public ?int $limit = null,
        public ?FeatureType $type = null,
    ) {}

    public static function at(Coordinates $point): self
    {
        return new self(Rules::point($point, 4));
    }

    public function limit(int $limit): self
    {
        return $this->with(['limit' => Rules::limit($limit)]);
    }

    public function type(FeatureType $type): self
    {
        return $this->with(['type' => $type]);
    }

    /**
     * @return array<string, string>
     */
    public function toParameters(): array
    {
        return Arr::whereNotNull([
            'lat' => Rules::decimal($this->point->lat),
            'lon' => Rules::decimal($this->point->lon),
            'type' => $this->type?->value,
            'limit' => $this->limit === null ? null : (string) $this->limit,
        ]);
    }

    private function with(array $changes): self
    {
        return new self(...$changes + get_object_vars($this));
    }
}
