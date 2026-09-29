<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class AdminWarehouseResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'legal_name' => $this->legal_name, 'npwp' => $this->npwp, 'phone' => $this->phone, 'email' => $this->email, 'logo' => $this->logo, 'address' => $this->address, 'region' => ['province_id' => $this->province_id, 'province_name' => $this->province_name, 'city_id' => $this->city_id, 'city_name' => $this->city_name, 'city_type' => $this->city_type, 'district_id' => $this->district_id, 'district_name' => $this->district_name, 'subdistrict_id' => $this->subdistrict_id, 'subdistrict_name' => $this->subdistrict_name], 'coordinates' => ['latitude' => $this->latitude, 'longitude' => $this->longitude], 'is_active' => (bool) $this->is_active, 'created_at' => $this->created_at];
    }
}
