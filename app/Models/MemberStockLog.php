<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk tabel: member_stock_log
 */
class MemberStockLog extends Model
{
    protected $table = 'member_stock_log';

    protected $primaryKey = 'member_stock_log_id';

    public $timestamps = false;

    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'member_stock_log_product_id', 'product_id');
    }
}
