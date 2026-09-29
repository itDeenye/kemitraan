<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model untuk tabel: shipping_pickup
 */
class ShippingPickup extends Model
{
    protected $table = 'shipping_pickup';

    protected $primaryKey = 'shipping_pickup_id';

    public $timestamps = false;

    protected $guarded = [];

    public function statuses(): HasMany
    {
        return $this->hasMany(
            ShippingPickupStatus::class,
            'shipping_pickup_status_shipping_pickup_id',
            'shipping_pickup_id',
        );
    }

    public function details(): HasMany
    {
        return $this->hasMany(ShippingDetail::class, 'shipping_detail_shipping_id', 'shipping_pickup_id')
            ->where('shipping_detail_shipping_type', 'pickup');
    }

    public function latestStatus(): HasOne
    {
        return $this->hasOne(
            ShippingPickupStatus::class,
            'shipping_pickup_status_shipping_pickup_id',
            'shipping_pickup_id',
        )->latestOfMany('shipping_pickup_status_id');
    }
}
