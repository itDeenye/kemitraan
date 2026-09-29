<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberStockAdjustmentDetail extends Model
{
    protected $table = 'member_stock_adjustment_detail';

    protected $primaryKey = 'stock_adjustment_detail_id';

    public $timestamps = false;

    protected $fillable = [
        'stock_adjustment_detail_stock_adjustment_id',
        'stock_adjustment_detail_stock_member_id',
        'stock_adjustment_detail_product_id',
        'stock_adjustment_detail_type',
        'stock_adjustment_detail_qty',
        'stock_adjustment_detail_current_price',
        'stock_adjustment_detail_note',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(
            MemberStockAdjustment::class,
            'stock_adjustment_detail_stock_adjustment_id',
            'stock_adjustment_id',
        );
    }

    public function memberStock(): BelongsTo
    {
        return $this->belongsTo(
            MemberStock::class,
            'stock_adjustment_detail_stock_member_id',
            'member_stock_id',
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'stock_adjustment_detail_product_id', 'product_id');
    }

    protected function casts(): array
    {
        return [
            'stock_adjustment_detail_qty' => 'integer',
            'stock_adjustment_detail_current_price' => 'integer',
        ];
    }
}
