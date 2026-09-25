<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: trx_detail
 */
class TrxDetail extends Model
{
    protected $table = 'trx_detail';

    protected $primaryKey = 'trx_detail_id';

    public $timestamps = false;

    protected $guarded = [];

    public function trx()
    {
        return $this->belongsTo(Trx::class, 'trx_detail_trx_id', 'trx_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'trx_detail_product_id', 'product_id');
    }
}