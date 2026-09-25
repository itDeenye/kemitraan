<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class AdminCustomerResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'whatsapp' => $this->whatsapp, 'phone' => $this->phone, 'gender' => $this->gender, 'birth_date' => $this->birth_date, 'address' => $this->address, 'region' => ['province_id' => $this->province_id, 'province_name' => $this->province_name, 'city_id' => $this->city_id, 'city_name' => $this->city_name, 'district_id' => $this->district_id, 'district_name' => $this->district_name, 'subdistrict_id' => $this->subdistrict_id, 'subdistrict_name' => $this->subdistrict_name], 'member' => ['id' => $this->member_id, 'code' => $this->member_code, 'name' => $this->member_name, 'level' => ['id' => $this->member_level_id, 'code' => $this->member_level_code, 'name' => $this->member_level]], 'created_at' => $this->created_at];
    }
}
