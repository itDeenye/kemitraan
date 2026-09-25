<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model untuk tabel: trx
 */
class Trx extends Model
{
    protected $table = 'trx';

    protected $primaryKey = 'trx_id';

    public $timestamps = false;

    protected $fillable = [
        'trx_code',
        'trx_parent_trx_id',
        'trx_is_preorder',
        'trx_seller_type',
        'trx_seller_id',
        'trx_buyer_type',
        'trx_buyer_id',
        'trx_type',
        'trx_reference_id',
        'trx_total_price',
        'trx_discount',
        'trx_discount_value',
        'trx_voucher_id',
        'trx_voucher_value',
        'trx_grand_total_price',
        'trx_shipping_cost',
        'trx_payment_charge',
        'trx_grand_total_nett_price',
        'trx_bill_remaining',
        'trx_bill_augment',
        'trx_bill_amount',
        'trx_payment_method',
        'trx_shipping_method',
        'trx_status',
        'trx_status_datetime',
        'trx_datetime',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'trx_buyer_id', 'member_id');
    }

    public function buyerCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'trx_buyer_id', 'customer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'trx_seller_id', 'member_id');
    }

    public function sellerWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'trx_seller_id', 'warehouse_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Trx::class, 'trx_parent_trx_id', 'trx_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Trx::class, 'trx_parent_trx_id', 'trx_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(RewardStockist::class, 'trx_voucher_id', 'reward_stockist_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TrxDetail::class, 'trx_detail_trx_id', 'trx_id');
    }

    public function paymentTransfer(): HasOne
    {
        return $this->hasOne(TrxPaymentTransfer::class, 'payment_transfer_trx_id', 'trx_id');
    }

    public function returns(): HasManyThrough
    {
        return $this->hasManyThrough(
            ReturnModel::class,
            GoodsReceive::class,
            'goods_receive_trx_id',
            'return_goods_receive_id',
            'trx_id',
            'goods_receive_id',
        );
    }

    public function goodsReceives(): HasMany
    {
        return $this->hasMany(GoodsReceive::class, 'goods_receive_trx_id', 'trx_id');
    }

    public function spreadPayments(): HasMany
    {
        return $this->hasMany(TrxSpreadPayment::class, 'trx_spread_payment_trx_id', 'trx_id');
    }

    public function shippingExpress(): HasOne
    {
        return $this->hasOne(
            ShippingCourierExpress::class,
            'shipping_courier_express_ref_id',
            'trx_id'
        )->where('shipping_courier_express_ref_type', 'trx');
    }

    public function shippingInstant(): HasOne
    {
        return $this->hasOne(
            ShippingCourierInstant::class,
            'shipping_courier_instant_ref_id',
            'trx_id'
        )->where('shipping_courier_instant_ref_type', 'trx');
    }

    public function shippingManual(): HasOne
    {
        return $this->hasOne(
            ShippingCourierManual::class,
            'shipping_courier_manual_ref_id',
            'trx_id'
        )->where('shipping_courier_manual_ref_type', 'trx');
    }

    public function shippingPickup(): HasOne
    {
        return $this->hasOne(ShippingPickup::class, 'shipping_pickup_ref_id', 'trx_id')
            ->where('shipping_pickup_ref_type', 'trx');
    }

    protected function casts(): array
    {
        return [
            'trx_is_preorder' => 'boolean',
            'trx_discount' => 'decimal:0',
            'trx_total_price' => 'integer',
            'trx_discount_value' => 'integer',
            'trx_voucher_id' => 'integer',
            'trx_voucher_value' => 'integer',
            'trx_grand_total_price' => 'integer',
            'trx_shipping_cost' => 'integer',
            'trx_payment_charge' => 'integer',
            'trx_grand_total_nett_price' => 'integer',
            'trx_bill_remaining' => 'integer',
            'trx_bill_augment' => 'integer',
            'trx_bill_amount' => 'integer',
            'trx_status_datetime' => 'datetime',
            'trx_datetime' => 'datetime',
        ];
    }
}
