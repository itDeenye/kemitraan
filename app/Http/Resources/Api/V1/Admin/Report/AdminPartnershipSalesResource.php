<?php

namespace App\Http\Resources\Api\V1\Admin\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPartnershipSalesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'datetime' => $this->datetime,
            'code' => $this->code,
            'is_preorder' => (bool) $this->is_preorder,
            'buyer' => [
                'type' => $this->buyer_type,
                'id' => (int) $this->buyer_id,
                'name' => $this->buyer_type === 'customer'
                    ? $this->buyer_customer_name
                    : $this->buyer_name,
                'code' => $this->buyer_type === 'customer' ? null : $this->buyer_code,
            ],
            'seller' => [
                'type' => $this->seller_type,
                'id' => (int) $this->seller_id,
                'name' => $this->seller_type === 'warehouse'
                    ? $this->seller_warehouse_name
                    : $this->seller_member_name,
                'code' => $this->seller_type === 'warehouse'
                    ? null
                    : $this->seller_member_code,
            ],
            'total_price' => (int) $this->total_price,
            'status' => $this->status,
        ];
    }
}
