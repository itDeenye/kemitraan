<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\ReturnModel;
use App\Models\ShippingCourierExpress;
use App\Models\ShippingCourierInstant;
use App\Models\ShippingCourierManual;
use App\Models\ShippingPickup;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class AdminReturnResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $return = $this->resource instanceof ReturnModel ? $this->resource : null;
        $status = (string) ($return?->return_status ?? $this->resource->status);
        $goodsReceiveId = $return?->return_goods_receive_id ?? $this->resource->goods_receive_id;

        return [
            'id' => (int) ($return?->return_id ?? $this->resource->id),
            'code' => $return?->return_code ?? $this->resource->code,
            'transaction' => [
                'id' => (int) ($return?->goodsReceive?->goods_receive_trx_id ?? $this->resource->trx_id),
                'code' => $return?->trx?->trx_code ?? $this->resource->trx_code,
            ],
            'goods_receive' => $goodsReceiveId ? [
                'id' => (int) $goodsReceiveId,
                'number' => $return?->goodsReceive?->goods_receive_number
                    ?? $this->resource->goods_receive_number,
            ] : null,
            'member' => [
                'id' => (int) ($return?->return_member_id ?? $this->resource->member_id),
                'code' => $return?->member?->member_code ?? $this->resource->member_code,
                'name' => $return?->member?->member_name ?? $this->resource->member_name,
            ],
            'pickup_address' => $this->when($return !== null, fn (): array => [
                'address_id' => (int) $return->return_member_address_id,
                'name' => $return->return_pickup_name,
                'phone' => $return->return_pickup_phone,
                'address' => $return->return_pickup_address,
                'province_id' => (int) $return->return_pickup_province_id,
                'city_id' => (int) $return->return_pickup_city_id,
                'district_id' => (int) $return->return_pickup_district_id,
                'subdistrict_id' => (int) $return->return_pickup_subdistrict_id,
            ]),
            'description' => $return?->return_description ?? $this->resource->description,
            'attachments' => $this->when($return !== null, fn (): array => [
                'images' => collect($return->return_attachment_image_url_json ?? [])
                    ->map(fn (string $url): ?string => MediaUrl::temporaryPrivateUrl($url, 'admin'))
                    ->values()
                    ->all(),
                'video' => MediaUrl::temporaryPrivateUrl(
                    $return->return_attachment_video_url,
                    'admin',
                ),
            ]),
            'status' => $status,
            'return_shipping' => $this->when(
                $return !== null,
                fn (): ?array => $this->shipping($return),
            ),
            'replacement_shipping' => $this->when(
                $return !== null,
                fn (): ?array => $this->shipping($return, true),
            ),
            'summary' => $this->when($return !== null, function () use ($return): array {
                $requested = (int) $return->details->sum('return_detail_qty');
                $received = (int) $return->details->sum('return_detail_received_qty');

                return [
                    'requested_quantity' => $requested,
                    'received_quantity' => $received,
                    'not_received_quantity' => max(0, $requested - $received),
                    'replacement_quantity' => $received,
                ];
            }),
            'created_at' => $return
                ? $return->return_created_datetime?->toAtomString()
                : $this->resource->created_at,
            'actions' => [
                'can_approve' => in_array($status, ['submitted', 'return_shipping_failed'], true),
                'can_reject' => $status === 'submitted',
                'can_receive' => $return !== null
                    && $return->return_shipping_method !== 'pickup'
                    && in_array($status, ['waiting_member_shipment', 'return_in_transit'], true),
                'requires_pickup_pin' => $return !== null
                    && $status === 'submitted'
                    && $return->return_shipping_method === 'pickup',
                'can_ship_replacement' => in_array(
                    $status,
                    ['received_by_company', 'replacement_shipping_failed'],
                    true,
                ),
            ],
            'details' => $this->when($return !== null, fn () => $return->details->map(fn ($detail): array => [
                'id' => (int) $detail->return_detail_id,
                'goods_receive_detail_id' => $detail->return_detail_goods_receive_detail_id
                    ? (int) $detail->return_detail_goods_receive_detail_id
                    : null,
                'product' => [
                    'id' => (int) $detail->return_detail_product_id,
                    'code' => $detail->product?->product_code,
                    'name' => $detail->product?->product_name,
                ],
                'quantity' => (int) $detail->return_detail_qty,
                'requested_quantity' => (int) $detail->return_detail_qty,
                'received_quantity' => (int) $detail->return_detail_received_qty,
                'not_received_quantity' => max(
                    0,
                    (int) $detail->return_detail_qty - (int) $detail->return_detail_received_qty,
                ),
                'replacement_quantity' => (int) $detail->return_detail_received_qty,
                'batch_number' => $detail->goodsReceiveDetail?->goods_receive_detail_batch_number,
                'expiry_date' => $detail->goodsReceiveDetail?->goods_receive_detail_expire_date?->toDateString(),
                'reason' => $detail->return_detail_reason,
            ])->values()),
            'status_history' => $this->when($return !== null, fn () => $return->statusLogs->map(fn ($log): array => [
                'status' => $log->return_status_log_status,
                'note' => $this->displayNote($log->return_status_log_note),
                'created_by' => (int) $log->return_status_log_created_by,
                'created_at' => $log->return_status_log_created_datetime?->toAtomString(),
            ])->values()),
        ];
    }

    /** @return array<string, mixed>|null */
    private function shipping(ReturnModel $return, bool $replacement = false): ?array
    {
        $method = $replacement
            ? $return->return_replacement_shipping_method
            : $return->return_shipping_method;

        $shippingData = match ($method) {
            'courier_express' => $this->expressShipping(
                $replacement ? $return->replacementShippingExpress : $return->shippingExpress,
            ),
            'courier_instant' => $this->instantShipping(
                $replacement ? $return->replacementShippingInstant : $return->shippingInstant,
            ),
            'courier_manual' => $this->manualShipping(
                $replacement ? $return->replacementShippingManual : $return->shippingManual,
            ),
            'pickup' => $this->pickupShipping(
                $replacement ? $return->replacementShippingPickup : $return->shippingPickup,
                (int) ($replacement
                    ? $return->return_replacement_shipping_cost
                    : $return->return_shipping_cost),
            ),
            default => null,
        };

        if ($shippingData !== null) {
            $shippingData['cost_bearer'] = $replacement
                ? 'warehouse'
                : ($return->return_shipping_cost_bearer ?? 'member');
        }

        return $shippingData;
    }

    /** @return array<string, mixed>|null */
    private function expressShipping(?ShippingCourierExpress $shipping): ?array
    {
        if (! $shipping) {
            return null;
        }

        return [
            'method' => 'courier_express',
            'courier' => $shipping->shipping_courier_express_expedition_name,
            'service' => $shipping->shipping_courier_express_expedition_service,
            'etd' => $shipping->shipping_courier_express_etd,
            'pickup_method' => $shipping->shipping_courier_express_pickup_method,
            'pickup_schedule' => $shipping->shipping_courier_express_schedule_datetime,
            'pickup_number' => $shipping->shipping_courier_express_pickup_number,
            'order_id' => $shipping->shipping_courier_express_order_id,
            'delivery_note_number' => $shipping->shipping_courier_express_delivery_note_number,
            'items' => $this->shippingItems($shipping->details),
            'tracking_number' => $shipping->shipping_courier_express_awb,
            'cost' => (int) $shipping->shipping_courier_express_cost,
            'insurance' => (int) $shipping->shipping_courier_express_insurance,
            'force_insurance' => (bool) $shipping->shipping_courier_express_insurance_is_force,
            'total_cost' => (int) $shipping->shipping_courier_express_cost
                + (int) $shipping->shipping_courier_express_insurance,
            'package_weight' => (int) $shipping->shipping_courier_express_package_weight,
            'delivery_status' => $shipping->latestStatus?->shipping_courier_express_status_value,
        ];
    }

    /** @return array<string, mixed>|null */
    private function instantShipping(?ShippingCourierInstant $shipping): ?array
    {
        if (! $shipping) {
            return null;
        }

        return [
            'method' => 'courier_instant',
            'courier' => $shipping->shipping_courier_instant_expedition_name,
            'service' => $shipping->shipping_courier_instant_expedition_service,
            'vehicle' => $shipping->shipping_courier_instant_expedition_vehicle,
            'etd' => $shipping->shipping_courier_instant_estimation_hours,
            'order_id' => $shipping->shipping_courier_instant_order_id,
            'delivery_note_number' => $shipping->shipping_courier_instant_delivery_note_number,
            'items' => $this->shippingItems($shipping->details),
            'tracking_number' => $shipping->shipping_courier_instant_awb,
            'cost' => (int) $shipping->shipping_courier_instant_cost,
            'admin_fee' => (int) $shipping->shipping_courier_instant_admin_fee,
            'total_cost' => (int) $shipping->shipping_courier_instant_cost
                + (int) $shipping->shipping_courier_instant_admin_fee,
            'delivery_status' => $shipping->latestStatus?->shipping_courier_instant_status_value,
        ];
    }

    /** @return array<string, mixed>|null */
    private function manualShipping(?ShippingCourierManual $shipping): ?array
    {
        if (! $shipping) {
            return null;
        }

        return [
            'method' => 'courier_manual',
            'courier' => $shipping->shipping_courier_manual_name,
            'service' => $shipping->shipping_courier_manual_service,
            'delivery_note_number' => $shipping->shipping_courier_manual_delivery_note_number,
            'items' => $this->shippingItems($shipping->details),
            'tracking_number' => $shipping->shipping_courier_manual_awb,
            'cost' => (int) $shipping->shipping_courier_manual_price,
            'delivery_status' => $shipping->latestStatus?->shipping_courier_manual_status_value,
        ];
    }

    /** @return array<string, mixed>|null */
    private function pickupShipping(?ShippingPickup $shipping, int $cost): ?array
    {
        if (! $shipping) {
            return null;
        }

        return [
            'method' => 'pickup',
            'location' => [
                'id' => 1,
                'name' => $shipping->shipping_pickup_seller_name,
                'address' => $shipping->shipping_pickup_seller_address,
                'phone' => $shipping->shipping_pickup_seller_mobilephone,
            ],
            'pickup_schedule' => $shipping->shipping_pickup_schedule_datetime,
            'delivery_note_number' => $shipping->shipping_pickup_delivery_note_number,
            'items' => $this->shippingItems($shipping->details),
            'pin' => $shipping->shipping_pickup_pin,
            'pickup_pin' => $shipping->shipping_pickup_pin,
            'verification_code' => $shipping->shipping_pickup_pin,
            'cost' => $cost,
            'delivery_status' => $shipping->latestStatus?->shipping_pickup_status_value,
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

    private function displayNote(?string $note): ?string
    {
        return $note === null
            ? null
            : str_ireplace(
                ['kurir STC', 'STC', 'Company'],
                ['kurir', 'kurir', 'Perusahaan'],
                $note,
            );
    }
}
