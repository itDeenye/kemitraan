<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\Product;
use Illuminate\Http\Request;

class AdminProductPriceResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $product = $this->resource instanceof Product ? $this->resource : null;
        $prices = $product?->levelPrices ?? $this->resource->member_prices;

        return [
            'id' => $product?->product_id ?? $this->resource->id,
            'code' => $product?->product_code ?? $this->resource->code,
            'name' => $product?->product_name ?? $this->resource->name,
            'category' => [
                'id' => $product?->category?->product_category_id ?? $this->resource->category_id,
                'name' => $product?->category?->product_category_name ?? $this->resource->category_name,
            ],
            'customer_price' => (int) ($product?->product_customer_price ?? $this->resource->customer_price),
            'member_prices' => $prices->map(fn ($price): array => [
                'member_level_id' => (int) $price->product_price_member_level_id,
                'code' => $price->level?->member_level_code,
                'name' => $price->level?->member_level_name,
                'price' => (int) $price->product_price_value,
            ])->values(),
            'is_active' => (bool) ($product?->product_is_active ?? $this->resource->is_active),
        ];
    }
}
