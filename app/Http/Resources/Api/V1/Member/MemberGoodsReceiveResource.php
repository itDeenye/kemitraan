<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use App\Models\Trx;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class MemberGoodsReceiveResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $trx = $this->resource instanceof Trx ? $this->resource : null;
        $fulfillmentTrx = $trx ? $this->fulfillmentTransaction($trx) : null;
        $shippingTrx = $fulfillmentTrx ?? $trx;
        $goodsReceive = $trx?->goodsReceives->first();
        $receiveId = (int) ($goodsReceive?->goods_receive_id ?? $this->resource->receive_id ?? 0);
        $orderStatus = (string) ($trx?->trx_status ?? $this->resource->order_status);
        $pickupStatus = $shippingTrx?->shippingPickup?->latestStatus?->shipping_pickup_status_value
            ?? ($this->resource->pickup_status ?? null);
        $shippingMethod = (string) ($shippingTrx?->trx_shipping_method ?? $this->resource->shipping_method);
        $isPreorder = (bool) ($trx?->trx_is_preorder ?? $this->resource->is_preorder ?? false);
        $sellerType = (string) ($shippingTrx?->trx_seller_type ?? $this->resource->seller_type);
        $shippingStatus = match ($shippingMethod) {
            'courier_express' => $shippingTrx?->shippingExpress?->latestStatus
                ?->shipping_courier_express_status_value ?? ($this->resource->express_status ?? null),
            'courier_instant' => $shippingTrx?->shippingInstant?->latestStatus
                ?->shipping_courier_instant_status_value ?? ($this->resource->instant_status ?? null),
            'pickup' => $pickupStatus,
            default => null,
        };
        $canReceive = $orderStatus === 'received' && ($isPreorder || match ($shippingMethod) {
            'courier_express', 'courier_instant' => in_array(
                $shippingStatus,
                ['finished_packages', 'completed'],
                true,
            ),
            'pickup' => $pickupStatus === 'picked_up',
            'courier_manual' => true,
            default => false,
        });
        [$statusCode, $statusLabel] = match (true) {
            $receiveId > 0 => ['completed', 'Selesai'],
            ! $canReceive => ['waiting_delivery', 'Menunggu Pengiriman Selesai'],
            default => ['ready_to_receive', 'Siap Diterima'],
        };

        return [
            'purchase_order_id' => (int) ($trx?->trx_id ?? $this->resource->id),
            'transaction' => [
                'id' => (int) ($trx?->trx_id ?? $this->resource->id),
                'code' => $trx?->trx_code ?? $this->resource->code,
                'status' => $orderStatus,
            ],
            'seller' => $this->seller($shippingTrx),
            'shipping_method' => $shippingMethod,
            'receive_number' => $goodsReceive?->goods_receive_number
                ?? $this->resource->receive_number,
            'delivery_note_number' => $this->when(
                $receiveId > 0,
                $goodsReceive?->goods_receive_delivery_note_number
                    ?? $this->resource->delivery_note_number,
            ),
            'status' => [
                'code' => $statusCode,
                'label' => $statusLabel,
            ],
            'pickup_verification_status' => $pickupStatus,
            'shipping_status' => $shippingStatus,
            'actions' => [
                'can_receive' => $receiveId === 0 && $canReceive,
                'requires_delivery_note_number' => $receiveId === 0
                    && $canReceive
                    && $sellerType === 'warehouse',
            ],
            'summary' => $trx ? [
                'product_count' => $trx->details->count(),
                'total_quantity' => (int) $trx->details->sum('trx_detail_qty'),
            ] : [
                'product_count' => (int) $this->resource->product_count,
                'total_quantity' => (int) $this->resource->total_quantity,
            ],
            'ready_at' => $trx
                ? $trx->trx_status_datetime?->toAtomString()
                : $this->resource->ready_at,
            'received_at' => $goodsReceive
                ? $goodsReceive->goods_receive_created_datetime?->toAtomString()
                : $this->resource->received_at,
            'ordered_items' => $this->when($trx !== null, fn () => $trx->details->map(
                fn ($detail): array => [
                    'product_id' => (int) $detail->trx_detail_product_id,
                    'code' => $detail->trx_detail_product_code,
                    'name' => $detail->trx_detail_product_name,
                    'bpom_number' => $detail->trx_detail_product_bpom_number
                        ?: $detail->product?->product_bpom_number,
                    'image' => MediaUrl::publicUrl($detail->product?->product_image),
                    'quantity' => (int) $detail->trx_detail_qty,
                ]
            )->values()),
            'shipment_items' => $this->when(
                $trx !== null && $shippingTrx !== null,
                fn () => $this->shipmentItems($trx, $shippingTrx),
            ),
            'received_items' => $this->when($goodsReceive !== null, fn () => $goodsReceive->details->map(
                function ($detail): array {
                    $returnedQuantity = (int) $detail->activeReturnDetails->sum('return_detail_qty');

                    return [
                        'goods_receive_detail_id' => (int) $detail->goods_receive_detail_id,
                        'product_id' => (int) $detail->goods_receive_detail_product_id,
                        'code' => $detail->product?->product_code,
                        'name' => $detail->product?->product_name,
                        'bpom_number' => $detail->product?->product_bpom_number,
                        'image' => MediaUrl::publicUrl($detail->product?->product_image),
                        'quantity' => (int) $detail->goods_receive_detail_qty,
                        'returned_quantity' => $returnedQuantity,
                        'remaining_return_quantity' => max(
                            0,
                            (int) $detail->goods_receive_detail_qty - $returnedQuantity,
                        ),
                        'batch_number' => $detail->goods_receive_detail_batch_number,
                        'expiry_date' => $detail->goods_receive_detail_expire_date?->toDateString(),
                    ];
                }
            )->values()),
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

    /** @return array<int, array<string, mixed>> */
    private function shipmentItems(Trx $order, Trx $shippingTrx): array
    {
        $shipment = match ($shippingTrx->trx_shipping_method) {
            'courier_express' => $shippingTrx->shippingExpress,
            'courier_instant' => $shippingTrx->shippingInstant,
            'courier_manual' => $shippingTrx->shippingManual,
            'pickup' => $shippingTrx->shippingPickup,
            default => null,
        };

        if (! $shipment) {
            return [];
        }

        $products = $order->details->keyBy('trx_detail_product_id');

        return $shipment->details->map(function ($detail) use ($products): array {
            $orderDetail = $products->get((int) $detail->shipping_detail_product_id);

            return [
                'product_id' => (int) $detail->shipping_detail_product_id,
                'code' => $orderDetail?->trx_detail_product_code,
                'name' => $orderDetail?->trx_detail_product_name,
                'bpom_number' => $orderDetail?->trx_detail_product_bpom_number
                    ?: $orderDetail?->product?->product_bpom_number,
                'image' => MediaUrl::publicUrl($orderDetail?->product?->product_image),
                'quantity' => (int) $detail->shipping_detail_qty,
                'batch_number' => $detail->shipping_detail_batch_number,
                'expiry_date' => $detail->shipping_detail_expire_date?->toDateString(),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function seller(?Trx $trx): array
    {
        $type = $trx?->trx_seller_type ?? $this->resource->seller_type;
        if ($trx) {
            $seller = $type === 'warehouse' ? $trx->sellerWarehouse : $trx->seller;

            return [
                'type' => $type,
                'id' => (int) $trx->trx_seller_id,
                'code' => $type === 'warehouse' ? null : $seller?->member_code,
                'name' => $type === 'warehouse' ? $seller?->warehouse_name : $seller?->member_name,
            ];
        }

        return [
            'type' => $type,
            'id' => (int) $this->resource->seller_id,
            'code' => $type === 'warehouse' ? null : $this->resource->seller_code,
            'name' => $type === 'warehouse'
                ? $this->resource->warehouse_name
                : $this->resource->seller_name,
        ];
    }
}
