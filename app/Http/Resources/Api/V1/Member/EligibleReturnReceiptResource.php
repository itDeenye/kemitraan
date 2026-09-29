<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class EligibleReturnReceiptResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $receivedAt = CarbonImmutable::parse($this->resource->received_at);
        $sellerType = (string) $this->resource->seller_type;

        return [
            'receive_id' => (int) $this->resource->receive_id,
            'receive_number' => $this->resource->receive_number,
            'delivery_note_number' => !empty($this->resource->delivery_note_number) ? (string) $this->resource->delivery_note_number : null,
            'transaction' => [
                'id' => (int) $this->resource->transaction_id,
                'code' => $this->resource->transaction_code,
            ],
            'seller' => [
                'type' => $sellerType,
                'id' => (int) $this->resource->seller_id,
                'code' => $sellerType === 'warehouse' ? null : $this->resource->seller_code,
                'name' => $sellerType === 'warehouse'
                    ? $this->resource->warehouse_name
                    : $this->resource->seller_name,
            ],
            'summary' => [
                'product_count' => (int) $this->resource->product_count,
                'received_quantity' => (int) $this->resource->received_quantity,
                'returned_quantity' => (int) $this->resource->returned_quantity,
                'remaining_quantity' => (int) $this->resource->received_quantity
                    - (int) $this->resource->returned_quantity,
            ],
            'received_at' => $receivedAt->toAtomString(),
            'return_deadline' => $receivedAt->addDays((int) $this->resource->return_max_days)->toAtomString(),
            'items' => collect($this->resource->items ?? (is_array($this->resource) ? ($this->resource['items'] ?? []) : []))->map(function ($item): array {
                $item = (object) $item;
                return [
                    'goods_receive_detail_id' => (int) ($item->goods_receive_detail_id ?? 0),
                    'product_id' => (int) ($item->product_id ?? 0),
                    'product_code' => (string) ($item->product_code ?? ''),
                    'product_name' => (string) ($item->product_name ?? ''),
                    'product_image' => !empty($item->product_image) ? (string) $item->product_image : null,
                    'product_price' => (int) ($item->product_price ?? 0),
                    'product_weight' => (int) ($item->product_weight ?? 0),
                    'batch_number' => (string) ($item->batch_number ?? ''),
                    'expire_date' => !empty($item->expire_date) ? (string) $item->expire_date : null,
                    'received_quantity' => (int) ($item->received_quantity ?? 0),
                    'returned_quantity' => (int) ($item->returned_quantity ?? 0),
                    'remaining_quantity' => (int) ($item->remaining_quantity ?? 0),
                ];
            })->all(),
        ];
    }
}
