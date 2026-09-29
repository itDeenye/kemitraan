<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Model untuk tabel: return
 */
class ReturnModel extends Model
{
    protected $table = 'return';

    protected $primaryKey = 'return_id';

    public $timestamps = false;

    protected $fillable = [
        'return_code',
        'return_goods_receive_id',
        'return_member_id',
        'return_member_address_id',
        'return_pickup_name',
        'return_pickup_phone',
        'return_pickup_address',
        'return_pickup_province_id',
        'return_pickup_city_id',
        'return_pickup_district_id',
        'return_pickup_subdistrict_id',
        'return_description',
        'return_attachment_image_url_json',
        'return_attachment_video_url',
        'return_status',
        'return_shipping_cost_bearer',
        'return_shipping_method',
        'return_shipping_cost',
        'return_approved_by',
        'return_approved_datetime',
        'return_received_by',
        'return_received_datetime',
        'return_replacement_shipping_method',
        'return_replacement_shipping_cost',
        'return_replacement_shipped_by',
        'return_replacement_shipped_datetime',
        'return_completed_datetime',
        'return_created_datetime',
    ];

    protected function casts(): array
    {
        return [
            'return_attachment_image_url_json' => 'array',
            'return_goods_receive_id' => 'integer',
            'return_shipping_cost' => 'integer',
            'return_replacement_shipping_cost' => 'integer',
            'return_approved_datetime' => 'datetime',
            'return_received_datetime' => 'datetime',
            'return_replacement_shipped_datetime' => 'datetime',
            'return_completed_datetime' => 'datetime',
            'return_created_datetime' => 'datetime',
        ];
    }

    public function trx(): HasOneThrough
    {
        return $this->hasOneThrough(
            Trx::class,
            GoodsReceive::class,
            'goods_receive_id',
            'trx_id',
            'return_goods_receive_id',
            'goods_receive_trx_id',
        );
    }

    public function goodsReceive(): BelongsTo
    {
        return $this->belongsTo(
            GoodsReceive::class,
            'return_goods_receive_id',
            'goods_receive_id'
        );
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'return_member_id', 'member_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ReturnDetail::class, 'return_detail_return_id', 'return_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ReturnStatusLog::class, 'return_status_log_return_id', 'return_id')
            ->oldest('return_status_log_id');
    }

    public function shippingExpress(): HasOne
    {
        return $this->hasOne(
            ShippingCourierExpress::class,
            'shipping_courier_express_ref_id',
            'return_id'
        )->where('shipping_courier_express_ref_type', 'return_company')
            ->latestOfMany('shipping_courier_express_id');
    }

    public function replacementShippingExpress(): HasOne
    {
        return $this->hasOne(
            ShippingCourierExpress::class,
            'shipping_courier_express_ref_id',
            'return_id'
        )->where('shipping_courier_express_ref_type', 'return_replacement')
            ->latestOfMany('shipping_courier_express_id');
    }

    public function shippingManual(): HasOne
    {
        return $this->hasOne(
            ShippingCourierManual::class,
            'shipping_courier_manual_ref_id',
            'return_id'
        )->where('shipping_courier_manual_ref_type', 'return_company')
            ->latestOfMany('shipping_courier_manual_id');
    }

    public function replacementShippingManual(): HasOne
    {
        return $this->hasOne(
            ShippingCourierManual::class,
            'shipping_courier_manual_ref_id',
            'return_id'
        )->where('shipping_courier_manual_ref_type', 'return_replacement')
            ->latestOfMany('shipping_courier_manual_id');
    }

    public function shippingInstant(): HasOne
    {
        return $this->hasOne(
            ShippingCourierInstant::class,
            'shipping_courier_instant_ref_id',
            'return_id'
        )->where('shipping_courier_instant_ref_type', 'return_company')
            ->latestOfMany('shipping_courier_instant_id');
    }

    public function replacementShippingInstant(): HasOne
    {
        return $this->hasOne(
            ShippingCourierInstant::class,
            'shipping_courier_instant_ref_id',
            'return_id'
        )->where('shipping_courier_instant_ref_type', 'return_replacement')
            ->latestOfMany('shipping_courier_instant_id');
    }

    public function shippingPickup(): HasOne
    {
        return $this->hasOne(
            ShippingPickup::class,
            'shipping_pickup_ref_id',
            'return_id'
        )->where('shipping_pickup_ref_type', 'return_company')
            ->latestOfMany('shipping_pickup_id');
    }

    public function replacementShippingPickup(): HasOne
    {
        return $this->hasOne(
            ShippingPickup::class,
            'shipping_pickup_ref_id',
            'return_id'
        )->where('shipping_pickup_ref_type', 'return_replacement')
            ->latestOfMany('shipping_pickup_id');
    }
}
