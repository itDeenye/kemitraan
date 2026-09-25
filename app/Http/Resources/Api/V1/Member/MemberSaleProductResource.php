<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class MemberSaleProductResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'image' => MediaUrl::publicUrl($this->resource->image),
            'price' => (int) $this->resource->price,
            'available_stock' => (int) $this->resource->available_stock,
            'weight_grams' => (int) $this->resource->weight,
            'unit' => $this->resource->unit,
            'category' => [
                'id' => (int) $this->resource->category_id,
                'name' => $this->resource->category_name,
            ],
        ];
    }
}
