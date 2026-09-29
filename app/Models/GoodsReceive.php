<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model untuk tabel: goods_receive
 */
class GoodsReceive extends Model
{
    protected $table = 'goods_receive';

    protected $primaryKey = 'goods_receive_id';

    public $timestamps = false;

    protected $guarded = [];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'goods_receive_buyer_id', 'member_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'goods_receive_seller_id', 'member_id');
    }

    public function sellerWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'goods_receive_seller_id', 'warehouse_id');
    }

    public function trx(): BelongsTo
    {
        return $this->belongsTo(Trx::class, 'goods_receive_trx_id', 'trx_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(GoodsReceiveDetail::class, 'goods_receive_detail_receive_id', 'goods_receive_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnModel::class, 'return_goods_receive_id', 'goods_receive_id');
    }

    protected function casts(): array
    {
        return [
            'goods_receive_created_datetime' => 'datetime',
            'goods_receive_status_datetime' => 'datetime',
        ];
    }
}
