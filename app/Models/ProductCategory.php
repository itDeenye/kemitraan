<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model untuk tabel: product_category
 */
class ProductCategory extends Model
{
    protected $table = 'product_category';

    protected $primaryKey = 'product_category_id';

    public $timestamps = false;

    protected $fillable = [
        'product_category_name',
        'product_category_description',
        'product_category_is_active',
        'product_category_sort_order',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_product_category_id', 'product_category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('product_category_is_active', 1);
    }

    protected function casts(): array
    {
        return [
            'product_category_is_active' => 'boolean',
            'product_category_sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductCategory $category): void {
            if ((int) $category->product_category_sort_order > 0) {
                return;
            }

            $category->product_category_sort_order = ((int) static::query()
                ->max('product_category_sort_order')) + 1;
        });
    }
}
