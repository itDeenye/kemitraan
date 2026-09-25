<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model untuk tabel: shipping_courier_instant
 */
class ShippingCourierInstant extends Model
{
    protected $table = 'shipping_courier_instant';

    protected $primaryKey = 'shipping_courier_instant_id';

    public $timestamps = false;

    protected $fillable = [
        'shipping_courier_instant_ref_type',
        'shipping_courier_instant_ref_id',
        'shipping_courier_instant_type',
        'shipping_courier_instant_expedition_name',
        'shipping_courier_instant_expedition_service',
        'shipping_courier_instant_expedition_vehicle',
        'shipping_courier_instant_estimation_hours',
        'shipping_courier_instant_order_id',
        'shipping_courier_instant_awb',
        'shipping_courier_instant_delivery_note_number',
        'shipping_courier_instant_admin_fee',
        'shipping_courier_instant_cost',
        'shipping_courier_instant_insurance',
        'shipping_courier_instant_package_weight',
        'shipping_courier_instant_origin_name',
        'shipping_courier_instant_origin_phone',
        'shipping_courier_instant_origin_address',
        'shipping_courier_instant_origin_address_note',
        'shipping_courier_instant_origin_latitude',
        'shipping_courier_instant_origin_longitude',
        'shipping_courier_instant_destination_name',
        'shipping_courier_instant_destination_phone',
        'shipping_courier_instant_destination_address',
        'shipping_courier_instant_destination_address_note',
        'shipping_courier_instant_destination_latitude',
        'shipping_courier_instant_destination_longitude',
    ];

    protected function casts(): array
    {
        return [
            'shipping_courier_instant_ref_id' => 'integer',
            'shipping_courier_instant_admin_fee' => 'integer',
            'shipping_courier_instant_cost' => 'integer',
            'shipping_courier_instant_insurance' => 'integer',
            'shipping_courier_instant_package_weight' => 'integer',
            'shipping_courier_instant_origin_latitude' => 'decimal:8',
            'shipping_courier_instant_origin_longitude' => 'decimal:8',
            'shipping_courier_instant_destination_latitude' => 'decimal:8',
            'shipping_courier_instant_destination_longitude' => 'decimal:8',
        ];
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(
            ShippingCourierInstantStatus::class,
            'shipping_courier_instant_status_shipping_courier_instant_id',
            'shipping_courier_instant_id'
        );
    }

    public function details(): HasMany
    {
        return $this->hasMany(ShippingDetail::class, 'shipping_detail_shipping_id', 'shipping_courier_instant_id')
            ->where('shipping_detail_shipping_type', 'courier_instant');
    }

    public function latestStatus(): HasOne
    {
        return $this->hasOne(
            ShippingCourierInstantStatus::class,
            'shipping_courier_instant_status_shipping_courier_instant_id',
            'shipping_courier_instant_id'
        )->latestOfMany('shipping_courier_instant_status_id');
    }
}
