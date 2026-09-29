<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model untuk tabel: product
 */
class Product extends Model
{
    protected $table = 'product';

    protected $primaryKey = 'product_id';

    public $timestamps = false;

    protected $fillable = [
        'product_product_category_id',
        'product_code',
        'product_name',
        'product_bpom_number',
        'product_description',
        'product_image',
        'product_image_filename',
        'product_customer_price',
        'product_weight',
        'product_length',
        'product_height',
        'product_width',
        'product_unit',
        'product_is_package',
        'product_is_publish',
        'product_is_active',
        'product_is_deleted',
        'product_input_datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_product_category_id', 'product_category_id');
    }

    public function trxDetails(): HasMany
    {
        return $this->hasMany(TrxDetail::class, 'trx_detail_product_id', 'product_id');
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(
            Promotion::class,
            'promotion_product',
            'promotion_product_product_id',
            'promotion_product_promotion_id'
        )->withPivot([
            'promotion_product_qty',
            'promotion_product_discount_percent',
        ]);
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class, 'warehouse_stock_product_id', 'product_id');
    }

    public function memberStocks(): HasMany
    {
        return $this->hasMany(MemberStock::class, 'member_stock_product_id', 'product_id');
    }

    public function levelPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_price_product_id', 'product_id');
    }

    public function scopeAvailableInCatalog(Builder $query): Builder
    {
        return $query
            ->where('product_is_publish', 1)
            ->where('product_is_active', 1)
            ->where('product_is_deleted', 0)
            ->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->active());
    }

    protected function casts(): array
    {
        return [
            'product_customer_price' => 'integer',
            'product_weight' => 'integer',
            'product_length' => 'integer',
            'product_height' => 'integer',
            'product_width' => 'integer',
            'product_is_package' => 'boolean',
            'product_is_publish' => 'boolean',
            'product_is_active' => 'boolean',
            'product_is_deleted' => 'boolean',
            'product_input_datetime' => 'datetime',
        ];
    }
}
