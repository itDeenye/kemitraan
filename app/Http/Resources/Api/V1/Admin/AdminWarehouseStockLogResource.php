<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class AdminWarehouseStockLogResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'warehouse' => ['id' => (int) $this->warehouse_id, 'name' => $this->warehouse_name],
            'product' => [
                'id' => (int) $this->product_id,
                'code' => $this->product_code,
                'name' => $this->product_name,
            ],
            'type' => $this->type,
            'quantity' => (int) $this->quantity,
            'unit_price' => (int) $this->unit_price,
            'balance' => (int) $this->balance,
            'note' => $this->note,
            'happened_at' => $this->happened_at,
        ];
    }
}
