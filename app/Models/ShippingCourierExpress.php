<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model untuk tabel: shipping_courier_express
 */
class ShippingCourierExpress extends Model
{
    protected $table = 'shipping_courier_express';

    protected $primaryKey = 'shipping_courier_express_id';

    public $timestamps = false;

    protected $guarded = [];

    public function statuses(): HasMany
    {
        return $this->hasMany(
            ShippingCourierExpressStatus::class,
            'shipping_courier_express_status_shipping_courier_express_id',
            'shipping_courier_express_id'
        );
    }

    public function details(): HasMany
    {
        return $this->hasMany(ShippingDetail::class, 'shipping_detail_shipping_id', 'shipping_courier_express_id')
            ->where('shipping_detail_shipping_type', 'courier_express');
    }

    public function latestStatus(): HasOne
    {
        return $this->hasOne(
            ShippingCourierExpressStatus::class,
            'shipping_courier_express_status_shipping_courier_express_id',
            'shipping_courier_express_id'
        )->latestOfMany('shipping_courier_express_status_id');
    }
}
