<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\WarehouseStockAdjustment;
use Illuminate\Http\Request;

class AdminStockAdjustmentResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $adjustment = $this->resource instanceof WarehouseStockAdjustment ? $this->resource : null;

        return [
            'id' => (int) ($adjustment?->stock_adjustment_id ?? $this->resource->id),
            'code' => $adjustment?->stock_adjustment_code ?? $this->resource->code,
            'note' => $adjustment?->stock_adjustment_note ?? $this->resource->note,
            'warehouse' => [
                'id' => (int) ($adjustment?->stock_adjustment_warehouse_id ?? $this->resource->warehouse_id),
                'name' => $adjustment?->warehouse?->warehouse_name ?? $this->resource->warehouse_name,
            ],
            'administrator' => [
                'id' => (int) ($adjustment?->stock_adjustment_administrator_id ?? $this->resource->administrator_id),
                'name' => $adjustment?->administrator?->administrator_name ?? $this->resource->administrator_name,
            ],
            'total_items' => $adjustment ? $adjustment->details->count() : (int) $this->resource->total_items,
            'total_quantity' => $adjustment
                ? (int) $adjustment->details->sum('stock_adjustment_detail_qty')
                : (int) $this->resource->total_quantity,
            'happened_at' => $adjustment
                ? $adjustment->stock_adjustment_datetime?->toAtomString()
                : $this->resource->happened_at,
            'details' => $this->when($adjustment !== null, fn () => $adjustment->details->map(fn ($detail): array => [
                'id' => (int) $detail->stock_adjustment_detail_id,
                'warehouse_stock_id' => (int) $detail->stock_adjustment_detail_stock_warehouse_id,
                'product' => [
                    'id' => (int) $detail->stock_adjustment_detail_product_id,
                    'code' => $detail->product?->product_code,
                    'name' => $detail->product?->product_name,
                    'unit' => $detail->product?->product_unit,
                ],
                'batch_number' => $detail->stock_adjustment_detail_batch_number,
                'type' => $detail->stock_adjustment_detail_type,
                'quantity' => (int) $detail->stock_adjustment_detail_qty,
                'unit_price' => (int) $detail->stock_adjustment_detail_current_price,
                'note' => $detail->stock_adjustment_detail_note,
            ])->values()),
        ];
    }
}
