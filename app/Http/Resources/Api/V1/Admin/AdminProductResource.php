<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\Product;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class AdminProductResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $category = $this->resource instanceof Product
            ? $this->resource->getRelation('category')
            : null;

        return [
            'id' => $this->value('id', 'product_id'),
            'code' => $this->value('code', 'product_code'),
            'name' => $this->value('name', 'product_name'),
            'bpom_number' => $this->value('bpom_number', 'product_bpom_number'),
            'description' => $this->value('description', 'product_description'),
            'image' => MediaUrl::publicUrl($this->value('image', 'product_image')),
            'image_filename' => $this->value('image_filename', 'product_image_filename'),
            'prices' => [
                'customer' => (int) $this->value('customer_price', 'product_customer_price'),
                'members' => $this->memberPrices(),
            ],
            'weight_grams' => (int) $this->value('weight', 'product_weight'),
            'dimensions_cm' => [
                'length' => (int) $this->value('length', 'product_length'),
                'width' => (int) $this->value('width', 'product_width'),
                'height' => (int) $this->value('height', 'product_height'),
            ],
            'unit' => $this->value('unit', 'product_unit'),
            'is_package' => (bool) $this->value('is_package', 'product_is_package'),
            'is_publish' => (bool) $this->value('is_publish', 'product_is_publish'),
            'is_active' => (bool) $this->value('is_active', 'product_is_active'),
            'category' => [
                'id' => $category?->product_category_id ?? $this->value('category_id', 'product_product_category_id'),
                'name' => $category?->product_category_name ?? $this->value('category_name', 'product_category_name'),
            ],
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof Product
            ? $this->resource->getAttribute($modelField)
            : $this->resource->{$listField};
    }

    /** @return array<int, array<string, mixed>> */
    private function memberPrices(): array
    {
        $prices = $this->resource instanceof Product
            ? $this->resource->levelPrices
            : ($this->resource->member_prices ?? collect());

        return $prices->map(fn ($price): array => [
            'member_level_id' => $price->product_price_member_level_id,
            'code' => $price->level?->member_level_code,
            'name' => $price->level?->member_level_name,
            'price' => (int) $price->product_price_value,
        ])->values()->all();
    }
}
