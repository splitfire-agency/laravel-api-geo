<?php

namespace Splitfire\ApiGeo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Splitfire\ApiGeo\Data\Coordinates;
use Splitfire\ApiGeo\Data\FeatureType;
use Splitfire\ApiGeo\Exceptions\InvalidQuery;
use Splitfire\ApiGeo\Query\SearchQuery;

final class SearchQueryTest extends TestCase
{
    public function test_text_is_trimmed_of_unicode_spaces(): void
    {
        self::assertSame('8 bd du port', SearchQuery::for("\u{00A0} 8 bd du port \u{3000}")->text);
    }

    /**
     * @dataProvider tooShortTexts
     */
    #[DataProvider('tooShortTexts')]
    public function test_text_shorter_than_two_characters_is_too_short(string $text): void
    {
        $e = self::invalid(static fn () => SearchQuery::for($text));

        self::assertSame('query_too_short', $e->errorCode);
        self::assertSame('q', $e->parameter);
    }

    public static function tooShortTexts(): array
    {
        return ['empty' => [''], 'spaces' => ['   '], 'one character' => ['a'], 'padded' => ['  a  '], 'one accented' => ['é']];
    }

    public function test_text_length_counts_characters_not_bytes(): void
    {
        self::assertSame('éé', SearchQuery::for('éé')->text);
        self::assertSame('Eu', SearchQuery::for('  Eu  ')->text);
        self::assertSame(200, mb_strlen(SearchQuery::for(str_repeat('é', 200))->text));
        self::assertSame('invalid_parameter', self::invalid(static fn () => SearchQuery::for(str_repeat('é', 201)))->errorCode);
    }

    /**
     * @dataProvider invalidTexts
     */
    #[DataProvider('invalidTexts')]
    public function test_text_with_control_characters_or_invalid_utf8_is_invalid(string $text): void
    {
        $e = self::invalid(static fn () => SearchQuery::for($text));

        self::assertSame('invalid_parameter', $e->errorCode);
        self::assertSame('q', $e->parameter);
    }

    public static function invalidTexts(): array
    {
        return [
            'tab' => ["pa\tris"],
            'leading newline' => ["\nparis"],
            'delete' => ["paris\x7f"],
            'next line' => ["par\u{0085}is"],
            'invalid utf-8' => ["\xC3\x28 paris"],
        ];
    }

    public function test_limit_is_between_1_and_20(): void
    {
        self::assertSame(1, SearchQuery::for('paris')->limit(1)->limit);
        self::assertSame(20, SearchQuery::for('paris')->limit(20)->limit);
        self::assertSame('limit', self::invalid(static fn () => SearchQuery::for('paris')->limit(0))->parameter);
        self::assertSame('limit', self::invalid(static fn () => SearchQuery::for('paris')->limit(21))->parameter);
    }

    /**
     * @dataProvider codes
     */
    #[DataProvider('codes')]
    public function test_territory_codes_follow_the_contract(string $method, string $parameter, string $value, bool $valid): void
    {
        $build = static fn () => SearchQuery::for('paris')->{$method}($value);

        if ($valid) {
            self::assertSame($value, $build()->toParameters()[$parameter]);
        } else {
            self::assertSame($parameter, self::invalid($build)->parameter);
        }
    }

    public static function codes(): array
    {
        $cases = [];
        $sets = [
            ['postcode', 'postcode', ['80000', '97500', '20000'], ['8000', '800000', '2A000', ' 80000', "80000\n"]],
            ['citycode', 'citycode', ['80021', '2A004', '2B033', '75056'], ['2C004', '8002', '2a004', "80021\n"]],
            ['departmentCode', 'department_code', ['01', '19', '2A', '2B', '21', '95', '971', '978'], ['00', '20', '96', '979', '984', '1', '2a', "80\n"]],
            ['regionCode', 'region_code', ['01', '06', '32', '94'], ['05', '99', '1', '976']],
        ];
        foreach ($sets as [$method, $parameter, $valid, $invalid]) {
            foreach ($valid as $value) {
                $cases["{$parameter} {$value}"] = [$method, $parameter, $value, true];
            }
            foreach ($invalid as $value) {
                $cases["{$parameter} {$value} refused"] = [$method, $parameter, $value, false];
            }
        }

        return $cases;
    }

    public function test_near_is_truncated_toward_zero_to_three_decimals(): void
    {
        $query = SearchQuery::for('paris')->near(new Coordinates(lat: -21.11549, lon: 55.53629));

        self::assertSame(-21.115, $query->near->lat);
        self::assertSame(55.536, $query->near->lon);
        self::assertSame(['q' => 'paris', 'lat' => '-21.115', 'lon' => '55.536'], $query->toParameters());
    }

    /**
     * @dataProvider nearBounds
     */
    #[DataProvider('nearBounds')]
    public function test_near_bounds_apply_after_truncation(float $lat, float $lon, ?string $invalid): void
    {
        if ($invalid !== null) {
            $this->expectExceptionObject(InvalidQuery::invalid($invalid));
        }

        self::assertNotNull(SearchQuery::for('paris')->near(new Coordinates($lat, $lon))->near);
    }

    public static function nearBounds(): array
    {
        return [
            'latitude just under -90' => [-90.0001, 0, null],
            'latitude over 90' => [90.001, 0, 'lat'],
            'longitude just over 180' => [0, 180.0009, null],
            'longitude over 180' => [0, 180.001, 'lon'],
            'latitude not a number' => [NAN, 0, 'lat'],
            'longitude infinite' => [0, INF, 'lon'],
        ];
    }

    public function test_near_handles_tiny_and_negative_zero_values(): void
    {
        $query = SearchQuery::for('paris')->near(new Coordinates(lat: 1.0E-5, lon: -0.0001));

        self::assertSame(['q' => 'paris', 'lat' => '0', 'lon' => '0'], $query->toParameters());
    }

    public function test_parameters_only_carry_what_was_set(): void
    {
        self::assertSame(['q' => 'paris'], SearchQuery::for('paris')->toParameters());

        $query = SearchQuery::for('8 bd du port')
            ->limit(10)
            ->autocomplete(false)
            ->type(FeatureType::Address)
            ->postcode('80000')
            ->citycode('80021')
            ->departmentCode('80')
            ->regionCode('32')
            ->near(new Coordinates(lat: 49.897443, lon: 2.290084));

        self::assertSame([
            'q' => '8 bd du port',
            'limit' => '10',
            'autocomplete' => 'false',
            'type' => 'address',
            'postcode' => '80000',
            'citycode' => '80021',
            'department_code' => '80',
            'region_code' => '32',
            'lat' => '49.897',
            'lon' => '2.29',
        ], $query->toParameters());
    }

    public function test_builders_return_new_instances(): void
    {
        $base = SearchQuery::for('paris');
        $limited = $base->limit(3);

        self::assertNull($base->limit);
        self::assertSame(3, $limited->limit);
        self::assertSame('paris', $limited->text);
    }

    private static function invalid(callable $build): InvalidQuery
    {
        try {
            $build();
        } catch (InvalidQuery $e) {
            return $e;
        }
        self::fail('Expected an InvalidQuery exception.');
    }
}
