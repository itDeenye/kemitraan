<?php

namespace App\Services\Product;

use App\Libraries\DataTable;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminPromotionService
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function promotions(array $params): array
    {
        return DataTable::select([
            'promotion.promotion_id as id',
            'promotion.promotion_name as name',
            'promotion.promotion_type as type',
            'promotion.promotion_value as value',
            'promotion.promotion_start_date as start_date',
            'promotion.promotion_end_date as end_date',
            'promotion.promotion_terms as terms',
            'promotion.promotion_is_active as is_active',
        ])
            ->selectRaw('(SELECT COUNT(*) FROM promotion_product WHERE promotion_product.promotion_product_promotion_id = promotion.promotion_id) as product_count')
            ->from('promotion')
            ->search(['name', 'terms'])
            ->defaultSort('-id')
            ->get($params);
    }

    public function promotion(Promotion $promotion): Promotion
    {
        return Promotion::query()
            ->with(['products' => fn ($query) => $query->select([
                (new Product)->qualifyColumn('product_id'),
                (new Product)->qualifyColumn('product_code'),
                (new Product)->qualifyColumn('product_name'),
            ])])
            ->withCount('products')
            ->findOrFail($promotion->getKey());
    }

    /** @param array<string, mixed> $data */
    public function createPromotion(array $data): Promotion
    {
        return DB::transaction(function () use ($data): Promotion {
            $promotion = Promotion::query()->create($this->promotionAttributes($data) + [
                'promotion_is_active' => 1,
            ]);
            $promotion->products()->sync($this->productAssignments($data['products']));

            return $this->promotion($promotion);
        });
    }

    /** @param array<string, mixed> $data */
    public function updatePromotion(Promotion $promotion, array $data): Promotion
    {
        return DB::transaction(function () use ($promotion, $data): Promotion {
            $promotion->update($this->promotionAttributes($data));
            $promotion->products()->sync($this->productAssignments($data['products']));

            return $this->promotion($promotion->refresh());
        });
    }

    public function deletePromotion(Promotion $promotion): void
    {
        DB::transaction(function () use ($promotion): void {
            $promotion->products()->detach();
            $promotion->delete();
        });
    }

    /** @param array<string, mixed> $data */
    private function promotionAttributes(array $data): array
    {
        $attributes = [
            'promotion_name' => $data['name'],
            'promotion_type' => $data['type'],
            'promotion_value' => $data['value'],
            'promotion_start_date' => $data['start_date'],
            'promotion_end_date' => $data['end_date'],
            'promotion_terms' => $data['terms'] ?? null,
        ];

        if (array_key_exists('is_active', $data)) {
            $attributes['promotion_is_active'] = $data['is_active'];
        }

        return $attributes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, int|float>>
     */
    private function productAssignments(array $products): array
    {
        $assignments = [];

        foreach ($products as $product) {
            $assignments[(int) $product['product_id']] = [
                'promotion_product_qty' => (int) ($product['qty'] ?? 1),
                'promotion_product_discount_percent' => (float) ($product['discount_percent'] ?? 0),
            ];
        }

        return $assignments;
    }
}
