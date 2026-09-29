<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: shipping_courier_manual_status
 */
class ShippingCourierManualStatus extends Model
{
    protected $table = 'shipping_courier_manual_status';

    protected $primaryKey = 'shipping_courier_manual_status_id';

    public $timestamps = false;

    protected $guarded = [];
}