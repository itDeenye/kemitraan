<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InitialWarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $location = self::warehouseLocation();

        $warehouse = Warehouse::query()->find(1) ?? new Warehouse;
        $warehouse->warehouse_id = 1;
        $warehouse->fill([
            ...self::warehouseAttributes($location),
            'warehouse_created_datetime' => $warehouse->warehouse_created_datetime ?? now(),
        ])->save();
    }

    /**
     * @param  array{province_id: int, city_id: int, district_id: int, subdistrict_id: int}  $location
     * @return array<string, int|string>
     */
    public static function warehouseAttributes(array $location): array
    {
        return [
            'warehouse_name' => 'Warehouse Utama DNY',
            'warehouse_legal_name' => 'PT Deenye Berkah Abadi',
            'warehouse_npwp' => '01.234.567.8-901.000',
            'warehouse_phone' => '+6281110000101',
            'warehouse_email' => 'warehouse@dny.example.test',
            'warehouse_logo' => '/logo.png',
            'warehouse_address' => 'Jalan Teuku Umar Barat No. 88, Dauh Puri, Denpasar Barat, Denpasar, Bali 80113',
            'warehouse_province_id' => $location['province_id'],
            'warehouse_city_id' => $location['city_id'],
            'warehouse_district_id' => $location['district_id'],
            'warehouse_subdistrict_id' => $location['subdistrict_id'],
            'warehouse_latitude' => '-8.6705',
            'warehouse_longitude' => '115.2126',
            'warehouse_is_active' => 1,
        ];
    }

    /** @return array{province_id: int, city_id: int, district_id: int, subdistrict_id: int} */
    public static function warehouseLocation(): array
    {
        $provinceId = (int) DB::table('ref_province')
            ->where('province_name', 'Bali')
            ->value('province_id');
        $cityId = (int) DB::table('ref_city')
            ->where('city_province_id', $provinceId)
            ->where('city_name', 'Denpasar')
            ->value('city_id');
        $districtId = (int) DB::table('ref_district')
            ->where('district_city_id', $cityId)
            ->where('district_name', 'Denpasar Barat')
            ->value('district_id');
        $subdistrictId = (int) DB::table('ref_subdistrict')
            ->where('subdistrict_district_id', $districtId)
            ->where('subdistrict_name', 'Dauh Puri')
            ->value('subdistrict_id');

        if (in_array(0, [$provinceId, $cityId, $districtId, $subdistrictId], true)) {
            $provinceId = (int) DB::table('ref_province')->orderBy('province_id')->value('province_id');
            $cityId = (int) DB::table('ref_city')
                ->where('city_province_id', $provinceId)
                ->orderBy('city_id')
                ->value('city_id');
            $districtId = (int) DB::table('ref_district')
                ->where('district_city_id', $cityId)
                ->orderBy('district_id')
                ->value('district_id');
            $subdistrictId = (int) DB::table('ref_subdistrict')
                ->where('subdistrict_district_id', $districtId)
                ->orderBy('subdistrict_id')
                ->value('subdistrict_id');
        }

        if (in_array(0, [$provinceId, $cityId, $districtId, $subdistrictId], true)
            && DB::getDriverName() !== 'sqlite') {
            throw new RuntimeException('Referensi wilayah Warehouse Utama DNY tidak lengkap.');
        }

        return [
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'district_id' => $districtId,
            'subdistrict_id' => $subdistrictId,
        ];
    }
}
