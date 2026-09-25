<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class MemberStockResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'product' => [
                'id' => $this->product_id,
                'code' => $this->product_code,
                'name' => $this->product_name,
                'category_id' => $this->category_id,
                'category_name' => $this->category_name,
                'unit' => $this->unit,
            ],
            'balance' => (int) $this->balance,
            'transfer_in' => (int) $this->transfer_in,
            'transfer_out' => (int) $this->transfer_out,
        ];
    }
}
