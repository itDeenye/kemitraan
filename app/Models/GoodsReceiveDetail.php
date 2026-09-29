<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model untuk tabel: goods_receive_detail
 */
class GoodsReceiveDetail extends Model
{
    protected $table = 'goods_receive_detail';

    protected $primaryKey = 'goods_receive_detail_id';

    public $timestamps = false;

    protected $guarded = [];

    public function goodsReceive(): BelongsTo
    {
        return $this->belongsTo(GoodsReceive::class, 'goods_receive_detail_receive_id', 'goods_receive_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'goods_receive_detail_product_id', 'product_id');
    }

    public function activeReturnDetails(): HasMany
    {
        return $this->hasMany(
            ReturnDetail::class,
            'return_detail_goods_receive_detail_id',
            'goods_receive_detail_id',
        )->whereHas(
            'returnModel',
            fn ($query) => $query->where('return_status', '!=', 'rejected'),
        );
    }

    protected function casts(): array
    {
        return [
            'goods_receive_detail_expire_date' => 'date',
            'goods_receive_detail_qty' => 'integer',
            'goods_receive_detail_created_datetime' => 'datetime',
        ];
    }
}
