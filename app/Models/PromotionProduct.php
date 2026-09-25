<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: promotion_product
 */
class PromotionProduct extends Model
{
    protected $table = 'promotion_product';

    protected $primaryKey = 'promotion_product_id';

    public $timestamps = false;

    protected $fillable = [
        'promotion_product_promotion_id',
        'promotion_product_product_id',
        'promotion_product_qty',
        'promotion_product_discount_percent',
    ];

    protected function casts(): array
    {
        return [
            'promotion_product_qty' => 'integer',
            'promotion_product_discount_percent' => 'decimal:2',
        ];
    }
}
