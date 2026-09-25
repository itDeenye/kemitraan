<?php

namespace App\Services\Shipping;

use App\Exceptions\ProcessException;
use App\Models\ShippingPickup;
use App\Models\ShippingPickupStatus;
use App\Models\Trx;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

class PickupVerificationService
{
    public function validateAndMarkPickedUp(
        Trx $trx,
        ?string $pin,
        DateTimeInterface $pickedUpAt,
    ): void {
        if ($trx->trx_shipping_method !== 'pickup') {
            throw new ProcessException('Pesanan tidak menggunakan metode ambil di tempat.');
        }

        $pickup = ShippingPickup::query()
            ->where('shipping_pickup_ref_type', 'trx')
            ->where('shipping_pickup_ref_id', $trx->getKey())
            ->lockForUpdate()
            ->first();
        if (! $pickup || blank($pickup->shipping_pickup_pin)) {
            throw new ProcessException('Kode pengambilan untuk pesanan ini tidak ditemukan.');
        }

        if ($pin === null || $pin === '') {
            throw ValidationException::withMessages([
                'pickup_pin' => ['PIN pickup wajib diisi untuk memproses pengiriman pickup.'],
            ]);
        }
        if (! hash_equals((string) $pickup->shipping_pickup_pin, $pin)) {
            throw ValidationException::withMessages([
                'pickup_pin' => ['PIN pickup tidak sesuai.'],
            ]);
        }

        $latestStatus = ShippingPickupStatus::query()
            ->where('shipping_pickup_status_shipping_pickup_id', $pickup->getKey())
            ->lockForUpdate()
            ->latest('shipping_pickup_status_id')
            ->value('shipping_pickup_status_value');

        if (! in_array($latestStatus, ['picked_up', 'completed'], true)) {
            ShippingPickupStatus::query()->create([
                'shipping_pickup_status_shipping_pickup_id' => $pickup->getKey(),
                'shipping_pickup_status_ref_type' => 'trx',
                'shipping_pickup_status_ref_id' => $trx->getKey(),
                'shipping_pickup_status_value' => 'picked_up',
                'shipping_pickup_status_datetime' => $pickedUpAt,
            ]);
        }
    }

    public function markCompleted(Trx $trx, DateTimeInterface $completedAt): void
    {
        $pickup = ShippingPickup::query()
            ->where('shipping_pickup_ref_type', 'trx')
            ->where('shipping_pickup_ref_id', $trx->getKey())
            ->lockForUpdate()
            ->first();
        if (! $pickup) {
            throw new ProcessException('Data pengambilan pesanan tidak ditemukan.');
        }

        $latestStatus = ShippingPickupStatus::query()
            ->where('shipping_pickup_status_shipping_pickup_id', $pickup->getKey())
            ->lockForUpdate()
            ->latest('shipping_pickup_status_id')
            ->value('shipping_pickup_status_value');
        if ($latestStatus !== 'completed') {
            ShippingPickupStatus::query()->create([
                'shipping_pickup_status_shipping_pickup_id' => $pickup->getKey(),
                'shipping_pickup_status_ref_type' => 'trx',
                'shipping_pickup_status_ref_id' => $trx->getKey(),
                'shipping_pickup_status_value' => 'completed',
                'shipping_pickup_status_datetime' => $completedAt,
            ]);
        }
    }
}
