<?php

namespace Splitfire\ApiGeo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Splitfire\ApiGeo\Data\Coordinates;
use Splitfire\ApiGeo\Data\FeatureType;
use Splitfire\ApiGeo\Exceptions\InvalidQuery;
use Splitfire\ApiGeo\Query\ReverseQuery;

final class ReverseQueryTest extends TestCase
{
    public function test_point_is_truncated_toward_zero_to_four_decimals(): void
    {
        $query = ReverseQuery::at(new Coordinates(lat: 49.897443, lon: -2.290084));

        self::assertSame(49.8974, $query->point->lat);
        self::assertSame(-2.29, $query->point->lon);
        self::assertSame(['lat' => '49.8974', 'lon' => '-2.29'], $query->toParameters());
    }

    /**
     * @dataProvider bounds
     */
    #[DataProvider('bounds')]
    public function test_bounds_apply_after_truncation(float $lat, float $lon, ?string $invalid): void
    {
        if ($invalid !== null) {
            $this->expectExceptionObject(InvalidQuery::invalid($invalid));
        }

        self::assertNotNull(ReverseQuery::at(new Coordinates($lat, $lon))->point);
    }

    public static function bounds(): array
    {
        return [
            'latitude -90.0001' => [-90.0001, 0, 'lat'],
            'latitude -90.00009' => [-90.00009, 0, null],
            'longitude 180.0001' => [0, 180.0001, 'lon'],
            'longitude -180.00009' => [0, -180.00009, null],
            'latitude not a number' => [NAN, 0, 'lat'],
        ];
    }

    public function test_formatting_does_not_depend_on_serialize_precision(): void
    {
        $previous = ini_set('serialize_precision', '17');

        try {
            $query = ReverseQuery::at(new Coordinates(lat: 49.8974, lon: 49.9));

            self::assertSame(['lat' => '49.8974', 'lon' => '49.9'], $query->toParameters());
        } finally {
            ini_set('serialize_precision', (string) $previous);
        }
    }

    public function test_type_and_limit_are_optional(): void
    {
        $query = ReverseQuery::at(new Coordinates(lat: 48.8566, lon: 2.3522))->type(FeatureType::Street)->limit(2);

        self::assertSame(['lat' => '48.8566', 'lon' => '2.3522', 'type' => 'street', 'limit' => '2'], $query->toParameters());
    }

    public function test_limit_is_between_1_and_20(): void
    {
        $this->expectException(InvalidQuery::class);

        ReverseQuery::at(new Coordinates(lat: 48.8566, lon: 2.3522))->limit(0);
    }
}
