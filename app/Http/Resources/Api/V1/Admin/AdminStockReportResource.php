<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class AdminStockReportResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $endingBalance = (int) $this->ending_balance;
        $stockIn = (int) $this->stock_in;
        $stockOut = (int) $this->stock_out;

        return [
            'id' => (int) $this->id,
            'warehouse' => ['id' => (int) $this->warehouse_id, 'name' => $this->warehouse_name],
            'product' => [
                'id' => (int) $this->product_id,
                'code' => $this->product_code,
                'name' => $this->product_name,
                'category' => ['id' => (int) $this->category_id, 'name' => $this->category_name],
                'unit' => $this->unit,
            ],
            'starting_balance' => $endingBalance - $stockIn + $stockOut,
            'stock_in' => $stockIn,
            'stock_out' => $stockOut,
            'ending_balance' => $endingBalance,
        ];
    }
}
