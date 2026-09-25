<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Model untuk tabel: promotion
 */
class Promotion extends Model
{
    protected $table = 'promotion';

    protected $primaryKey = 'promotion_id';

    public $timestamps = false;

    protected $fillable = [
        'promotion_name',
        'promotion_type',
        'promotion_value',
        'promotion_start_date',
        'promotion_end_date',
        'promotion_terms',
        'promotion_is_active',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'promotion_product',
            'promotion_product_promotion_id',
            'promotion_product_product_id'
        )->withPivot([
            'promotion_product_qty',
            'promotion_product_discount_percent',
        ]);
    }

    protected function casts(): array
    {
        return [
            'promotion_value' => 'integer',
            'promotion_start_date' => 'date',
            'promotion_end_date' => 'date',
            'promotion_is_active' => 'boolean',
        ];
    }
}
