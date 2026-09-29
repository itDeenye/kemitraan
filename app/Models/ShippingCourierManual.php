<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model untuk tabel: shipping_courier_manual
 */
class ShippingCourierManual extends Model
{
    protected $table = 'shipping_courier_manual';

    protected $primaryKey = 'shipping_courier_manual_id';

    public $timestamps = false;

    protected $guarded = [];

    public function statuses(): HasMany
    {
        return $this->hasMany(
            ShippingCourierManualStatus::class,
            'shipping_courier_manual_status_shipping_courier_manual_id',
            'shipping_courier_manual_id',
        );
    }

    public function details(): HasMany
    {
        return $this->hasMany(ShippingDetail::class, 'shipping_detail_shipping_id', 'shipping_courier_manual_id')
            ->where('shipping_detail_shipping_type', 'courier_manual');
    }

    public function latestStatus(): HasOne
    {
        return $this->hasOne(
            ShippingCourierManualStatus::class,
            'shipping_courier_manual_status_shipping_courier_manual_id',
            'shipping_courier_manual_id',
        )->latestOfMany('shipping_courier_manual_status_id');
    }
}
