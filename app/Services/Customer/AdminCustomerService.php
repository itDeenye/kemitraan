<?php

namespace App\Services\Customer;

use App\Libraries\DataTable;
use App\Models\Customer;
use App\Models\Member;
use App\Models\MemberLevel;
use App\Models\RefCity;
use App\Models\RefDistrict;
use App\Models\RefProvince;
use App\Models\RefSubdistrict;
use Illuminate\Support\Facades\DB;

class AdminCustomerService
{
    /** @param array<string, mixed> $params */
    public function list(array $params): array
    {
        return DataTable::select([
            'customer.customer_id as id',
            'customer.customer_name as name',
            'customer.customer_whatsapp as whatsapp',
            'customer.customer_phone as phone',
            'customer.customer_gender as gender',
            'customer.customer_birth_date as birth_date',
            'customer.customer_address as address',
            'customer.customer_subdistrict_id as subdistrict_id',
            'ref_subdistrict.subdistrict_name as subdistrict_name',
            'customer.customer_district_id as district_id',
            'ref_district.district_name as district_name',
            'customer.customer_city_id as city_id',
            'ref_city.city_name as city_name',
            'customer.customer_province_id as province_id',
            'ref_province.province_name as province_name',
            'customer.customer_member_id as member_id',
            'member.member_code as member_code',
            'member.member_name as member_name',
            'member_level.member_level_id as member_level_id',
            'member_level.member_level_code as member_level_code',
            'member_level.member_level_name as member_level',
            'customer.customer_created_datetime as created_at',
        ])->from('customer')->leftJoin('member', 'member.member_id = customer.customer_member_id')
            ->leftJoin('member_level', 'member_level.member_level_id = member.member_member_level_id')
            ->leftJoin('ref_province', 'ref_province.province_id = customer.customer_province_id')
            ->leftJoin('ref_city', 'ref_city.city_id = customer.customer_city_id')
            ->leftJoin('ref_district', 'ref_district.district_id = customer.customer_district_id')
            ->leftJoin('ref_subdistrict', 'ref_subdistrict.subdistrict_id = customer.customer_subdistrict_id')
            ->where('customer.customer_is_deleted', 0)
            ->search(['name', 'whatsapp', 'phone', 'address', 'member_code', 'member_name'])->defaultSort('-id')->get($params);
    }

    public function detail(Customer $customer): object
    {
        $customerTable = (new Customer)->getTable();
        $memberTable = (new Member)->getTable();
        $levelTable = (new MemberLevel)->getTable();
        $provinceTable = (new RefProvince)->getTable();
        $cityTable = (new RefCity)->getTable();
        $districtTable = (new RefDistrict)->getTable();
        $subdistrictTable = (new RefSubdistrict)->getTable();

        return DB::table($customerTable)
            ->leftJoin($memberTable, "{$memberTable}.member_id", '=', "{$customerTable}.customer_member_id")
            ->leftJoin($levelTable, "{$levelTable}.member_level_id", '=', "{$memberTable}.member_member_level_id")
            ->leftJoin($provinceTable, "{$provinceTable}.province_id", '=', "{$customerTable}.customer_province_id")
            ->leftJoin($cityTable, "{$cityTable}.city_id", '=', "{$customerTable}.customer_city_id")
            ->leftJoin($districtTable, "{$districtTable}.district_id", '=', "{$customerTable}.customer_district_id")
            ->leftJoin($subdistrictTable, "{$subdistrictTable}.subdistrict_id", '=', "{$customerTable}.customer_subdistrict_id")
            ->where("{$customerTable}.customer_is_deleted", 0)
            ->where("{$customerTable}.customer_id", $customer->getKey())
            ->select([
                "{$customerTable}.customer_id as id",
                "{$customerTable}.customer_name as name",
                "{$customerTable}.customer_whatsapp as whatsapp",
                "{$customerTable}.customer_phone as phone",
                "{$customerTable}.customer_gender as gender",
                "{$customerTable}.customer_birth_date as birth_date",
                "{$customerTable}.customer_address as address",
                "{$customerTable}.customer_subdistrict_id as subdistrict_id",
                "{$subdistrictTable}.subdistrict_name as subdistrict_name",
                "{$customerTable}.customer_district_id as district_id",
                "{$districtTable}.district_name as district_name",
                "{$customerTable}.customer_city_id as city_id",
                "{$cityTable}.city_name as city_name",
                "{$customerTable}.customer_province_id as province_id",
                "{$provinceTable}.province_name as province_name",
                "{$customerTable}.customer_member_id as member_id",
                "{$memberTable}.member_code as member_code",
                "{$memberTable}.member_name as member_name",
                "{$levelTable}.member_level_id as member_level_id",
                "{$levelTable}.member_level_code as member_level_code",
                "{$levelTable}.member_level_name as member_level",
                "{$customerTable}.customer_created_datetime as created_at",
            ])
            ->firstOrFail();
    }
}
