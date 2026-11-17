<?php

namespace Splitfire\ApiGeo\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Splitfire\ApiGeo\Internal\Payload;

/**
 * @implements IteratorAggregate<int, Feature>
 */
final readonly class FeatureCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param  list<Feature>  $features
     */
    public function __construct(
        public string $attribution,
        public array $features,
        public ResponseMeta $meta = new ResponseMeta,
    ) {}

    public static function fromArray(array $data, ResponseMeta $meta = new ResponseMeta): self
    {
        if (($data['type'] ?? null) !== 'FeatureCollection') {
            throw Payload::invalid('type');
        }

        $features = [];
        foreach (Payload::list($data, 'features') as $feature) {
            if (! is_array($feature)) {
                throw Payload::invalid('features');
            }
            $feature = Feature::fromArray($feature);
            if ($feature !== null) {
                $features[] = $feature;
            }
        }

        return new self(Payload::string($data, 'attribution'), $features, $meta);
    }

    public function first(): ?Feature
    {
        return $this->features[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->features === [];
    }

    public function count(): int
    {
        return count($this->features);
    }

    /**
     * @return ArrayIterator<int, Feature>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->features);
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'FeatureCollection',
            'attribution' => $this->attribution,
            'features' => $this->features,
        ];
    }
}
