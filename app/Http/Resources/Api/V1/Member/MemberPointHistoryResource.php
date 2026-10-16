<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class MemberPointHistoryResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'transaction' => [
                'id' => (int) $this->resource->transaction_id,
                'code' => $this->resource->transaction_code,
                'is_preorder' => (bool) $this->resource->is_preorder,
            ],
            'quantity' => (int) $this->resource->quantity,
            'monthly_points' => (int) $this->resource->monthly_points,
            'annual_points' => (int) $this->resource->annual_points,
            'approved_at' => CarbonImmutable::parse($this->resource->approved_at)->toAtomString(),
        ];
    }
}
