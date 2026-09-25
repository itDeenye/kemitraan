<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use App\Models\Product;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class MemberProductResource extends ApiResource
{
    private const LOW_STOCK_MAXIMUM = 5;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $availableStock = max(0, (int) ($this->available_stock ?? 0));
        $directAvailableStock = max(0, (int) ($this->direct_available_stock ?? 0));

        return [
            'id' => $this->product_id,
            'code' => $this->resource instanceof Product ? $this->product_code : $this->code,
            'name' => $this->resource instanceof Product ? $this->product_name : $this->name,
            'description' => $this->product_description,
            'bpom_number' => $this->product_bpom_number,
            'image' => MediaUrl::publicUrl($this->product_image),
            'image_filename' => $this->product_image_filename,
            'price' => (int) ($this->resource instanceof Product ? $this->catalog_price : $this->price),
            'customer_price' => (int) $this->product_customer_price,
            'weight_grams' => (int) $this->product_weight,
            'dimensions_cm' => [
                'length' => (int) $this->product_length,
                'width' => (int) $this->product_width,
                'height' => (int) $this->product_height,
            ],
            'unit' => $this->product_unit,
            'is_package' => (bool) $this->product_is_package,
            'stock' => [
                'available' => $availableStock,
                'status' => $this->stockStatus($availableStock, $directAvailableStock),
                'requires_preorder' => $availableStock > 0 && $directAvailableStock === 0,
            ],
            'category' => $this->categoryData(),
        ];
    }

    /** @return array{code: string, label: string} */
    private function stockStatus(int $availableStock, int $directAvailableStock): array
    {
        if ($availableStock === 0) {
            return ['code' => 'out_of_stock', 'label' => 'Habis'];
        }

        if ($directAvailableStock === 0) {
            return ['code' => 'preorder', 'label' => 'PO'];
        }

        if ($availableStock <= self::LOW_STOCK_MAXIMUM) {
            return ['code' => 'low_stock', 'label' => 'Sisa Sedikit'];
        }

        return ['code' => 'available', 'label' => 'Tersedia'];
    }

    /** @return array{id: mixed, name: mixed, description: mixed} */
    private function categoryData(): array
    {
        if ($this->resource instanceof Product) {
            $category = $this->resource->getRelation('category');

            return [
                'id' => $category->product_category_id,
                'name' => $category->product_category_name,
                'description' => $category->product_category_description,
            ];
        }

        return [
            'id' => $this->category_id,
            'name' => $this->category_name,
            'description' => $this->category_description,
        ];
    }
}
