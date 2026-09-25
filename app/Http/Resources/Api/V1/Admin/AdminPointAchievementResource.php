<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class AdminPointAchievementResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'member' => [
                'id' => (int) $this->member_id,
                'code' => $this->member_code,
                'name' => $this->member_name,
                'level' => [
                    'id' => (int) $this->member_level_id,
                    'code' => $this->member_level_code,
                    'name' => $this->member_level_name,
                ],
            ],
            'period' => ['year' => (int) $this->year, 'month' => (int) $this->month],
            'points' => (int) $this->points,
            'customer_count' => (int) $this->customer_count,
            'total_spending' => (int) $this->total_spending,
            'created_at' => $this->created_at,
        ];
    }
}
