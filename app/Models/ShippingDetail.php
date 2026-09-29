<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingDetail extends Model
{
    protected $table = 'shipping_detail';

    protected $primaryKey = 'shipping_detail_id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'shipping_detail_shipping_id' => 'integer',
            'shipping_detail_product_id' => 'integer',
            'shipping_detail_qty' => 'integer',
            'shipping_detail_expire_date' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'shipping_detail_product_id', 'product_id');
    }
}
