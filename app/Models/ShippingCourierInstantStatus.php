<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: shipping_courier_instant_status
 */
class ShippingCourierInstantStatus extends Model
{
    protected $table = 'shipping_courier_instant_status';

    protected $primaryKey = 'shipping_courier_instant_status_id';

    public $timestamps = false;

    protected $fillable = [
        'shipping_courier_instant_status_shipping_courier_instant_id',
        'shipping_courier_instant_status_ref_type',
        'shipping_courier_instant_status_ref_id',
        'shipping_courier_instant_status_value',
        'shipping_courier_instant_status_note',
        'shipping_courier_instant_status_datetime',
        'shipping_courier_instant_status_ref_code',
        'shipping_courier_instant_status_external_ref_code',
    ];

    protected function casts(): array
    {
        return [
            'shipping_courier_instant_status_datetime' => 'datetime',
        ];
    }
}
