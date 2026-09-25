<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\Trx;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class AdminOrderResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $trx = $this->resource instanceof Trx ? $this->resource : null;
        $sellerType = $trx?->trx_seller_type ?? $this->resource->seller_type;
        $buyerType = $trx?->trx_buyer_type ?? $this->resource->buyer_type;
        $status = (string) ($trx?->trx_status ?? $this->resource->status);
        $shippingMethod = (string) ($trx?->trx_shipping_method ?? $this->resource->shipping_method);
        $isPreorder = (bool) ($trx?->trx_is_preorder ?? $this->resource->is_preorder ?? false);
        $hasChildTransaction = (bool) ($trx?->children_exists
            ?? $this->resource->has_child_transaction
            ?? false);
        $canShip = in_array($status, ['processing', 'reship_required'], true)
            && ! $hasChildTransaction;
        $fulfillmentTrx = $trx ? $this->fulfillmentTransaction($trx) : null;
        $originTrx = $trx ? $this->originTransaction($trx) : null;
        $trackingTrx = $trx ? $this->trackingTransaction($trx) : null;
        $trackingShippingMethod = (string) ($trackingTrx?->trx_shipping_method ?? $shippingMethod);
        $trackingStatus = (string) ($trackingTrx?->trx_status ?? $status);
        $shippingOrderId = $trackingTrx
            ? match ($trackingShippingMethod) {
                'courier_express' => $trackingTrx->shippingExpress?->shipping_courier_express_order_id,
                'courier_instant' => $trackingTrx->shippingInstant?->shipping_courier_instant_order_id,
                default => null,
            }
        : ($this->resource->shipping_order_id ?? null);
        $shippingTrackingNumber = $trackingTrx
            ? match ($trackingShippingMethod) {
                'courier_express' => $trackingTrx->shippingExpress?->shipping_courier_express_awb,
                'courier_instant' => $trackingTrx->shippingInstant?->shipping_courier_instant_awb,
                default => null,
            }
        : ($this->resource->shipping_tracking_number ?? null);
        $isStcShipping = in_array($trackingShippingMethod, ['courier_express', 'courier_instant'], true);
        $isExpressShipping = $trackingShippingMethod === 'courier_express';
        $canProcessStockScreening = (bool) ($trx?->getAttribute('can_approve_stock_screening')
            ?? $this->resource->can_approve_stock_screening
            ?? false);
        $shippingItemsByProduct = $trx
            ? collect($this->transactionShippingItems($fulfillmentTrx ?? $trx))->groupBy('product_id')
            : collect();

        return [
            'id' => (int) ($trx?->trx_id ?? $this->resource->id),
            'code' => $trx?->trx_code ?? $this->resource->code,
            'type' => $trx?->trx_type ?? $this->resource->type,
            'order_type' => [
                'code' => $isPreorder ? 'preorder' : 'regular',
                'label' => $isPreorder ? 'PO' : 'Reguler',
            ],
            'seller' => $this->seller($trx, $sellerType, $fulfillmentTrx),
            'buyer' => $this->buyer($trx, $buyerType, $fulfillmentTrx, $originTrx),
            'totals' => $trx ? [
                'product_total' => (int) $trx->trx_total_price,
                'discount_percent' => (int) $trx->trx_discount,
                'discount_value' => (int) $trx->trx_discount_value,
                'product_discount' => (int) $trx->trx_discount_value,
                'voucher_id' => (int) $trx->trx_voucher_id,
                'voucher_value' => (int) $trx->trx_voucher_value,
                'after_discount' => (int) $trx->trx_grand_total_price,
                'shipping_cost' => $this->shippingBaseCost($trx),
                'shipping_cost_insurance' => $this->shippingInsurance($trx),
                'shipping_cost_total' => (int) $trx->trx_shipping_cost,
                'payment_charge' => (int) $trx->trx_payment_charge,
                'grand_total' => (int) $trx->trx_grand_total_nett_price,
                'bill_amount' => (int) $trx->trx_bill_amount,
            ] : [
                'product_total' => (int) $this->resource->product_total,
                'discount_percent' => (int) $this->resource->discount_percent,
                'discount_value' => (int) $this->resource->discount_value,
                'product_discount' => (int) $this->resource->discount_value,
                'voucher_id' => (int) $this->resource->voucher_id,
                'voucher_value' => (int) $this->resource->voucher_value,
                'after_discount' => (int) $this->resource->after_discount,
                'shipping_cost' => (int) $this->resource->shipping_cost,
                'shipping_cost_insurance' => (int) ($this->resource->shipping_cost_insurance ?? 0),
                'shipping_cost_total' => (int) ($this->resource->shipping_cost_total
                    ?? $this->resource->shipping_cost),
                'payment_charge' => (int) $this->resource->payment_charge,
                'grand_total' => (int) $this->resource->grand_total,
                'bill_amount' => (int) $this->resource->bill_amount,
            ],
            'payment_method' => $trx?->trx_payment_method ?? $this->resource->payment_method,
            'shipping_method' => $shippingMethod,
            'status' => $status,
            'stock_screening' => $this->when(
                $trx !== null,
                fn (): ?array => $this->stockScreening($trx),
            ),
            'actions' => [
                'can_approve_stock_screening' => $canProcessStockScreening,
                'can_reject_stock_screening' => $canProcessStockScreening,
                'can_ship' => $canShip,
                'can_reship' => $status === 'reship_required' && ! $hasChildTransaction,
                'requires_courier_selection' => $status === 'reship_required'
                    && ! $hasChildTransaction
                    && $shippingMethod === 'courier_express',
                'requires_tracking_number' => $canShip
                    && $shippingMethod === 'courier_manual',
                'requires_pickup_method' => $canShip
                    && $shippingMethod === 'courier_express',
                'requires_pickup_schedule' => $canShip
                    && $shippingMethod === 'courier_express',
                'requires_pickup_pin' => $canShip
                    && $shippingMethod === 'pickup'
                    && $buyerType !== 'customer',
                'can_track' => $isExpressShipping && filled($shippingTrackingNumber),
                'can_simulate_finished' => app()->environment(['local', 'development', 'testing'])
                    && $trackingShippingMethod === 'courier_express'
                    && $trackingStatus === 'shipped'
                    && filled($shippingOrderId),
            ],
            'status_at' => $trx
                ? $trx->trx_status_datetime?->toAtomString()
                : $this->resource->status_at,
            'ordered_at' => $trx
                ? $trx->trx_datetime?->toAtomString()
                : $this->resource->ordered_at,
            'parent_transaction_id' => (int) ($trx?->trx_parent_trx_id
                ?? $this->resource->parent_transaction_id
                ?? 0),
            'is_preorder' => $isPreorder,
            'preorder' => $this->when(
                $trx !== null,
                fn (): ?array => $isPreorder ? $this->preorder($trx) : null,
            ),
            'details' => $this->when($trx !== null, fn () => $trx->details->map(fn ($detail): array => [
                'id' => (int) $detail->trx_detail_id,
                'product' => [
                    'id' => (int) $detail->trx_detail_product_id,
                    'code' => $detail->trx_detail_product_code,
                    'name' => $detail->trx_detail_product_name,
                    'bpom_number' => $detail->trx_detail_product_bpom_number
                        ?: $detail->product?->product_bpom_number,
                    'image' => MediaUrl::publicUrl($detail->product?->product_image),
                    'batches' => collect($shippingItemsByProduct->get(
                        (int) $detail->trx_detail_product_id,
                        [],
                    ))->map(fn (array $item): array => [
                        'quantity' => $item['quantity'],
                        'batch_number' => $item['batch_number'],
                        'expiry_date' => $item['expiry_date'],
                    ])->values()->all(),
                ],
                'price' => (int) $detail->trx_detail_product_price,
                'discount_percent' => (int) $detail->trx_detail_discount_percent,
                'discount_value' => (int) $detail->trx_detail_discount_value,
                'net_price' => (int) $detail->trx_detail_nett_price,
                'quantity' => (int) $detail->trx_detail_qty,
                'preorder_quantity' => (bool) ($trx?->trx_is_preorder ?? false)
                    ? (int) $detail->trx_detail_qty
                    : 0,
                'subtotal' => (int) $detail->trx_detail_nett_price * (int) $detail->trx_detail_qty,
                'points' => (int) $detail->trx_detail_qty,
            ])->values()),
            'payment' => $this->payment($trx),
            'tracking' => $this->tracking(
                $trackingTrx,
                $trackingShippingMethod,
                $shippingOrderId,
                $shippingTrackingNumber,
            ),
            'shipping' => $trx === null
                ? ($isStcShipping ? [
                    'method' => $shippingMethod,
                    'order_id' => $shippingOrderId,
                    'tracking_number' => $shippingTrackingNumber,
                ] : null)
                : $this->shipping(
                    $fulfillmentTrx ?? $trx,
                    $shippingOrderId,
                    $shippingTrackingNumber,
                ),
        ];
    }

    private function fulfillmentTransaction(Trx $trx): Trx
    {
        if (! $trx->trx_is_preorder
            || ! $trx->relationLoaded('preorderChain')
            || $trx->preorderChain->isEmpty()) {
            return $trx;
        }

        /** @var Trx|null $fulfillment */
        $fulfillment = $trx->preorderChain->last();

        return $fulfillment ?? $trx;
    }

    private function originTransaction(Trx $trx): Trx
    {
        if (! $trx->trx_is_preorder
            || ! $trx->relationLoaded('preorderChain')
            || $trx->preorderChain->isEmpty()) {
            return $trx;
        }

        /** @var Trx|null $origin */
        $origin = $trx->preorderChain->first();

        return $origin ?? $trx;
    }

    /** @return array<string, mixed>|null */
    private function shipping(Trx $trx, mixed $trackingOrderId, mixed $trackingNumber): ?array
    {
        return match ($trx->trx_shipping_method) {
            'courier_express' => $trx->shippingExpress ? [
                'method' => 'courier_express',
                'courier' => $trx->shippingExpress->shipping_courier_express_expedition_name,
                'service' => $trx->shippingExpress->shipping_courier_express_expedition_service,
                'order_id' => $trx->shippingExpress->shipping_courier_express_order_id ?: $trackingOrderId,
                'pickup_method' => $trx->shippingExpress->shipping_courier_express_pickup_method,
                'pickup_number' => $trx->shippingExpress->shipping_courier_express_pickup_number,
                'pickup_schedule' => $trx->shippingExpress->shipping_courier_express_schedule_datetime,
                'delivery_note_number' => $trx->shippingExpress->shipping_courier_express_delivery_note_number,
                'items' => $this->shippingItems($trx->shippingExpress->details),
                'tracking_number' => $trx->shippingExpress->shipping_courier_express_awb ?: $trackingNumber,
                'delivery_status' => $trx->shippingExpress->latestStatus
                    ?->shipping_courier_express_status_value,
                'insurance' => (int) $trx->shippingExpress->shipping_courier_express_insurance,
                'shipping_cost_insurance' => (int) $trx->shippingExpress->shipping_courier_express_insurance,
            ] : null,
            'courier_instant' => $trx->shippingInstant ? [
                'method' => 'courier_instant',
                'courier' => $trx->shippingInstant->shipping_courier_instant_expedition_name,
                'service' => $trx->shippingInstant->shipping_courier_instant_expedition_service,
                'vehicle' => $trx->shippingInstant->shipping_courier_instant_expedition_vehicle,
                'order_id' => $trx->shippingInstant->shipping_courier_instant_order_id ?: $trackingOrderId,
                'delivery_note_number' => $trx->shippingInstant->shipping_courier_instant_delivery_note_number,
                'items' => $this->shippingItems($trx->shippingInstant->details),
                'tracking_number' => $trx->shippingInstant->shipping_courier_instant_awb ?: $trackingNumber,
                'delivery_status' => $trx->shippingInstant->latestStatus
                    ?->shipping_courier_instant_status_value,
                'cost' => (int) $trx->shippingInstant->shipping_courier_instant_cost,
                'admin_fee' => (int) $trx->shippingInstant->shipping_courier_instant_admin_fee,
                'total_cost' => (int) $trx->shippingInstant->shipping_courier_instant_cost
                    + (int) $trx->shippingInstant->shipping_courier_instant_admin_fee,
                'insurance' => (int) $trx->shippingInstant->shipping_courier_instant_insurance,
                'shipping_cost_insurance' => (int) $trx->shippingInstant->shipping_courier_instant_insurance,
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
            ] : null,
            'pickup' => $trx->shippingPickup ? [
                'method' => 'pickup',
                'delivery_note_number' => $trx->shippingPickup->shipping_pickup_delivery_note_number,
                'items' => $this->shippingItems($trx->shippingPickup->details),
                'schedule_at' => $trx->shippingPickup->shipping_pickup_schedule_datetime,
                'verification_status' => $trx->shippingPickup->latestStatus?->shipping_pickup_status_value,
            ] : null,
            default => null,
        };
    }

    /** @return array<string, mixed>|null */
    private function stockScreening(Trx $trx): ?array
    {
        if ($trx->trx_seller_type !== 'warehouse'
            || $trx->trx_buyer_type !== 'distributor'
            || $trx->trx_status !== 'waiting_stock_screening') {
            return null;
        }

        $stocks = $trx->buyer?->stocks->keyBy('member_stock_product_id') ?? collect();

        return [
            'status' => [
                'code' => 'pending',
                'label' => 'Menunggu Screening',
            ],
            'requested_at' => $trx->trx_status_datetime?->toAtomString(),
            'member_stocks' => $trx->details->map(function ($detail) use ($stocks): array {
                $stock = $stocks->get((int) $detail->trx_detail_product_id);

                return [
                    'product' => [
                        'id' => (int) $detail->trx_detail_product_id,
                        'code' => $detail->trx_detail_product_code,
                        'name' => $detail->trx_detail_product_name,
                    ],
                    'ordered_quantity' => (int) $detail->trx_detail_qty,
                    'balance' => (int) ($stock?->member_stock_balance ?? 0),
                    'transfer_in' => (int) ($stock?->member_stock_transfer_in ?? 0),
                    'transfer_out' => (int) ($stock?->member_stock_transfer_out ?? 0),
                    'available' => max(0, (int) ($stock?->member_stock_balance ?? 0)),
                ];
            })->values(),
        ];
    }

    private function trackingTransaction(Trx $trx): Trx
    {
        if ($trx->relationLoaded('preorderTrackingTransaction')) {
            $trackingTrx = $trx->getRelation('preorderTrackingTransaction');

            if ($trackingTrx instanceof Trx) {
                return $trackingTrx;
            }
        }

        return $trx;
    }

    /** @return array<string, mixed>|null */
    private function tracking(
        ?Trx $trx,
        string $method,
        mixed $orderId,
        mixed $trackingNumber,
    ): ?array {
        if (! in_array($method, ['courier_express', 'courier_instant'], true)) {
            return null;
        }

        $shipping = $trx
            ? match ($method) {
                'courier_express' => $trx->shippingExpress,
                'courier_instant' => $trx->shippingInstant,
            }
        : null;

        return [
            'is_available' => filled($orderId) && filled($trackingNumber),
            'provider' => 'stc',
            'method' => $method,
            'transaction_id' => $trx ? (int) $trx->getKey() : (int) $this->resource->id,
            'order_id' => filled($orderId) ? (string) $orderId : null,
            'tracking_number' => filled($trackingNumber) ? (string) $trackingNumber : null,
            'status' => match ($method) {
                'courier_express' => $shipping?->latestStatus?->shipping_courier_express_status_value,
                'courier_instant' => $shipping?->latestStatus?->shipping_courier_instant_status_value,
            },
            'courier' => match ($method) {
                'courier_express' => $shipping?->shipping_courier_express_expedition_name,
                'courier_instant' => $shipping?->shipping_courier_instant_expedition_name,
            },
            'service' => match ($method) {
                'courier_express' => $shipping?->shipping_courier_express_expedition_service,
                'courier_instant' => $shipping?->shipping_courier_instant_expedition_service,
            },
        ];
    }

    /** @return array<string, mixed>|null */
    private function preorder(Trx $trx): ?array
    {
        if (! $trx->relationLoaded('preorderChain') || $trx->preorderChain->isEmpty()) {
            return null;
        }

        /** @var Trx $originTransaction */
        $originTransaction = $trx->preorderChain->first();

        return [
            'current_transaction_id' => (int) $trx->getKey(),
            'origin' => [
                'transaction' => $this->chainTransaction($originTransaction),
                'buyer' => $this->chainParty($originTransaction, 'buyer'),
            ],
            'chain' => $trx->preorderChain->values()->map(
                fn (Trx $transaction, int $index): array => [
                    'sequence' => $index + 1,
                    'transaction' => $this->chainTransaction($transaction),
                    'seller' => $this->chainParty($transaction, 'seller'),
                    'buyer' => $this->chainParty($transaction, 'buyer'),
                ],
            )->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function chainTransaction(Trx $trx): array
    {
        $status = (string) $trx->trx_status;
        $paymentStatus = (string) ($trx->paymentTransfer?->payment_transfer_approval_status ?? 'pending');

        return [
            'id' => (int) $trx->getKey(),
            'code' => $trx->trx_code,
            'status' => $status,
            'status_label' => $this->chainStatusLabel($status),
            'payment_status' => $paymentStatus,
            'payment_status_label' => $this->chainPaymentStatusLabel($paymentStatus),
            'ordered_at' => $trx->trx_datetime?->toAtomString(),
        ];
    }

    private function chainStatusLabel(string $status): string
    {
        return match ($status) {
            'waiting_stock_screening' => 'Menunggu Screening Stok',
            'waiting_payment' => 'Menunggu Pembayaran',
            'waiting_payment_approval' => 'Menunggu Verifikasi Pembayaran',
            'processing' => 'Diproses',
            'shipped' => 'Dikirim',
            'reship_required' => 'Perlu Dikirim Ulang',
            'ready_to_pickup' => 'Siap Diambil',
            'received' => 'Siap Diterima',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            'rejected' => 'Ditolak',
            default => $status,
        };
    }

    private function chainPaymentStatusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Menunggu Verifikasi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => 'Belum Dibayar',
        };
    }

    /** @return array<string, mixed> */
    private function chainParty(Trx $trx, string $side): array
    {
        $type = (string) $trx->{"trx_{$side}_type"};
        $id = (int) $trx->{"trx_{$side}_id"};
        $party = match (true) {
            $side === 'seller' && $type === 'warehouse' => $trx->sellerWarehouse,
            $side === 'buyer' && $type === 'customer' => $trx->buyerCustomer,
            $side === 'seller' => $trx->seller,
            default => $trx->buyer,
        };

        return [
            'type' => $type,
            'id' => $id,
            'code' => in_array($type, ['warehouse', 'customer'], true)
                ? null
                : $party?->member_code,
            'name' => match ($type) {
                'warehouse' => $party?->warehouse_name,
                'customer' => $party?->customer_name,
                default => $party?->member_name,
            },
        ];
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

    /** @return list<array<string, mixed>> */
    private function transactionShippingItems(Trx $trx): array
    {
        $details = match ($trx->trx_shipping_method) {
            'courier_express' => $trx->shippingExpress?->details,
            'courier_instant' => $trx->shippingInstant?->details,
            'courier_manual' => $trx->shippingManual?->details,
            'pickup' => $trx->shippingPickup?->details,
            default => null,
        };

        return $details ? $this->shippingItems($details) : [];
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

    /** @return array<string, mixed> */
    private function seller(?Trx $trx, string $sellerType, ?Trx $fulfillmentTrx = null): array
    {
        if ($trx) {
            $seller = $sellerType === 'warehouse' ? $trx->sellerWarehouse : $trx->seller;
            $originTrx = $fulfillmentTrx ?? $trx;
            $originType = (string) $originTrx->trx_seller_type;
            $originSeller = $originType === 'warehouse'
                ? $originTrx->sellerWarehouse
                : $originTrx->seller;

            return [
                'type' => $sellerType,
                'id' => (int) $trx->trx_seller_id,
                'code' => $sellerType === 'warehouse' ? null : $seller?->member_code,
                'name' => $sellerType === 'warehouse' ? $seller?->warehouse_name : $seller?->member_name,
                'origin' => $this->locationWithParty(
                    $this->shipmentLocation($originTrx, 'origin', $originSeller),
                    $originType,
                    (int) $originTrx->trx_seller_id,
                    $originType === 'warehouse' ? null : $originSeller?->member_code,
                ),
            ];
        }

        return [
            'type' => $sellerType,
            'id' => (int) $this->resource->seller_id,
            'code' => $sellerType === 'warehouse' ? null : $this->resource->seller_member_code,
            'name' => $sellerType === 'warehouse'
                ? $this->resource->seller_warehouse_name
                : $this->resource->seller_member_name,
            'origin' => $this->locationWithParty(
                $this->listShipmentLocation('origin'),
                $sellerType,
                (int) $this->resource->seller_id,
                $sellerType === 'warehouse' ? null : $this->resource->seller_member_code,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function buyer(
        ?Trx $trx,
        string $buyerType,
        ?Trx $fulfillmentTrx = null,
        ?Trx $originTrx = null,
    ): array {
        if ($trx) {
            $buyer = $buyerType === 'customer' ? $trx->buyerCustomer : $trx->buyer;
            $recipientTrx = $originTrx ?? $trx;
            $recipientType = (string) $recipientTrx->trx_buyer_type;
            $recipient = $recipientType === 'customer'
                ? $recipientTrx->buyerCustomer
                : $recipientTrx->buyer;
            $destinationTrx = $fulfillmentTrx ?? $trx;

            return [
                'type' => $buyerType,
                'id' => (int) $trx->trx_buyer_id,
                'code' => $buyerType === 'customer' ? null : $buyer?->member_code,
                'name' => $buyerType === 'customer' ? $buyer?->customer_name : $buyer?->member_name,
                'destination' => $this->locationWithParty(
                    $this->shipmentLocation($destinationTrx, 'destination', $recipient),
                    $recipientType,
                    (int) $recipientTrx->trx_buyer_id,
                    $recipientType === 'customer' ? null : $recipient?->member_code,
                ),
            ];
        }

        return [
            'type' => $buyerType,
            'id' => (int) $this->resource->buyer_id,
            'code' => $buyerType === 'customer' ? null : $this->resource->buyer_member_code,
            'name' => $buyerType === 'customer'
                ? $this->resource->buyer_customer_name
                : $this->resource->buyer_member_name,
            'destination' => $this->locationWithParty(
                $this->listShipmentLocation('destination'),
                $buyerType,
                (int) $this->resource->buyer_id,
                $buyerType === 'customer' ? null : $this->resource->buyer_member_code,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $location
     * @return array<string, mixed>|null
     */
    private function locationWithParty(
        ?array $location,
        string $type,
        int $id,
        mixed $memberCode,
    ): ?array {
        if ($location === null) {
            return null;
        }

        return [
            'type' => $type,
            'id' => $id,
            'code' => filled($memberCode) ? (string) $memberCode : null,
            ...$location,
        ];
    }

    /** @return array<string, mixed>|null */
    private function shipmentLocation(Trx $trx, string $side, mixed $party): ?array
    {
        $shipping = match ($trx->trx_shipping_method) {
            'courier_express' => $trx->shippingExpress,
            'courier_instant' => $trx->shippingInstant,
            'courier_manual' => $trx->shippingManual,
            'pickup' => $trx->shippingPickup,
            default => null,
        };

        if (! $shipping) {
            return $this->partyLocation($party);
        }

        $prefix = $trx->trx_shipping_method === 'pickup'
            ? 'shipping_pickup_seller_'
            : 'shipping_'.$trx->trx_shipping_method.'_'.$side.'_';
        if ($trx->trx_shipping_method === 'pickup') {
            if ($side !== 'origin') {
                return $this->partyLocation($party);
            }

            return [
                'name' => $shipping->shipping_pickup_seller_name ?: ($party->member_name ?? $party->warehouse_name ?? null),
                'phone' => $shipping->shipping_pickup_seller_mobilephone ?: ($party->member_mobilephone ?? $party->warehouse_phone ?? null),
                'address' => $shipping->shipping_pickup_seller_address ?: ($party->member_address ?? $party->warehouse_address ?? null),
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

        return $this->shippingModelLocation($shipping, $prefix);
    }

    /** @return array<string, mixed>|null */
    private function listShipmentLocation(string $side): ?array
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
        $location = [
            'name' => $this->resource->{$prefix.'name_'.$suffix} ?? null,
            'phone' => $this->resource->{$prefix.'phone_'.$suffix} ?? null,
            'address' => $this->resource->{$prefix.'address_'.$suffix} ?? null,
        ];
        foreach (['subdistrict', 'district', 'city', 'province'] as $region) {
            $location[$region] = [
                'id' => $this->resource->{$prefix.$region.'_id_'.$suffix} ?? null,
                'name' => $this->resource->{$prefix.$region.'_name_'.$suffix} ?? null,
            ];
        }
        $location['postal_code'] = $this->resource->{$prefix.'zipcode_'.$suffix} ?? null;
        $location['note'] = $this->resource->{$prefix.'note_'.$suffix} ?? null;
        $location['latitude'] = $this->resource->{$prefix.'latitude_'.$suffix} ?? null;
        $location['longitude'] = $this->resource->{$prefix.'longitude_'.$suffix} ?? null;

        return collect($location)
            ->except(['subdistrict', 'district', 'city', 'province'])
            ->contains(fn ($value): bool => $value !== null && $value !== '')
                ? $location
                : null;
    }

    /** @return array<string, mixed>|null */
    private function shippingModelLocation(mixed $shipping, string $prefix): ?array
    {
        $value = fn (string $field): mixed => $shipping->{$prefix.$field} ?? null;
        $location = [
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

        return $location;
    }

    /** @return array<string, mixed>|null */
    private function partyLocation(mixed $party): ?array
    {
        if (! $party) {
            return null;
        }

        return [
            'name' => $party->member_name ?? $party->warehouse_name ?? $party->customer_name ?? null,
            'phone' => $party->member_mobilephone ?? $party->warehouse_phone ?? $party->customer_phone ?? null,
            'address' => $party->member_address ?? $party->warehouse_address ?? $party->customer_address ?? null,
            'subdistrict' => ['id' => $party->member_subdistrict_id ?? $party->warehouse_subdistrict_id ?? $party->customer_subdistrict_id ?? null, 'name' => $party->subdistrict?->subdistrict_name ?? null],
            'district' => ['id' => $party->member_district_id ?? $party->warehouse_district_id ?? $party->customer_district_id ?? null, 'name' => $party->district?->district_name ?? null],
            'city' => ['id' => $party->member_city_id ?? $party->warehouse_city_id ?? $party->customer_city_id ?? null, 'name' => $party->city?->city_name ?? null],
            'province' => ['id' => $party->member_province_id ?? $party->warehouse_province_id ?? $party->customer_province_id ?? null, 'name' => $party->province?->province_name ?? null],
            'postal_code' => $party->subdistrict?->subdistrict_zip_code ?? null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function payment(?Trx $trx): ?array
    {
        if ($trx) {
            $payment = $trx->paymentTransfer;

            if (! $payment) {
                return null;
            }

            return [
                'id' => (int) $payment->getKey(),
                'bank' => [
                    'id' => (int) $payment->payment_transfer_bank_id,
                    'code' => $payment->bank?->bank_code,
                    'name' => $payment->bank?->bank_name,
                    'account_name' => $payment->payment_transfer_account_name,
                    'account_number' => $payment->payment_transfer_account_number,
                ],
                'bill_amount' => (int) $payment->payment_transfer_bill_amount,
                'amount' => (int) $payment->payment_transfer_amount,
                'receipt_url' => MediaUrl::temporaryPrivateUrl(
                    $payment->payment_transfer_receipt_file,
                    'admin',
                ),
                'status' => $payment->payment_transfer_approval_status,
                'administrator_id' => $payment->payment_transfer_approval_admin_id
                    ? (int) $payment->payment_transfer_approval_admin_id
                    : null,
                'note' => $payment->payment_transfer_note,
                'transferred_at' => $payment->payment_transfer_datetime?->toAtomString(),
                'verified_at' => $payment->payment_transfer_approval_datetime?->toAtomString(),
                'spread_payment' => $this->spreadPayment($trx),
            ];
        }

        if (! $this->resource->payment_id) {
            return null;
        }

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
                'admin',
            ),
            'status' => $this->resource->payment_status,
            'administrator_id' => isset($this->resource->payment_administrator_id)
                ? (int) $this->resource->payment_administrator_id
                : null,
            'note' => $this->resource->payment_note,
            'transferred_at' => $this->resource->payment_transferred_at,
            'verified_at' => $this->resource->payment_verified_at,
            'spread_payment' => null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function spreadPayment(Trx $trx): ?array
    {
        $spread = $trx->relationLoaded('spreadPayments')
            ? $trx->spreadPayments->first()
            : null;

        if (! $spread) {
            return null;
        }

        return [
            'id' => (int) $spread->getKey(),
            'amount' => (int) $spread->trx_spread_payment_amount,
            'percentage' => (float) $spread->trx_spread_payment_percentage,
            'bank' => [
                'id' => (int) $spread->trx_spread_payment_bank_id,
                'code' => $spread->bank?->bank_code,
                'name' => $spread->bank?->bank_name,
                'account_name' => $spread->trx_spread_payment_account_name,
                'account_number' => $spread->trx_spread_payment_account_number,
            ],
            'receipt_url' => MediaUrl::temporaryPrivateUrl(
                $spread->trx_spread_payment_receipt_file,
                'admin',
            ),
            'transferred_at' => $spread->trx_spread_payment_transfer_datetime?->toAtomString(),
            'status' => $spread->trx_spread_payment_status,
        ];
    }
}
