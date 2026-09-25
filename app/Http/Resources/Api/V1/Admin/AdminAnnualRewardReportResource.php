<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class AdminAnnualRewardReportResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $months = data_get($this->resource, 'months');

        return [
            'id' => (int) data_get($this->resource, 'id'),
            'member' => [
                'id' => (int) data_get($this->resource, 'member_id'),
                'code' => data_get($this->resource, 'member_code'),
                'name' => data_get($this->resource, 'member_name'),
                'level' => [
                    'id' => (int) data_get($this->resource, 'member_level_id'),
                    'code' => data_get($this->resource, 'member_level_code'),
                    'name' => data_get($this->resource, 'member_level_name'),
                ],
            ],
            'year' => (int) data_get($this->resource, 'year'),
            'total_points' => (int) data_get($this->resource, 'total_points'),
            'updated_at' => data_get($this->resource, 'updated_at'),
            'months' => $this->when($months !== null, $months),
        ];
    }
}
