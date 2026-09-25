<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    protected $table = 'product_price';

    protected $primaryKey = 'product_price_id';

    public const CREATED_AT = 'product_price_created_datetime';

    public const UPDATED_AT = 'product_price_updated_datetime';

    protected $fillable = [
        'product_price_product_id',
        'product_price_member_level_id',
        'product_price_value',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_price_product_id', 'product_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'product_price_member_level_id', 'member_level_id');
    }

    protected function casts(): array
    {
        return [
            'product_price_value' => 'integer',
            'product_price_created_datetime' => 'datetime',
            'product_price_updated_datetime' => 'datetime',
        ];
    }
}
