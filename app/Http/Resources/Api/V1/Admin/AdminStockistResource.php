<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\Stockist;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class AdminStockistResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $stockist = $this->resource instanceof Stockist ? $this->resource : null;

        return [
            'id' => $this->value('id', 'stockist_id'),
            'member' => [
                'id' => $stockist?->member?->member_id ?? $this->resource->member_id,
                'code' => $stockist?->member?->member_code ?? $this->resource->member_code,
                'name' => $stockist?->member?->member_name ?? $this->resource->member_name,
                'level' => [
                    'code' => $stockist?->member?->level?->member_level_code ?? $this->resource->member_level_code,
                    'name' => $stockist?->member?->level?->member_level_name ?? $this->resource->member_level_name,
                ],
            ],
            'name' => $this->value('name', 'stockist_name'),
            'email' => $this->value('email', 'stockist_email'),
            'address' => $this->value('address', 'stockist_address'),
            'mobile_phone' => $this->value('mobile_phone', 'stockist_mobilephone'),
            'image_url' => MediaUrl::publicUrl($this->value('image_url', 'stockist_image')),
            'region' => [
                'province_id' => $this->value('province_id', 'stockist_province_id'),
                'province_name' => $stockist?->province?->province_name ?? $this->resource->province_name,
                'city_id' => $this->value('city_id', 'stockist_city_id'),
                'city_name' => $stockist?->city?->city_name ?? $this->resource->city_name,
                'city_type' => $stockist?->city?->city_type ?? $this->resource->city_type,
                'district_id' => $this->value('district_id', 'stockist_district_id'),
                'district_name' => $stockist?->district?->district_name ?? $this->resource->district_name,
                'subdistrict_id' => $this->value('subdistrict_id', 'stockist_subdistrict_id'),
                'subdistrict_name' => $stockist?->subdistrict?->subdistrict_name ?? $this->resource->subdistrict_name,
            ],
            'coordinates' => [
                'latitude' => $this->value('latitude', 'stockist_latitude'),
                'longitude' => $this->value('longitude', 'stockist_longitude'),
            ],
            'note' => $this->value('note', 'stockist_note'),
            'is_active' => (bool) $this->value('is_active', 'stockist_is_active'),
            'created_at' => $this->value('created_at', 'stockist_input_datetime'),
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof Stockist
            ? $this->resource->getAttribute($modelField)
            : $this->resource->{$listField};
    }
}
