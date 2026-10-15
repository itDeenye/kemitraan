<?php

namespace App\Models;

use App\Support\PointCalculation;
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

    protected $attributes = [
        'trx_detail_product_monthly_points' => 1,
        'trx_detail_product_annual_points' => 1,
    ];

    public function monthlyPoints(): int
    {
        return PointCalculation::multiply((int) $this->trx_detail_qty, (int) $this->trx_detail_product_monthly_points);
    }

    public function annualPoints(): int
    {
        return PointCalculation::multiply((int) $this->trx_detail_qty, (int) $this->trx_detail_product_annual_points);
    }

    protected function casts(): array
    {
        return [
            'trx_detail_product_monthly_points' => 'integer',
            'trx_detail_product_annual_points' => 'integer',
        ];
    }

    public function trx()
    {
        return $this->belongsTo(Trx::class, 'trx_detail_trx_id', 'trx_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'trx_detail_product_id', 'product_id');
    }
}
