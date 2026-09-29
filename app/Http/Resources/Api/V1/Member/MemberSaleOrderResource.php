<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\Api\V1\Member\Concerns\BuildsMemberPreorder;
use App\Http\Resources\Api\V1\Member\Concerns\BuildsMemberTracking;
use App\Http\Resources\ApiResource;
use App\Models\Trx;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class MemberSaleOrderResource extends ApiResource
{
    use BuildsMemberPreorder, BuildsMemberTracking;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $trx = $this->resource instanceof Trx ? $this->resource : null;
        $status = (string) ($trx?->trx_status ?? $this->resource->status);
        $paymentMethod = (string) ($trx?->trx_payment_method ?? $this->resource->payment_method);
        $shippingMethod = (string) ($trx?->trx_shipping_method ?? $this->resource->shipping_method);
        $orderType = (string) ($trx?->trx_type ?? $this->resource->order_type);
        $buyerType = (string) ($trx?->trx_buyer_type ?? $this->resource->buyer_type ?? '');
        $isPreorder = (bool) ($trx?->trx_is_preorder ?? $this->resource->is_preorder);
        $hasChildTransaction = (bool) ($trx?->children_exists
            ?? $this->resource->has_child_transaction
            ?? false);

        return [
            'id' => (int) ($trx?->trx_id ?? $this->resource->id),
            'code' => $trx?->trx_code ?? $this->resource->code,
            'type' => $orderType,
            'parent_transaction_id' => (int) ($trx?->trx_parent_trx_id
                ?? $this->resource->parent_transaction_id),
            'is_preorder' => $isPreorder,
            'order_type' => [
                'code' => $isPreorder ? 'preorder' : 'regular',
                'label' => $isPreorder ? 'PO' : 'Reguler',
            ],
            'preorder' => $this->when(
                $trx !== null,
                fn (): ?array => $isPreorder ? $this->memberPreorder($trx) : null,
            ),
            'seller' => $this->seller($trx),
            'buyer' => $this->buyer($trx),
            // Dipertahankan sebagai alias agar frontend penjualan lama tetap kompatibel.
            'customer' => $this->customer($trx),
            'summary' => $trx ? $this->detailSummary($trx) : [
                'product_count' => (int) $this->resource->product_count,
                'total_quantity' => (int) $this->resource->total_quantity,
                'preorder_quantity' => $isPreorder
                    ? (int) $this->resource->total_quantity
                    : 0,
                'product_total' => (int) $this->resource->product_total,
                'discount_percent' => (float) $this->resource->discount_percent,
                'product_discount' => (int) $this->resource->total_discount,
                'voucher_id' => (int) $this->resource->voucher_id,
                'voucher_discount' => (int) $this->resource->voucher_value,
                'total_discount' => (int) $this->resource->total_discount,
                'after_discount' => (int) $this->resource->after_discount,
                'shipping_cost' => (int) ($this->resource->shipping_cost ?? 0),
                'shipping_cost_insurance' => (int) ($this->resource->shipping_cost_insurance ?? 0),
                'shipping_cost_total' => (int) ($this->resource->shipping_cost_total
                    ?? $this->resource->shipping_cost ?? 0),
                'payment_charge' => (int) $this->resource->payment_charge,
                'grand_total' => (int) $this->resource->grand_total,
                'bill_amount' => (int) $this->resource->bill_amount,
            ],
            'product_preview' => $this->productPreview($trx),
            'payment_method' => $paymentMethod,
            'payment_status' => $trx
                ? $trx->paymentTransfer?->payment_transfer_approval_status
                : $this->resource->payment_status,
            'shipping_method' => $shippingMethod,
            'status' => [
                'code' => $status,
                'label' => $this->statusLabel($status),
            ],
            'actions' => $this->actions(
                $status,
                $paymentMethod,
                $shippingMethod,
                $orderType,
                $buyerType,
                $hasChildTransaction,
                $isPreorder,
            ),
            'status_at' => $trx
                ? $trx->trx_status_datetime?->toAtomString()
                : $this->resource->status_at,
            'ordered_at' => $trx
                ? $trx->trx_datetime?->toAtomString()
                : $this->resource->ordered_at,
            'items' => $this->when($trx !== null, fn () => $trx->details->map(
                fn ($detail): array => [
                    'id' => (int) $detail->trx_detail_id,
                    'product' => [
                        'id' => (int) $detail->trx_detail_product_id,
                        'code' => $detail->trx_detail_product_code,
                        'name' => $detail->trx_detail_product_name,
                        'bpom_number' => $detail->trx_detail_product_bpom_number
                            ?: $detail->product?->product_bpom_number,
                        'image' => MediaUrl::publicUrl($detail->product?->product_image),
                    ],
                    'price' => (int) $detail->trx_detail_product_price,
                    'discount_percent' => (float) $detail->trx_detail_discount_percent,
                    'discount_value' => (int) $detail->trx_detail_discount_value,
                    'net_price' => (int) $detail->trx_detail_nett_price,
                    'quantity' => (int) $detail->trx_detail_qty,
                    'preorder_quantity' => (bool) $trx->trx_is_preorder
                        ? (int) $detail->trx_detail_qty
                        : 0,
                    'subtotal' => (int) $detail->trx_detail_nett_price * (int) $detail->trx_detail_qty,
                    'points' => (int) $detail->trx_detail_qty,
                ]
            )->values()),
            'payment' => $trx
                ? ($trx->paymentTransfer
                    ? new MemberPurchasePaymentResource($trx->paymentTransfer)
                    : null)
                : $this->listPayment($paymentMethod),
            'shipping' => $this->when(
                $trx !== null,
                fn (): ?array => $this->shipping($this->preorderFulfillmentTransaction($trx) ?? $trx),
            ),
            'tracking' => $this->when($trx !== null, fn (): ?array => $this->memberTracking($trx)),
        ];
    }

    private function preorderFulfillmentTransaction(Trx $trx): ?Trx
    {
        if (! $trx->trx_is_preorder || ! $trx->relationLoaded('preorderChain')) {
            return null;
        }

        /** @var Trx|null $lastTransaction */
        $lastTransaction = $trx->preorderChain->last();

        return $lastTransaction?->is($trx) ? null : $lastTransaction;
    }

    private function preorderOriginTransaction(Trx $trx): ?Trx
    {
        if (! $trx->trx_is_preorder || ! $trx->relationLoaded('preorderChain')) {
            return null;
        }

        /** @var Trx|null $firstTransaction */
        $firstTransaction = $trx->preorderChain->first();

        return $firstTransaction?->is($trx) ? null : $firstTransaction;
    }

    /** @return array<string, mixed> */
    private function seller(?Trx $trx): array
    {
        if ($trx) {
            $fulfillmentTrx = $this->preorderFulfillmentTransaction($trx) ?? $trx;
            $isWarehouse = $fulfillmentTrx->trx_seller_type === 'warehouse';
            $fulfillmentSeller = $isWarehouse
                ? $fulfillmentTrx->sellerWarehouse
                : $fulfillmentTrx->seller;

            return [
                'type' => $trx->trx_seller_type,
                'id' => (int) $trx->trx_seller_id,
                'code' => $trx->seller?->member_code,
                'name' => $trx->seller?->member_name,
                'origin' => $this->shippingLocation($fulfillmentTrx, 'origin')
                    ?? $this->partyLocation($fulfillmentSeller),
            ];
        }

        return [
            'type' => $this->resource->seller_type,
            'id' => (int) $this->resource->seller_id,
            'code' => $this->resource->seller_code,
            'name' => $this->resource->seller_name,
            'origin' => $this->listPartyLocation('seller'),
        ];
    }

    /** @return array<string, mixed> */
    private function buyer(?Trx $trx): array
    {
        if ($trx) {
            $isCustomer = $trx->trx_buyer_type === 'customer';
            $buyer = $isCustomer ? $trx->buyerCustomer : $trx->buyer;
            $originTrx = $this->preorderOriginTransaction($trx) ?? $trx;
            $recipientIsCustomer = $originTrx->trx_buyer_type === 'customer';
            $recipient = $recipientIsCustomer ? $originTrx->buyerCustomer : $originTrx->buyer;
            $fulfillmentTrx = $this->preorderFulfillmentTransaction($trx) ?? $trx;

            return [
                'type' => $trx->trx_buyer_type,
                'id' => (int) $trx->trx_buyer_id,
                'code' => $isCustomer ? null : $buyer?->member_code,
                'name' => $isCustomer ? $buyer?->customer_name : $buyer?->member_name,
                'destination' => $this->shippingLocation($fulfillmentTrx, 'destination')
                    ?? $this->partyLocation($recipient, $recipientIsCustomer),
            ];
        }

        $isCustomer = $this->resource->buyer_type === 'customer';

        return [
            'type' => $this->resource->buyer_type,
            'id' => (int) $this->resource->customer_id,
            'code' => $isCustomer ? null : $this->resource->buyer_member_code,
            'name' => $isCustomer
                ? $this->resource->customer_name
                : $this->resource->buyer_member_name,
            'destination' => $this->listPartyLocation('buyer', $isCustomer),
        ];
    }

    /** @return array<string, mixed> */
    private function customer(?Trx $trx): array
    {
        if ($trx) {
            if ($trx->trx_buyer_type !== 'customer') {
                return [
                    'id' => (int) $trx->trx_buyer_id,
                    'name' => $trx->buyer?->member_name,
                    'whatsapp' => $trx->buyer?->member_mobilephone,
                    'phone' => $trx->buyer?->member_mobilephone,
                    'address' => $trx->buyer?->member_address,
                ];
            }

            return [
                'id' => (int) $trx->trx_buyer_id,
                'name' => $trx->buyerCustomer?->customer_name,
                'whatsapp' => $trx->buyerCustomer?->customer_whatsapp,
                'phone' => $trx->buyerCustomer?->customer_phone,
                'address' => $trx->buyerCustomer?->customer_address,
            ];
        }

        return [
            'id' => (int) $this->resource->customer_id,
            'name' => $this->resource->buyer_type === 'customer'
                ? $this->resource->customer_name
                : $this->resource->buyer_member_name,
            'whatsapp' => $this->resource->buyer_type === 'customer'
                ? $this->resource->customer_whatsapp
                : $this->resource->buyer_member_whatsapp,
        ];
    }

    /** @return array<string, int> */
    private function detailSummary(Trx $trx): array
    {
        $productDiscount = (int) $trx->details->sum(
            fn ($detail): int => (int) $detail->trx_detail_discount_value
                * (int) $detail->trx_detail_qty
        );

        return [
            'product_count' => $trx->details->count(),
            'total_quantity' => (int) $trx->details->sum('trx_detail_qty'),
            'preorder_quantity' => (bool) $trx->trx_is_preorder
                ? (int) $trx->details->sum('trx_detail_qty')
                : 0,
            'product_total' => (int) $trx->trx_total_price,
            'discount_percent' => (float) $trx->trx_discount,
            'product_discount' => $productDiscount,
            'voucher_id' => (int) $trx->trx_voucher_id,
            'voucher_discount' => (int) $trx->trx_voucher_value,
            'total_discount' => (int) $trx->trx_discount_value,
            'after_discount' => (int) $trx->trx_grand_total_price,
            'shipping_cost' => $this->shippingBaseCost($trx),
            'shipping_cost_insurance' => $this->shippingInsurance($trx),
            'shipping_cost_total' => (int) $trx->trx_shipping_cost,
            'payment_charge' => (int) $trx->trx_payment_charge,
            'grand_total' => (int) $trx->trx_grand_total_nett_price,
            'bill_amount' => (int) $trx->trx_bill_amount,
        ];
    }

    /** @return array<string, mixed>|null */
    private function productPreview(?Trx $trx): ?array
    {
        if ($trx) {
            $detail = $trx->details->sortBy('trx_detail_id')->first();
            if (! $detail) {
                return null;
            }

            return [
                'id' => (int) $detail->trx_detail_product_id,
                'code' => $detail->trx_detail_product_code,
                'name' => $detail->trx_detail_product_name,
                'bpom_number' => $detail->trx_detail_product_bpom_number
                    ?: $detail->product?->product_bpom_number,
                'image' => MediaUrl::publicUrl($detail->product?->product_image),
                'price' => (int) $detail->trx_detail_nett_price,
                'quantity' => (int) $detail->trx_detail_qty,
                'subtotal' => (int) $detail->trx_detail_nett_price * (int) $detail->trx_detail_qty,
                'other_product_count' => max($trx->details->count() - 1, 0),
            ];
        }

        if (! $this->resource->preview_product_id) {
            return null;
        }

        return [
            'id' => (int) $this->resource->preview_product_id,
            'code' => $this->resource->preview_product_code,
            'name' => $this->resource->preview_product_name,
            'bpom_number' => $this->resource->preview_product_bpom_number,
            'image' => MediaUrl::publicUrl($this->resource->preview_product_image),
            'price' => (int) $this->resource->preview_product_price,
            'quantity' => (int) $this->resource->preview_product_quantity,
            'subtotal' => (int) $this->resource->preview_product_price
                * (int) $this->resource->preview_product_quantity,
            'other_product_count' => max((int) $this->resource->product_count - 1, 0),
        ];
    }

    /** @return array<string, mixed>|null */
    private function listPayment(string $paymentMethod): ?array
    {
        if ($paymentMethod !== 'transfer') {
            return null;
        }

        $status = (string) ($this->resource->payment_status ?? 'pending');

        return [
            'id' => (int) $this->resource->payment_id,
            'bank' => [
                'id' => (int) $this->resource->payment_bank_id,
                'code' => $this->resource->payment_bank_code,
                'name' => $this->resource->payment_bank_name,
                'account_name' => $this->resource->payment_account_name,
                'account_number' => $this->resource->payment_account_number,
            ],
            'bill_amount' => (int) $this->resource->payment_bill_amount,
            'amount' => (int) $this->resource->payment_amount,
            'receipt_url' => MediaUrl::temporaryPrivateUrl(
                $this->resource->payment_receipt_url,
                'member',
            ),
            'status' => [
                'code' => $status,
                'label' => match ($status) {
                    'submitted' => 'Menunggu Verifikasi',
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                    default => 'Belum Dibayar',
                },
            ],
            'note' => $this->resource->payment_note,
            'transferred_at' => $this->resource->payment_receipt_url
                ? $this->resource->payment_transferred_at
                : null,
            'verified_at' => $this->resource->payment_verified_at,
        ];
    }

    /** @return array<string, bool> */
    private function actions(
        string $status,
        string $paymentMethod,
        string $shippingMethod,
        string $orderType,
        string $buyerType,
        bool $hasChildTransaction,
        bool $isPreorder,
    ): array {
        $canVerifyPayment = $paymentMethod === 'transfer'
            && $status === 'waiting_payment_approval';
        $canShip = $status === 'processing' && ! $hasChildTransaction;
        $canCancel = $isPreorder
            ? ! $hasChildTransaction && in_array($status, [
                'waiting_stock_screening',
                'waiting_payment',
                'waiting_payment_approval',
                'processing',
            ], true)
            : $status === 'waiting_payment';

        return [
            'can_verify_payment' => $canVerifyPayment,
            'can_approve_payment' => $canVerifyPayment,
            'can_reject_payment' => $canVerifyPayment,
            'can_ship' => $canShip,
            'can_cancel' => $canCancel,
            'can_upload_payment' => false,
            'can_receive' => false,
            'can_show_pickup_code' => false,
            'allows_delivery_note_number' => $canShip && $buyerType !== 'customer',
            'requires_delivery_note_number' => false,
            'requires_tracking_number' => $canShip
                && $shippingMethod === 'courier_manual',
            'requires_pickup_method' => $canShip && $shippingMethod === 'courier_express',
            'requires_pickup_schedule' => $canShip && $shippingMethod === 'courier_express',
            'requires_pickup_pin' => $canShip
                && $shippingMethod === 'pickup'
                && $buyerType !== 'customer',
        ];
    }

    /** @return array<string, mixed>|null */
    private function shippingLocation(Trx $trx, string $side): ?array
    {
        $shipping = match ($trx->trx_shipping_method) {
            'courier_express' => $trx->shippingExpress,
            'courier_instant' => $trx->shippingInstant,
            'courier_manual' => $trx->shippingManual,
            'pickup' => $trx->shippingPickup,
            default => null,
        };
        if (! $shipping) {
            return null;
        }
        if ($trx->trx_shipping_method === 'pickup') {
            if ($side !== 'origin') {
                return null;
            }

            $seller = $trx->trx_seller_type === 'warehouse'
                ? $trx->sellerWarehouse
                : $trx->seller;

            return [
                'name' => $shipping->shipping_pickup_seller_name
                    ?: ($seller?->member_name ?? $seller?->warehouse_name),
                'phone' => $shipping->shipping_pickup_seller_mobilephone
                    ?: ($seller?->member_mobilephone ?? $seller?->warehouse_phone),
                'address' => $shipping->shipping_pickup_seller_address
                    ?: ($seller?->member_address ?? $seller?->warehouse_address),
                'subdistrict' => [
                    'id' => $seller?->member_subdistrict_id ?? $seller?->warehouse_subdistrict_id,
                    'name' => $seller?->subdistrict?->subdistrict_name,
                ],
                'district' => [
                    'id' => $seller?->member_district_id ?? $seller?->warehouse_district_id,
                    'name' => $seller?->district?->district_name,
                ],
                'city' => [
                    'id' => $seller?->member_city_id ?? $seller?->warehouse_city_id,
                    'name' => $seller?->city?->city_name,
                ],
                'province' => [
                    'id' => $seller?->member_province_id ?? $seller?->warehouse_province_id,
                    'name' => $seller?->province?->province_name,
                ],
                'postal_code' => $seller?->subdistrict?->subdistrict_zip_code,
            ];
        }

        $prefix = 'shipping_'.$trx->trx_shipping_method.'_'.$side.'_';
        $value = fn (string $field): mixed => $shipping->{$prefix.$field} ?? null;

        return [
            'name' => $value('name'),
            'phone' => $value('phone'),
            'address' => $value('address'),
            'subdistrict' => ['id' => $value('subdistrict_id'), 'name' => $value('subdistrict_name')],
            'district' => ['id' => $value('district_id'), 'name' => $value('district_name')],
            'city' => ['id' => $value('city_id'), 'name' => $value('city_name')],
            'province' => ['id' => $value('province_id'), 'name' => $value('province_name')],
            'postal_code' => $value('zipcode'),
            'note' => $value('address_note'),
            'latitude' => $value('latitude'),
            'longitude' => $value('longitude'),
        ];
    }

    /** @return array<string, mixed>|null */
    private function partyLocation(mixed $party, bool $isCustomer = false): ?array
    {
        if (! $party) {
            return null;
        }

        if ($isCustomer) {
            return [
                'name' => $party->customer_name,
                'phone' => $party->customer_phone ?: $party->customer_whatsapp,
                'address' => $party->customer_address,
                'subdistrict' => [
                    'id' => $party->customer_subdistrict_id,
                    'name' => $party->subdistrict?->subdistrict_name,
                ],
                'district' => [
                    'id' => $party->customer_district_id,
                    'name' => $party->district?->district_name,
                ],
                'city' => [
                    'id' => $party->customer_city_id,
                    'name' => $party->city?->city_name,
                ],
                'province' => [
                    'id' => $party->customer_province_id,
                    'name' => $party->province?->province_name,
                ],
                'postal_code' => $party->subdistrict?->subdistrict_zip_code,
            ];
        }

        return [
            'name' => $party->member_name,
            'phone' => $party->member_mobilephone,
            'address' => $party->member_address,
            'subdistrict' => [
                'id' => $party->member_subdistrict_id,
                'name' => $party->subdistrict?->subdistrict_name,
            ],
            'district' => [
                'id' => $party->member_district_id,
                'name' => $party->district?->district_name,
            ],
            'city' => [
                'id' => $party->member_city_id,
                'name' => $party->city?->city_name,
            ],
            'province' => [
                'id' => $party->member_province_id,
                'name' => $party->province?->province_name,
            ],
            'postal_code' => $party->subdistrict?->subdistrict_zip_code,
        ];
    }

    /** @return array<string, mixed>|null */
    private function listPartyLocation(string $party, bool $isCustomer = false): ?array
    {
        $prefix = $party === 'seller'
            ? 'seller_'
            : ($isCustomer ? 'customer_' : 'buyer_member_');
        $name = $this->resource->{$prefix.'name'} ?? null;

        if (blank($name)) {
            return null;
        }

        return [
            'name' => $name,
            'phone' => $party === 'seller'
                ? ($this->resource->seller_phone ?? null)
                : ($isCustomer
                    ? ($this->resource->customer_phone ?: $this->resource->customer_whatsapp)
                    : ($this->resource->buyer_member_whatsapp ?? null)),
            'address' => $this->resource->{$prefix.'address'} ?? null,
            'subdistrict' => [
                'id' => $this->resource->{$prefix.'subdistrict_id'} ?? null,
                'name' => $this->resource->{$prefix.'subdistrict_name'} ?? null,
            ],
            'district' => [
                'id' => $this->resource->{$prefix.'district_id'} ?? null,
                'name' => $this->resource->{$prefix.'district_name'} ?? null,
            ],
            'city' => [
                'id' => $this->resource->{$prefix.'city_id'} ?? null,
                'name' => $this->resource->{$prefix.'city_name'} ?? null,
            ],
            'province' => [
                'id' => $this->resource->{$prefix.'province_id'} ?? null,
                'name' => $this->resource->{$prefix.'province_name'} ?? null,
            ],
            'postal_code' => $this->resource->{$prefix.'postal_code'}
                ?? $this->resource->{$prefix.'zipcode'}
                ?? null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function shipping(Trx $trx): ?array
    {
        return match ($trx->trx_shipping_method) {
            'courier_express' => $trx->shippingExpress ? [
                'method' => 'courier_express',
                'courier' => $trx->shippingExpress->shipping_courier_express_expedition_name,
                'service' => $trx->shippingExpress->shipping_courier_express_expedition_service,
                'pickup_method' => $trx->shippingExpress->shipping_courier_express_pickup_method,
                'estimated_delivery' => $trx->shippingExpress->shipping_courier_express_etd,
                'delivery_note_number' => $trx->shippingExpress->shipping_courier_express_delivery_note_number,
                'items' => $this->shippingItems($trx->shippingExpress->details),
                'tracking_number' => $trx->shippingExpress->shipping_courier_express_awb,
                'delivery_status' => $trx->shippingExpress->latestStatus
                    ?->shipping_courier_express_status_value,
                'cost' => (int) $trx->shippingExpress->shipping_courier_express_cost,
                'insurance' => (int) $trx->shippingExpress->shipping_courier_express_insurance,
                'shipping_cost_insurance' => (int) $trx->shippingExpress->shipping_courier_express_insurance,
                'total_cost' => (int) $trx->shippingExpress->shipping_courier_express_cost
                    + (int) $trx->shippingExpress->shipping_courier_express_insurance,
                'destination' => $trx->shippingExpress->shipping_courier_express_destination_address,
            ] : null,
            'courier_instant' => $trx->shippingInstant ? [
                'method' => 'courier_instant',
                'courier' => $trx->shippingInstant->shipping_courier_instant_expedition_name,
                'service' => $trx->shippingInstant->shipping_courier_instant_expedition_service,
                'vehicle' => $trx->shippingInstant->shipping_courier_instant_expedition_vehicle,
                'estimated_delivery' => $trx->shippingInstant->shipping_courier_instant_estimation_hours,
                'order_id' => $trx->shippingInstant->shipping_courier_instant_order_id,
                'delivery_note_number' => $trx->shippingInstant->shipping_courier_instant_delivery_note_number,
                'items' => $this->shippingItems($trx->shippingInstant->details),
                'tracking_number' => $trx->shippingInstant->shipping_courier_instant_awb,
                'delivery_status' => $trx->shippingInstant->latestStatus
                    ?->shipping_courier_instant_status_value,
                'cost' => (int) $trx->shippingInstant->shipping_courier_instant_cost,
                'insurance' => (int) $trx->shippingInstant->shipping_courier_instant_insurance,
                'shipping_cost_insurance' => (int) $trx->shippingInstant->shipping_courier_instant_insurance,
                'admin_fee' => (int) $trx->shippingInstant->shipping_courier_instant_admin_fee,
                'total_cost' => (int) $trx->shippingInstant->shipping_courier_instant_cost
                    + (int) $trx->shippingInstant->shipping_courier_instant_admin_fee,
                'destination' => $trx->shippingInstant->shipping_courier_instant_destination_address,
            ] : null,
            'courier_manual' => $trx->shippingManual ? [
                'method' => 'courier_manual',
                'courier' => $trx->shippingManual->shipping_courier_manual_name,
                'service' => $trx->shippingManual->shipping_courier_manual_service,
                'delivery_note_number' => $trx->shippingManual->shipping_courier_manual_delivery_note_number,
                'items' => $this->shippingItems($trx->shippingManual->details),
                'tracking_number' => $trx->shippingManual->shipping_courier_manual_awb,
                'cost' => (int) $trx->shippingManual->shipping_courier_manual_price,
                'insurance' => (int) ($trx->shippingManual->shipping_courier_manual_insurance ?? 0),
                'shipping_cost_insurance' => (int) ($trx->shippingManual->shipping_courier_manual_insurance ?? 0),
                'total_cost' => (int) $trx->shippingManual->shipping_courier_manual_price
                    + (int) ($trx->shippingManual->shipping_courier_manual_insurance ?? 0),
                'destination' => $trx->shippingManual->shipping_courier_manual_destination_address,
            ] : null,
            'pickup' => $trx->shippingPickup ? [
                'method' => 'pickup',
                'location' => $trx->shippingPickup->shipping_pickup_seller_name,
                'delivery_note_number' => $trx->shippingPickup->shipping_pickup_delivery_note_number,
                'items' => $this->shippingItems($trx->shippingPickup->details),
                'address' => $trx->shippingPickup->shipping_pickup_seller_address,
                'verification_status' => $trx->shippingPickup->latestStatus?->shipping_pickup_status_value,
                'schedule_at' => $trx->shippingPickup->shipping_pickup_schedule_datetime,
                'cost' => 0,
            ] : null,
            default => null,
        };
    }

    /** @param iterable<int, mixed> $details */
    private function shippingItems(iterable $details): array
    {
        return collect($details)->map(fn ($detail): array => [
            'product_id' => (int) $detail->shipping_detail_product_id,
            'quantity' => (int) $detail->shipping_detail_qty,
            'batch_number' => $detail->shipping_detail_batch_number,
            'expiry_date' => $detail->shipping_detail_expire_date?->toDateString(),
        ])->values()->all();
    }

    private function shippingInsurance(Trx $trx): int
    {
        return match ($trx->trx_shipping_method) {
            'courier_express' => (int) ($trx->shippingExpress?->shipping_courier_express_insurance ?? 0),
            'courier_instant' => (int) ($trx->shippingInstant?->shipping_courier_instant_insurance ?? 0),
            'courier_manual' => (int) ($trx->shippingManual?->shipping_courier_manual_insurance ?? 0),
            default => 0,
        };
    }

    private function shippingBaseCost(Trx $trx): int
    {
        $base = match ($trx->trx_shipping_method) {
            'courier_express' => (int) ($trx->shippingExpress?->shipping_courier_express_cost ?? 0),
            'courier_instant' => (int) ($trx->shippingInstant?->shipping_courier_instant_cost ?? 0),
            'courier_manual' => (int) ($trx->shippingManual?->shipping_courier_manual_price ?? 0),
            default => 0,
        };

        return $base > 0 || (int) $trx->trx_shipping_cost === 0
            ? $base
            : (int) $trx->trx_shipping_cost;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'waiting_payment' => 'Menunggu Pembayaran',
            'cancelled' => 'Dibatalkan',
            'processing' => 'Diproses',
            'shipped' => 'Dikirim',
            'reship_required' => 'Perlu Dikirim Ulang',
            'ready_to_pickup' => 'Siap Diambil',
            'received' => 'Siap Diterima',
            'completed' => 'Selesai',
            default => $status,
        };
    }
}
