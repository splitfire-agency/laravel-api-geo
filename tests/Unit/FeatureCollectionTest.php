<?php

namespace Splitfire\ApiGeo\Tests\Unit;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Splitfire\ApiGeo\Data\FeatureCollection;
use Splitfire\ApiGeo\Data\FeatureType;
use Splitfire\ApiGeo\Tests\TestCase;
use UnexpectedValueException;

final class FeatureCollectionTest extends TestCase
{
    /**
     * @dataProvider fixtures
     */
    #[DataProvider('fixtures')]
    public function test_fixtures_match_the_contract(string $name): void
    {
        self::assertMatchesContract(self::fixture($name));
    }

    /**
     * @dataProvider fixtures
     */
    #[DataProvider('fixtures')]
    public function test_serialization_reproduces_the_contract(string $name): void
    {
        $json = json_encode(FeatureCollection::fromArray(self::fixtureArray($name)), JSON_THROW_ON_ERROR);

        self::assertMatchesContract($json);
        self::assertEquals(self::fixtureArray($name), json_decode($json, true));
    }

    public static function fixtures(): array
    {
        return ['search' => ['search.json'], 'reverse' => ['reverse.json'], 'empty' => ['empty.json']];
    }

    public function test_features_are_mapped(): void
    {
        $collection = FeatureCollection::fromArray(self::fixtureArray('search.json'));
        [$address, $street, $municipality] = $collection->features;

        self::assertSame('Base Adresse Nationale, Licence Ouverte / Open Licence version 2.0', $collection->attribution);
        self::assertCount(3, $collection);
        self::assertSame($address, $collection->first());

        self::assertSame('ban:80021_6590_00008', $address->id);
        self::assertSame(FeatureType::Address, $address->type);
        self::assertSame(49.897443, $address->coordinates->lat);
        self::assertSame(2.290084, $address->coordinates->lon);
        self::assertSame('8 Boulevard du Port 80000 Amiens', $address->label);
        self::assertSame('8', $address->housenumber);
        self::assertSame('80', $address->departmentCode);
        self::assertSame('32', $address->regionCode);
        self::assertSame(0.9573, $address->score);
        self::assertNull($address->distance);
        self::assertNull($address->population);

        self::assertSame(FeatureType::Street, $street->type);
        self::assertNull($street->housenumber);
        self::assertNull($street->importance);

        self::assertSame('975', $municipality->departmentCode);
        self::assertNull($municipality->regionCode);
        self::assertNull($municipality->region);
        self::assertSame(5394, $municipality->population);
    }

    public function test_unknown_feature_types_are_skipped(): void
    {
        $data = self::fixtureArray('search.json');
        $data['features'][0]['properties']['type'] = 'poi';

        $collection = FeatureCollection::fromArray($data);

        self::assertCount(2, $collection);
        self::assertSame('ban:80721_0270', $collection->first()?->id);
    }

    public function test_empty_collection(): void
    {
        $collection = FeatureCollection::fromArray(self::fixtureArray('empty.json'));

        self::assertTrue($collection->isEmpty());
        self::assertNull($collection->first());
    }

    /**
     * @dataProvider malformed
     */
    #[DataProvider('malformed')]
    public function test_malformed_payloads_are_refused(callable $alter): void
    {
        $data = self::fixtureArray('search.json');
        $alter($data);

        $this->expectException(UnexpectedValueException::class);

        FeatureCollection::fromArray($data);
    }

    public static function malformed(): array
    {
        return [
            'not a feature collection' => [static function (array &$data) {
                $data['type'] = 'Feature';
            }],
            'features missing' => [static function (array &$data) {
                unset($data['features']);
            }],
            'attribution missing' => [static function (array &$data) {
                unset($data['attribution']);
            }],
            'feature without properties' => [static function (array &$data) {
                unset($data['features'][0]['properties']);
            }],
            'label missing' => [static function (array &$data) {
                unset($data['features'][0]['properties']['label']);
            }],
            'score as a string' => [static function (array &$data) {
                $data['features'][0]['properties']['score'] = '0.9';
            }],
            'single coordinate' => [static function (array &$data) {
                $data['features'][0]['geometry']['coordinates'] = [2.29];
            }],
            'population as a float' => [static function (array &$data) {
                $data['features'][2]['properties']['population'] = 5394.5;
            }],
        ];
    }

    private static function fixtureArray(string $name): array
    {
        return json_decode(self::fixture($name), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function assertMatchesContract(string $json): void
    {
        $schema = json_decode(self::fixture('geo-v1.schema.json'), false, 512, JSON_THROW_ON_ERROR);
        $result = (new Validator)->validate(json_decode($json, false, 512, JSON_THROW_ON_ERROR), $schema);

        self::assertTrue($result->isValid(), $result->isValid() ? '' : json_encode((new ErrorFormatter)->format($result->error()), JSON_PRETTY_PRINT));
    }
}
