<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: shipping_pickup_status
 */
class ShippingPickupStatus extends Model
{
    protected $table = 'shipping_pickup_status';

    protected $primaryKey = 'shipping_pickup_status_id';

    public $timestamps = false;

    protected $guarded = [];
}