<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiCityNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_city_names_use_the_region_type_without_changing_other_names_or_ids(): void
    {
        $this->createRegionReferences();
        Route::middleware('api')->get('/api/v1/testing/city-names', fn () => response()->json([
            'data' => [
                'results' => [
                    ['name' => 'Malang', 'region' => ['city_id' => '3507', 'city_name' => 'Malang']],
                    ['name' => 'Malang', 'region' => ['city_id' => '3573', 'city_name' => 'Malang']],
                ],
                'address' => ['city_name' => 'Malang', 'city_type' => 'Kota'],
                'bank' => ['city' => 'Malang'],
                'empty' => (object) [],
            ],
        ]));

        $response = $this->getJson('/api/v1/testing/city-names')
            ->assertOk()
            ->assertJsonPath('data.results.0.region.city_name', 'Kabupaten Malang')
            ->assertJsonPath('data.results.0.region.city_id', '3507')
            ->assertJsonPath('data.results.1.region.city_name', 'Kota Malang')
            ->assertJsonPath('data.results.1.region.city_id', '3573')
            ->assertJsonPath('data.address.city_name', 'Kota Malang')
            ->assertJsonPath('data.results.0.name', 'Malang')
            ->assertJsonPath('data.bank.city', 'Malang');

        $this->assertStringContainsString('"empty":{}', $response->getContent());
        $this->assertSame(['Malang', 'Malang'], DB::table('ref_city')->orderBy('city_id')->pluck('city_name')->all());
    }

    public function test_shipping_snapshot_city_names_are_resolved_in_batches_from_region_ids(): void
    {
        $this->createRegionReferences();
        $location = [
            'origin' => ['city' => ['id' => '3507', 'name' => 'Malang']],
            'destination' => ['city' => ['id' => null, 'name' => 'Malang'], 'subdistrict' => ['id' => 2]],
            'transit' => ['city' => ['name' => 'Malang'], 'district' => ['id' => '350701']],
        ];
        Route::middleware('api')->get('/api/v1/testing/shipping-cities', fn () => response()->json([
            'data' => ['results' => array_fill(0, 40, $location)],
        ]));
        DB::enableQueryLog();

        $this->getJson('/api/v1/testing/shipping-cities')
            ->assertOk()
            ->assertJsonPath('data.results.0.origin.city.name', 'Kabupaten Malang')
            ->assertJsonPath('data.results.0.destination.city.name', 'Kota Malang')
            ->assertJsonPath('data.results.0.destination.city.id', null)
            ->assertJsonPath('data.results.0.transit.city.name', 'Kabupaten Malang')
            ->assertJsonPath('data.results.39.destination.city.name', 'Kota Malang');

        $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'ref_city'));
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(3, $queries->count());
    }

    public function test_existing_prefixes_missing_names_and_unknown_region_types_are_preserved(): void
    {
        Route::middleware('api')->get('/api/v1/testing/existing-city-prefixes', fn () => response()->json([
            'data' => [
                ['city_name' => 'Kota Malang', 'city_type' => 'Kota'],
                ['city_name' => 'Kabupaten Malang', 'city_type' => 'Kabupaten'],
                ['city_name' => null, 'city_type' => 'Kota'],
                ['city_name' => '', 'city_type' => 'Kabupaten'],
                ['city_name' => 'Malang', 'city_type' => null],
                ['city_name' => 'Malang', 'city_type' => 'KOTA'],
                ['city_name' => 'Malang', 'city_id' => '9999', 'subdistrict_id' => 9999],
            ],
        ]));

        $this->getJson('/api/v1/testing/existing-city-prefixes')
            ->assertOk()
            ->assertJsonPath('data.0.city_name', 'Kota Malang')
            ->assertJsonPath('data.1.city_name', 'Kabupaten Malang')
            ->assertJsonPath('data.2.city_name', null)
            ->assertJsonPath('data.3.city_name', '')
            ->assertJsonPath('data.4.city_name', 'Malang')
            ->assertJsonPath('data.5.city_name', 'Kota Malang')
            ->assertJsonPath('data.6.city_name', 'Malang');
    }

    public function test_non_api_payloads_and_error_responses_are_not_rewritten(): void
    {
        $payload = ['data' => ['city_name' => 'Malang', 'city_type' => 'Kota']];
        Route::get('/testing/raw-city-name', fn () => response()->json($payload));
        Route::middleware('api')->get('/api/v1/testing/city-error', fn () => response()->json($payload, 422));

        $this->getJson('/testing/raw-city-name')->assertOk()->assertExactJson($payload);
        $this->getJson('/api/v1/testing/city-error')->assertUnprocessable()->assertExactJson($payload);
    }

    private function createRegionReferences(): void
    {
        DB::table('ref_province')->insert(['province_id' => '35', 'province_name' => 'Jawa Timur']);
        DB::table('ref_city')->insert([
            ['city_id' => '3507', 'city_province_id' => '35', 'city_name' => 'Malang', 'city_type' => 'Kabupaten'],
            ['city_id' => '3573', 'city_province_id' => '35', 'city_name' => 'Malang', 'city_type' => 'Kota'],
        ]);
        DB::table('ref_district')->insert([
            ['district_id' => '350701', 'district_city_id' => '3507', 'district_name' => 'Kepanjen'],
            ['district_id' => '357301', 'district_city_id' => '3573', 'district_name' => 'Klojen'],
        ]);
        DB::table('ref_subdistrict')->insert([
            ['subdistrict_id' => 1, 'subdistrict_district_id' => 350701, 'subdistrict_name' => 'Kepanjen'],
            ['subdistrict_id' => 2, 'subdistrict_district_id' => 357301, 'subdistrict_name' => 'Klojen'],
        ]);
    }
}
