<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class AdminProductCategoryResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->value('id', 'product_category_id'),
            'name' => $this->value('name', 'product_category_name'),
            'description' => $this->value('description', 'product_category_description'),
            'product_count' => (int) $this->value('product_count', 'products_count'),
            'is_active' => (bool) $this->value('is_active', 'product_category_is_active'),
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof ProductCategory
            ? $this->resource->getAttribute($modelField)
            : $this->resource->{$listField};
    }
}
