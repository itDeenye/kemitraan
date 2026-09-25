<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class MemberStockMutationResource extends ApiResource
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
            ],
            'type' => $this->type,
            'quantity' => (int) $this->quantity,
            'unit_price' => (int) $this->unit_price,
            'balance' => (int) $this->balance,
            'note' => $this->note,
            'datetime' => $this->datetime,
        ];
    }
}
