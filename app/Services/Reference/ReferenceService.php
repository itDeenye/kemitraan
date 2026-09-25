<?php

namespace App\Services\Reference;

use App\Models\RefBank;
use App\Models\RefCity;
use App\Models\RefCountry;
use App\Models\RefDistrict;
use App\Models\RefProvince;
use App\Models\RefSubdistrict;
use Illuminate\Support\Collection;

class ReferenceService
{
    /** @return Collection<int, RefProvince> */
    public function provinces(): Collection
    {
        return RefProvince::query()
            ->select(['province_id as id', 'province_name as name'])
            ->where('province_is_active', '1')
            ->orderBy('province_name')
            ->get();
    }

    /** @return Collection<int, RefCity> */
    public function cities(string $provinceId): Collection
    {
        return RefCity::query()
            ->select(['city_id as id', 'city_name as name', 'city_type as type'])
            ->where('city_province_id', $provinceId)
            ->where('city_is_active', 1)
            ->orderBy('city_name')
            ->get();
    }

    /** @return Collection<int, RefDistrict> */
    public function districts(string $cityId): Collection
    {
        return RefDistrict::query()
            ->select(['district_id as id', 'district_name as name'])
            ->where('district_city_id', $cityId)
            ->orderBy('district_name')
            ->get();
    }

    /** @return Collection<int, RefSubdistrict> */
    public function subdistricts(int $districtId): Collection
    {
        return RefSubdistrict::query()
            ->select([
                'subdistrict_id as id',
                'subdistrict_name as name',
                'subdistrict_zip_code as postal_code',
            ])
            ->where('subdistrict_district_id', $districtId)
            ->orderBy('subdistrict_name')
            ->get();
    }

    /** @return Collection<int, RefBank> */
    public function banks(): Collection
    {
        return RefBank::query()
            ->select(['bank_id as id', 'bank_code as code', 'bank_name as name', 'bank_logo as logo'])
            ->where('bank_is_active', 1)
            ->orderBy('bank_name')
            ->get();
    }

    /** @return Collection<int, RefCountry> */
    public function countries(): Collection
    {
        return RefCountry::query()
            ->select([
                'country_id as id',
                'country_iso_code as code',
                'country_phone_code as phone_code',
                'country_name as name',
                'country_flag as flag',
            ])
            ->where('country_is_active', '1')
            ->orderBy('country_name')
            ->get();
    }
}
