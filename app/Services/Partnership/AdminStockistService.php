<?php

namespace App\Services\Partnership;

use App\Libraries\DataTable;
use App\Models\Member;
use App\Models\MemberLevel;
use App\Models\RefCity;
use App\Models\RefDistrict;
use App\Models\RefProvince;
use App\Models\RefSubdistrict;
use App\Models\Stockist;
use Illuminate\Support\Collection;

class AdminStockistService
{
    /** @return array<string, mixed> */
    public function options(): array
    {
        return [
            'members' => Member::query()
                ->with('level')
                ->where('member_status', 1)
                ->whereHas('level', fn ($query) => $query
                    ->where('member_level_code', 'DST')
                    ->where('member_level_is_active', 1))
                ->whereDoesntHave('stockist', fn ($query) => $query->where('stockist_is_deleted', 0))
                ->orderBy('member_name')
                ->get()
                ->map(fn (Member $member): array => [
                    'id' => $member->getKey(),
                    'code' => $member->member_code,
                    'name' => $member->member_name,
                    'level' => [
                        'code' => $member->level?->member_level_code,
                        'name' => $member->level?->member_level_name,
                    ],
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function stockists(array $params): array
    {
        $stockistTable = (new Stockist)->getTable();
        $memberTable = (new Member)->getTable();
        $levelTable = (new MemberLevel)->getTable();
        $provinceTable = (new RefProvince)->getTable();
        $cityTable = (new RefCity)->getTable();
        $districtTable = (new RefDistrict)->getTable();
        $subdistrictTable = (new RefSubdistrict)->getTable();

        return DataTable::select([
            "{$stockistTable}.stockist_id as id",
            "{$stockistTable}.stockist_member_id as member_id",
            "{$memberTable}.member_code as member_code",
            "{$memberTable}.member_name as member_name",
            "{$levelTable}.member_level_code as member_level_code",
            "{$levelTable}.member_level_name as member_level_name",
            "{$stockistTable}.stockist_name as name",
            "{$stockistTable}.stockist_email as email",
            "{$stockistTable}.stockist_address as address",
            "{$stockistTable}.stockist_mobilephone as mobile_phone",
            "{$stockistTable}.stockist_image as image_url",
            "{$stockistTable}.stockist_province_id as province_id",
            "{$provinceTable}.province_name as province_name",
            "{$stockistTable}.stockist_city_id as city_id",
            "{$cityTable}.city_name as city_name",
            "{$cityTable}.city_type as city_type",
            "{$stockistTable}.stockist_district_id as district_id",
            "{$districtTable}.district_name as district_name",
            "{$stockistTable}.stockist_subdistrict_id as subdistrict_id",
            "{$subdistrictTable}.subdistrict_name as subdistrict_name",
            "{$stockistTable}.stockist_latitude as latitude",
            "{$stockistTable}.stockist_longitude as longitude",
            "{$stockistTable}.stockist_note as note",
            "{$stockistTable}.stockist_is_active as is_active",
            "{$stockistTable}.stockist_input_datetime as created_at",
        ])
            ->from($stockistTable)
            ->leftJoin($memberTable, "{$memberTable}.member_id = {$stockistTable}.stockist_member_id")
            ->leftJoin($levelTable, "{$levelTable}.member_level_id = {$memberTable}.member_member_level_id")
            ->leftJoin($provinceTable, "{$provinceTable}.province_id = {$stockistTable}.stockist_province_id")
            ->leftJoin($cityTable, "{$cityTable}.city_id = {$stockistTable}.stockist_city_id")
            ->leftJoin($districtTable, "{$districtTable}.district_id = {$stockistTable}.stockist_district_id")
            ->leftJoin($subdistrictTable, "{$subdistrictTable}.subdistrict_id = {$stockistTable}.stockist_subdistrict_id")
            ->where("{$stockistTable}.stockist_is_deleted", 0)
            ->search(['member_code', 'member_name', 'name', 'email', 'mobile_phone', 'address', 'city_name'])
            ->defaultSort('-id')
            ->get($params);
    }

    public function stockist(Stockist $stockist): Stockist
    {
        return Stockist::query()
            ->with(['member.level', 'province', 'city', 'district', 'subdistrict'])
            ->whereKey($stockist->getKey())
            ->where('stockist_is_deleted', 0)
            ->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function createStockist(array $data): Stockist
    {
        $stockist = Stockist::query()->create([
            ...$this->stockistAttributes($data),
            'stockist_is_active' => $data['is_active'] ?? true,
            'stockist_is_deleted' => false,
            'stockist_input_datetime' => now(),
        ]);

        return $this->stockist($stockist);
    }

    /** @param array<string, mixed> $data */
    public function updateStockist(Stockist $stockist, array $data): Stockist
    {
        $stockist = $this->stockist($stockist);
        $stockist->update($this->stockistAttributes($data));

        return $this->stockist($stockist->refresh());
    }

    public function deleteStockist(Stockist $stockist): void
    {
        $stockist = $this->stockist($stockist);
        $stockist->update([
            'stockist_is_active' => false,
            'stockist_is_deleted' => true,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function stockistAttributes(array $data): array
    {
        $attributes = [
            'stockist_member_id' => $data['member_id'],
            'stockist_name' => $data['name'],
            'stockist_email' => $data['email'] ?? '',
            'stockist_address' => $data['address'],
            'stockist_mobilephone' => $data['mobile_phone'],
            'stockist_image' => $data['image_url'] ?? '',
            'stockist_province_id' => $data['province_id'],
            'stockist_city_id' => $data['city_id'],
            'stockist_district_id' => $data['district_id'],
            'stockist_subdistrict_id' => $data['subdistrict_id'],
            'stockist_latitude' => $data['latitude'] ?? '0',
            'stockist_longitude' => $data['longitude'] ?? '0',
            'stockist_note' => $data['note'] ?? '',
        ];

        if (array_key_exists('is_active', $data)) {
            $attributes['stockist_is_active'] = $data['is_active'];
        }

        return $attributes;
    }
}
