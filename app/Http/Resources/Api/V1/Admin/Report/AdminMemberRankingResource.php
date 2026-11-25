<?php

namespace App\Http\Resources\Api\V1\Admin\Report;

use App\Services\Reference\CityNameFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMemberRankingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => (int) $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'level' => $this->level,
            'level_code' => $this->level_code,
            'area' => trim((string) CityNameFormatter::displayName($this->city_name, $this->city_type)) ?: '-',
        ];

        foreach (['purchase_amount', 'recruit_count', 'monthly_points', 'reward_amount', 'annual_points'] as $metric) {
            if (property_exists($this->resource, $metric)) {
                $data[$metric] = (int) $this->resource->{$metric};
            }
        }

        return $data;
    }
}
