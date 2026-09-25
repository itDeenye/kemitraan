<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class AdminMemberStockResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'member' => ['id' => $this->member_id, 'code' => $this->member_code, 'name' => $this->member_name, 'level' => ['id' => $this->member_level_id, 'code' => $this->member_level_code, 'name' => $this->member_level]], 'product' => ['id' => $this->product_id, 'code' => $this->product_code, 'name' => $this->product_name, 'category_id' => $this->category_id, 'category_name' => $this->category_name, 'unit' => $this->unit], 'balance' => (int) $this->balance, 'transfer_in' => (int) $this->transfer_in, 'transfer_out' => (int) $this->transfer_out, 'last_updated_at' => $this->last_updated_at];
    }
}
