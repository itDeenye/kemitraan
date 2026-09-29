<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class AdminWarehouseStockResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse' => ['id' => $this->warehouse_id, 'name' => $this->warehouse_name],
            'product' => [
                'id' => $this->product_id,
                'code' => $this->product_code,
                'name' => $this->product_name,
                'category_id' => $this->category_id,
                'category_name' => $this->category_name,
                'unit' => $this->unit,
            ],
            'balance' => (int) $this->balance,
            'available_balance' => max(0, (int) $this->balance),
            'transfer_in' => (int) $this->transfer_in,
            'transfer_out' => (int) $this->transfer_out,
        ];
    }
}
