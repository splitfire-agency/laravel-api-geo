<?php

namespace Splitfire\ApiGeo\Query;

use Illuminate\Support\Arr;
use Splitfire\ApiGeo\Data\Coordinates;
use Splitfire\ApiGeo\Data\FeatureType;
use Splitfire\ApiGeo\Internal\Rules;

final readonly class SearchQuery
{
    private function __construct(
        public string $text,
        public ?int $limit = null,
        public ?bool $autocomplete = null,
        public ?FeatureType $type = null,
        public ?string $postcode = null,
        public ?string $citycode = null,
        public ?string $departmentCode = null,
        public ?string $regionCode = null,
        public ?Coordinates $near = null,
    ) {}

    public static function for(string $text): self
    {
        return new self(Rules::text($text));
    }

    public function limit(int $limit): self
    {
        return $this->with(['limit' => Rules::limit($limit)]);
    }

    public function autocomplete(bool $enabled = true): self
    {
        return $this->with(['autocomplete' => $enabled]);
    }

    public function type(FeatureType $type): self
    {
        return $this->with(['type' => $type]);
    }

    public function postcode(string $postcode): self
    {
        return $this->with(['postcode' => Rules::code('postcode', $postcode)]);
    }

    public function citycode(string $citycode): self
    {
        return $this->with(['citycode' => Rules::code('citycode', $citycode)]);
    }

    public function departmentCode(string $departmentCode): self
    {
        return $this->with(['departmentCode' => Rules::code('department_code', $departmentCode)]);
    }

    public function regionCode(string $regionCode): self
    {
        return $this->with(['regionCode' => Rules::code('region_code', $regionCode)]);
    }

    public function near(Coordinates $point): self
    {
        return $this->with(['near' => Rules::point($point, 3)]);
    }

    /**
     * @return array<string, string>
     */
    public function toParameters(): array
    {
        return Arr::whereNotNull([
            'q' => $this->text,
            'limit' => $this->limit === null ? null : (string) $this->limit,
            'autocomplete' => $this->autocomplete === null ? null : ($this->autocomplete ? 'true' : 'false'),
            'type' => $this->type?->value,
            'postcode' => $this->postcode,
            'citycode' => $this->citycode,
            'department_code' => $this->departmentCode,
            'region_code' => $this->regionCode,
            'lat' => $this->near === null ? null : Rules::decimal($this->near->lat),
            'lon' => $this->near === null ? null : Rules::decimal($this->near->lon),
        ]);
    }

    private function with(array $changes): self
    {
        return new self(...$changes + get_object_vars($this));
    }
}
