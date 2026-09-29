<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk tabel: return_detail
 */
class ReturnDetail extends Model
{
    protected $table = 'return_detail';

    protected $primaryKey = 'return_detail_id';

    public $timestamps = false;

    protected $fillable = [
        'return_detail_return_id',
        'return_detail_goods_receive_detail_id',
        'return_detail_product_id',
        'return_detail_qty',
        'return_detail_received_qty',
        'return_detail_reason',
    ];

    public function returnModel(): BelongsTo
    {
        return $this->belongsTo(ReturnModel::class, 'return_detail_return_id', 'return_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'return_detail_product_id', 'product_id');
    }

    public function goodsReceiveDetail(): BelongsTo
    {
        return $this->belongsTo(
            GoodsReceiveDetail::class,
            'return_detail_goods_receive_detail_id',
            'goods_receive_detail_id',
        );
    }

    protected function casts(): array
    {
        return [
            'return_detail_goods_receive_detail_id' => 'integer',
            'return_detail_qty' => 'integer',
            'return_detail_received_qty' => 'integer',
        ];
    }
}
