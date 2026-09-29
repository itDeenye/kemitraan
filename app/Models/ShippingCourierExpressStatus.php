<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: shipping_courier_express_status
 */
class ShippingCourierExpressStatus extends Model
{
    protected $table = 'shipping_courier_express_status';

    protected $primaryKey = 'shipping_courier_express_status_id';

    public $timestamps = false;

    protected $guarded = [];
}