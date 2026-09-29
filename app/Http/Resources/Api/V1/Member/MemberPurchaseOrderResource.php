<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\Api\V1\Member\Concerns\BuildsMemberPreorder;
use App\Http\Resources\Api\V1\Member\Concerns\BuildsMemberTracking;
use App\Http\Resources\ApiResource;
use App\Models\Trx;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class MemberPurchaseOrderResource extends ApiResource
{
    use BuildsMemberPreorder, BuildsMemberTracking;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $trx = $this->resource instanceof Trx ? $this->resource : null;
        $status = (string) ($trx?->trx_status ?? $this->resource->status);
        $shippingMethod = (string) ($trx?->trx_shipping_method ?? $this->resource->shipping_method);
        $sellerType = (string) ($trx?->trx_seller_type ?? $this->resource->seller_type);
        $isPreorder = (bool) ($trx?->trx_is_preorder ?? $this->resource->is_preorder);
        $parentTransactionId = (int) ($trx?->trx_parent_trx_id
            ?? $this->resource->parent_transaction_id);
        $pickupStatus = $trx?->shippingPickup?->latestStatus?->shipping_pickup_status_value
            ?? ($this->resource->pickup_status ?? null);
        $shippingStatus = match ($shippingMethod) {
            'courier_express' => $trx?->shippingExpress?->latestStatus
                ?->shipping_courier_express_status_value ?? ($this->resource->express_status ?? null),
            'courier_instant' => $trx?->shippingInstant?->latestStatus
                ?->shipping_courier_instant_status_value ?? ($this->resource->instant_status ?? null),
            'pickup' => $pickupStatus,
            default => null,
        };
        $previousStepApproved = $this->previousStepApproved($trx);

        return [
            'id' => (int) ($trx?->trx_id ?? $this->resource->id),
            'code' => $trx?->trx_code ?? $this->resource->code,
            'type' => $trx?->trx_type ?? $this->resource->order_type,
            'parent_transaction_id' => $parentTransactionId,
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
            // Alias kompatibilitas lintas order. Pembelian tidak mempunyai pelanggan retail.
            'customer' => null,
            'summary' => $trx ? $this->detailSummary($trx) : [
                'product_count' => (int) $this->resource->product_count,
                'total_quantity' => (int) $this->resource->total_quantity,
                'preorder_quantity' => (int) $this->resource->preorder_quantity,
                'product_total' => (int) $this->resource->product_total,
                'discount_percent' => (int) $this->resource->discount_percent,
                'product_discount' => (int) $this->resource->total_discount,
                'voucher_id' => (int) $this->resource->voucher_id,
                'voucher_discount' => (int) $this->resource->voucher_value,
                'total_discount' => (int) $this->resource->total_discount,
                'after_discount' => (int) $this->resource->after_discount,
                'shipping_cost' => (int) $this->resource->shipping_cost,
                'shipping_cost_insurance' => (int) ($this->resource->shipping_cost_insurance ?? 0),
                'shipping_cost_total' => (int) ($this->resource->shipping_cost_total
                    ?? $this->resource->shipping_cost),
                'payment_charge' => (int) $this->resource->payment_charge,
                'grand_total' => (int) $this->resource->grand_total,
                'bill_amount' => (int) $this->resource->bill_amount,
            ],
            'product_preview' => $this->productPreview($trx),
            'payment_method' => $trx?->trx_payment_method ?? $this->resource->payment_method,
            'shipping_method' => $shippingMethod,
            'status' => [
                'code' => $status,
                'label' => $this->statusLabel($status),
            ],
            'stock_screening' => $trx && $status === 'waiting_stock_screening' ? [
                'status' => 'pending',
                'requested_at' => $trx->trx_status_datetime?->toAtomString(),
            ] : null,
            'actions' => $this->actions(
                $status,
                $shippingMethod,
                $pickupStatus,
                $shippingStatus,
                $sellerType,
                $previousStepApproved,
                $isPreorder,
                $parentTransactionId,
            ),
            'payment_status' => $trx
                ? $trx->paymentTransfer?->payment_transfer_approval_status
                : $this->resource->payment_status,
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
                : $this->listPayment(),
            'shipping' => $this->when(
                $trx !== null,
                fn (): ?array => $this->shipping(
                    $this->preorderFulfillmentTransaction($trx) ?? $trx,
                    ! $isPreorder || $parentTransactionId === 0,
                ),
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
        $type = $trx?->trx_seller_type ?? $this->resource->seller_type;
        if ($trx) {
            $seller = $type === 'warehouse' ? $trx->sellerWarehouse : $trx->seller;
            $originTransaction = $this->preorderFulfillmentTransaction($trx) ?? $trx;
            $originSeller = $originTransaction->trx_seller_type === 'warehouse'
                ? $originTransaction->sellerWarehouse
                : $originTransaction->seller;

            return [
                'type' => $type,
                'id' => (int) $trx->trx_seller_id,
                'code' => $type === 'warehouse' ? null : $seller?->member_code,
                'name' => $type === 'warehouse' ? $seller?->warehouse_name : $seller?->member_name,
                'origin' => $this->shippingLocation($originTransaction, 'origin')
                    ?? $this->partyLocation($originSeller)
                    ?? $this->partyLocation($seller),
            ];
        }

        return [
            'type' => $type,
            'id' => (int) $this->resource->seller_id,
            'code' => $type === 'warehouse' ? null : $this->resource->seller_code,
            'name' => $type === 'warehouse'
                ? $this->resource->warehouse_name
                : $this->resource->seller_name,
            'origin' => $this->listShippingLocation('origin')
                ?? $this->listPartyLocation('seller'),
        ];
    }

    /** @return array<string, mixed> */
    private function buyer(?Trx $trx): array
    {
        if ($trx) {
            $originTrx = $this->preorderOriginTransaction($trx) ?? $trx;
            $fulfillmentTrx = $this->preorderFulfillmentTransaction($trx) ?? $trx;

            return [
                'type' => 'member',
                'id' => (int) $trx->trx_buyer_id,
                'code' => $trx->buyer?->member_code,
                'name' => $trx->buyer?->member_name,
                'destination' => $this->shippingLocation($fulfillmentTrx, 'destination')
                    ?? $this->partyLocation($originTrx->buyer),
            ];
        }

        return [
            'type' => 'member',
            'id' => (int) ($this->resource->buyer_id ?? 0),
            'code' => $this->resource->buyer_code ?? null,
            'name' => $this->resource->buyer_name ?? null,
            'destination' => $this->listShippingLocation('destination')
                ?? $this->listPartyLocation('buyer'),
        ];
    }

    /** @return array<string, mixed>|null */
    private function shippingLocation(?Trx $trx, string $side): ?array
    {
        if (! $trx) {
            return null;
        }

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

            $isWarehouse = $trx->trx_seller_type === 'warehouse';
            $seller = $isWarehouse ? $trx->sellerWarehouse : $trx->seller;

            return [
                'name' => $shipping->shipping_pickup_seller_name ?: ($isWarehouse ? $seller?->warehouse_name : $seller?->member_name),
                'phone' => $shipping->shipping_pickup_seller_mobilephone ?: ($isWarehouse ? $seller?->warehouse_phone : $seller?->member_mobilephone),
                'address' => $shipping->shipping_pickup_seller_address ?: ($isWarehouse ? $seller?->warehouse_address : $seller?->member_address),
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
            'subdistrict' => [
                'id' => $value('subdistrict_id'),
                'name' => $value('subdistrict_name'),
            ],
            'district' => ['name' => $value('district_name')],
            'city' => ['name' => $value('city_name')],
            'province' => ['name' => $value('province_name')],
            'postal_code' => $value('zipcode'),
            'note' => $value('address_note'),
            'latitude' => $value('latitude'),
            'longitude' => $value('longitude'),
        ];
    }

    /** @return array<string, mixed>|null */
    private function partyLocation(mixed $party): ?array
    {
        if (! $party) {
            return null;
        }

        return [
            'name' => $party->member_name ?? $party->warehouse_name ?? null,
            'phone' => $party->member_mobilephone ?? $party->warehouse_phone ?? null,
            'address' => $party->member_address ?? $party->warehouse_address ?? null,
            'subdistrict' => [
                'id' => $party->member_subdistrict_id ?? $party->warehouse_subdistrict_id ?? null,
                'name' => $party->subdistrict?->subdistrict_name ?? null,
            ],
            'district' => [
                'id' => $party->member_district_id ?? $party->warehouse_district_id ?? null,
                'name' => $party->district?->district_name ?? null,
            ],
            'city' => [
                'id' => $party->member_city_id ?? $party->warehouse_city_id ?? null,
                'name' => $party->city?->city_name ?? null,
            ],
            'province' => [
                'id' => $party->member_province_id ?? $party->warehouse_province_id ?? null,
                'name' => $party->province?->province_name ?? null,
            ],
            'postal_code' => $party->subdistrict?->subdistrict_zip_code ?? null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function listPartyLocation(string $party): ?array
    {
        $prefix = $party === 'seller' ? 'seller_' : 'buyer_';
        $type = (string) ($this->resource->{$party.'_type'} ?? 'member');
        $name = $party === 'seller' && $type === 'warehouse'
            ? ($this->resource->warehouse_name ?? null)
            : ($this->resource->{$prefix.'name'} ?? null);
        $id = $this->resource->{$party.'_id'} ?? null;

        if (blank($name) && ! $id) {
            return null;
        }

        return [
            'name' => $name,
            'phone' => $this->resource->{$prefix.'phone'} ?? null,
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
            'type' => $type,
        ];
    }

    /** @return array<string, mixed>|null */
    private function listShippingLocation(string $side): ?array
    {
        $method = (string) ($this->resource->shipping_method ?? '');
        $suffix = match ($method) {
            'courier_express' => 'express',
            'courier_instant' => 'instant',
            'courier_manual' => 'manual',
            'pickup' => 'pickup',
            default => null,
        };
        if (! $suffix) {
            return null;
        }
        $prefix = "shipment_{$side}_";
        $value = fn (string $field): mixed => $this->resource->{$prefix.$field.'_'.$suffix} ?? null;
        $location = [
            'name' => $value('name'),
            'phone' => $value('phone'),
            'address' => $value('address'),
            'subdistrict' => ['id' => $value('subdistrict_id'), 'name' => $value('subdistrict_name')],
            'district' => ['name' => $value('district_name')],
            'city' => ['name' => $value('city_name')],
            'province' => ['name' => $value('province_name')],
            'postal_code' => $value('zipcode'),
            'note' => $value('note'),
            'latitude' => $value('latitude'),
            'longitude' => $value('longitude'),
        ];

        return collect($location)->except(['subdistrict', 'district', 'city', 'province'])
            ->contains(fn ($item): bool => $item !== null && $item !== '') ? $location : null;
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

    /** @return array<string, bool> */
    private function actions(
        string $status,
        string $shippingMethod,
        ?string $pickupStatus,
        ?string $shippingStatus,
        string $sellerType,
        bool $previousStepApproved,
        bool $isPreorder,
        int $parentTransactionId,
    ): array {
        $canReceive = $status === 'received' && ($isPreorder || match ($shippingMethod) {
            'courier_express', 'courier_instant' => in_array(
                $shippingStatus,
                ['finished_packages', 'completed'],
                true,
            ),
            'pickup' => $pickupStatus === 'picked_up',
            'courier_manual' => true,
            default => false,
        });

        $canUploadPayment = $status === 'waiting_payment' && $previousStepApproved;
        $canCancel = ! $isPreorder && $parentTransactionId === 0
            && ($canUploadPayment || $status === 'waiting_stock_screening');

        return [
            'can_cancel' => $canCancel,
            'can_upload_payment' => $canUploadPayment,
            'waiting_for_stock_screening' => $status === 'waiting_stock_screening',
            'waiting_for_previous_approval' => $status === 'waiting_payment' && ! $previousStepApproved,
            'can_receive' => $canReceive,
            'requires_delivery_note_number' => $canReceive && $sellerType === 'warehouse',
            'can_show_pickup_code' => $shippingMethod === 'pickup'
                && $status === 'processing'
                && (! $isPreorder || $parentTransactionId === 0)
                && ! in_array($pickupStatus, ['picked_up', 'completed'], true),
            'can_verify_payment' => false,
            'can_ship' => false,
            'requires_tracking_number' => false,
            'requires_pickup_method' => false,
            'requires_pickup_schedule' => false,
            'requires_pickup_pin' => false,
        ];
    }

    private function previousStepApproved(?Trx $trx): bool
    {
        $parentTransactionId = (int) ($trx?->trx_parent_trx_id
            ?? $this->resource->parent_transaction_id
            ?? 0);
        if ($parentTransactionId === 0) {
            return true;
        }

        $parentPaymentStatus = $trx
            ? $trx->parent?->paymentTransfer?->payment_transfer_approval_status
            : ($this->resource->parent_payment_status ?? null);

        return $parentPaymentStatus === 'approved';
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
    private function listPayment(): ?array
    {
        if (! $this->resource->payment_id) {
            return null;
        }

        $status = (string) $this->resource->payment_status;

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

    /** @return array<string, mixed>|null */
    private function shipping(Trx $trx, bool $includePickupCode): ?array
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
                'estimated_delivery' => $trx->shippingInstant->shipping_courier_instant_estimation_hours,
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
                'code' => ! $includePickupCode || in_array(
                    $trx->shippingPickup->latestStatus?->shipping_pickup_status_value,
                    ['picked_up', 'completed'],
                    true,
                ) ? null : $trx->shippingPickup->shipping_pickup_pin,
                'verification_status' => $trx->shippingPickup->latestStatus?->shipping_pickup_status_value,
                'schedule_at' => $trx->shippingPickup->shipping_pickup_schedule_datetime,
                'cost' => 0,
                'insurance' => 0,
                'shipping_cost_insurance' => 0,
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
            'rejected' => 'Pesanan Ditolak',
            'waiting_stock_screening' => 'Menunggu Screening Stok',
            'waiting_payment' => 'Menunggu Pembayaran',
            'waiting_payment_approval' => 'Menunggu Verifikasi Pembayaran',
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
