<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class MemberAnnualRewardReportResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'year' => (int) data_get($this->resource, 'year'),
            'total_points' => (int) data_get($this->resource, 'total_points'),
            'months' => data_get($this->resource, 'months', []),
        ];
    }
}
