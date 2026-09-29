<?php

namespace App\Http\Resources\Api\V1\Member\Concerns;

use App\Models\Trx;

trait BuildsMemberTracking
{
    /** @return array<string, mixed>|null */
    private function memberTracking(Trx $trx): ?array
    {
        $trackingOrder = $trx->relationLoaded('preorderTrackingTransaction')
            && $trx->preorderTrackingTransaction instanceof Trx
                ? $trx->preorderTrackingTransaction
                : $trx;

        return match ($trackingOrder->trx_shipping_method) {
            'courier_express' => $this->memberExpressTracking($trackingOrder, $trx),
            'courier_instant' => $this->memberInstantTracking($trackingOrder),
            'courier_manual' => $this->memberManualTracking($trackingOrder),
            'pickup' => $this->memberPickupTracking($trackingOrder),
            default => null,
        };
    }

    /** @return array<string, mixed>|null */
    private function memberExpressTracking(Trx $trx, ?Trx $historySource = null): ?array
    {
        $shipping = $trx->shippingExpress;
        if (! $shipping) {
            return null;
        }

        $orderId = filled($shipping->shipping_courier_express_order_id)
            ? (string) $shipping->shipping_courier_express_order_id
            : null;
        $trackingNumber = filled($shipping->shipping_courier_express_awb)
            ? (string) $shipping->shipping_courier_express_awb
            : null;

        return [
            'is_available' => $orderId !== null && $trackingNumber !== null,
            'provider' => 'stc',
            'method' => 'courier_express',
            'order_id' => $orderId,
            'tracking_number' => $trackingNumber,
            'status' => $shipping->latestStatus?->shipping_courier_express_status_value,
            'histories' => $this->memberTrackingHistories($historySource ?? $trx),
        ];
    }

    /** @return array<string, mixed>|null */
    private function memberInstantTracking(Trx $trx): ?array
    {
        $shipping = $trx->shippingInstant;
        if (! $shipping) {
            return null;
        }

        $orderId = filled($shipping->shipping_courier_instant_order_id)
            ? (string) $shipping->shipping_courier_instant_order_id
            : null;
        $trackingNumber = filled($shipping->shipping_courier_instant_awb)
            ? (string) $shipping->shipping_courier_instant_awb
            : null;

        return [
            'is_available' => $orderId !== null && $trackingNumber !== null,
            'provider' => 'stc',
            'method' => 'courier_instant',
            'order_id' => $orderId,
            'tracking_number' => $trackingNumber,
            'status' => $shipping->latestStatus?->shipping_courier_instant_status_value,
            'histories' => [],
        ];
    }

    /** @return array<string, mixed>|null */
    private function memberManualTracking(Trx $trx): ?array
    {
        $shipping = $trx->shippingManual;
        if (! $shipping) {
            return null;
        }

        $trackingNumber = filled($shipping->shipping_courier_manual_awb)
            ? (string) $shipping->shipping_courier_manual_awb
            : null;

        return [
            'is_available' => false,
            'provider' => filled($shipping->shipping_courier_manual_name)
                ? (string) $shipping->shipping_courier_manual_name
                : null,
            'method' => 'courier_manual',
            'order_id' => null,
            'tracking_number' => $trackingNumber,
            'status' => $shipping->latestStatus?->shipping_courier_manual_status_value,
            'histories' => [],
        ];
    }

    /** @return array<string, mixed>|null */
    private function memberPickupTracking(Trx $trx): ?array
    {
        $shipping = $trx->shippingPickup;
        if (! $shipping) {
            return null;
        }

        return [
            'is_available' => false,
            'provider' => null,
            'method' => 'pickup',
            'order_id' => null,
            'tracking_number' => null,
            'status' => $shipping->latestStatus?->shipping_pickup_status_value,
            'histories' => [],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function memberTrackingHistories(Trx $trx): array
    {
        $histories = $trx->getAttribute('member_tracking_histories');

        return is_array($histories) ? array_values($histories) : [];
    }
}
