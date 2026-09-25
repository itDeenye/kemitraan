<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class MemberAddressResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->member_address_id,
            'label' => $this->resource->member_address_label,
            'recipient' => $this->resource->member_address_recipient,
            'phone' => $this->resource->member_address_phone,
            'full_address' => $this->resource->member_address_full,
            'region' => [
                'province_id' => (string) $this->resource->member_address_province_id,
                'province_name' => $this->resource->province?->province_name,
                'city_id' => (string) $this->resource->member_address_city_id,
                'city_name' => $this->resource->city?->city_name,
                'city_type' => $this->resource->city?->city_type,
                'district_id' => (string) $this->resource->member_address_district_id,
                'district_name' => $this->resource->district?->district_name,
                'subdistrict_id' => (int) $this->resource->member_address_subdistrict_id,
                'subdistrict_name' => $this->resource->subdistrict?->subdistrict_name,
                'postal_code' => $this->resource->subdistrict?->subdistrict_zip_code,
                'country_id' => (int) $this->resource->member_address_country_id,
                'country_name' => $this->resource->country?->country_name,
            ],
            'is_default' => (bool) $this->resource->member_address_is_default,
        ];
    }
}
