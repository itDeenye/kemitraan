<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\Promotion;
use Illuminate\Http\Request;

class AdminPromotionResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->value('id', 'promotion_id'),
            'name' => $this->value('name', 'promotion_name'),
            'type' => $this->value('type', 'promotion_type'),
            'value' => (int) $this->value('value', 'promotion_value'),
            'start_date' => $this->dateValue('start_date', 'promotion_start_date'),
            'end_date' => $this->dateValue('end_date', 'promotion_end_date'),
            'terms' => $this->value('terms', 'promotion_terms'),
            'product_count' => (int) $this->value('product_count', 'products_count'),
            'is_active' => (bool) $this->value('is_active', 'promotion_is_active'),
            'products' => $this->when(
                $this->resource instanceof Promotion && $this->resource->relationLoaded('products'),
                fn (): array => $this->resource->products->map(fn ($product): array => [
                    'id' => $product->product_id,
                    'code' => $product->product_code,
                    'name' => $product->product_name,
                    'qty' => (int) $product->pivot->promotion_product_qty,
                    'discount_percent' => (float) $product->pivot->promotion_product_discount_percent,
                ])->all()
            ),
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof Promotion
            ? $this->resource->getAttribute($modelField)
            : ($this->resource->{$listField} ?? null);
    }

    private function dateValue(string $listField, string $modelField): ?string
    {
        $value = $this->value($listField, $modelField);

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
    }
}
