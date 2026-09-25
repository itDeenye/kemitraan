<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class AdminPartnershipReportResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'level' => [
                'id' => (int) $this->member_level_id,
                'code' => $this->member_level_code,
                'name' => $this->member_level_name,
            ],
            'region' => [
                'province_id' => (int) $this->province_id,
                'province_name' => $this->province_name,
                'city_id' => (int) $this->city_id,
                'city_name' => $this->city_name,
            ],
            'status' => (int) $this->status,
            'joined_at' => $this->joined_at,
            'total_purchases' => (int) $this->total_purchases,
            'purchase_amount' => (int) $this->purchase_amount,
            'sales_amount' => (int) $this->sales_amount,
        ];
    }
}
