<?php

namespace App\Services\Shipping;

use App\Exceptions\ProcessException;
use App\Models\ShippingCourierExpress;
use App\Models\ShippingCourierExpressStatus;
use App\Models\ShippingCourierInstant;
use App\Models\ShippingCourierInstantStatus;
use App\Models\ShippingCourierManual;
use App\Models\ShippingDetail;
use App\Models\ShippingPickupStatus;
use App\Models\Trx;
use App\Services\Inventory\StockAllocationService;
use App\Services\Notification\PartnershipEmailService;
use App\Services\Purchase\PreorderChainService;
use App\Services\Transaction\AdminTransactionService;
use App\Support\ShippingInsurance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminShippingService
{
    public function __construct(
        private readonly MemberShippingService $shippingService,
        private readonly AdminTransactionService $transactionService,
        private readonly PickupVerificationService $pickupVerificationService,
        private readonly StockAllocationService $stockAllocationService,
        private readonly PreorderChainService $preorderChainService,
        private readonly PartnershipEmailService $partnershipEmailService,
    ) {}

    /** @param array<string, mixed> $data */
    public function ship(Trx $trx, array $data): Trx
    {
        $order = Trx::query()
            ->with(['shippingExpress', 'shippingInstant', 'shippingManual'])
            ->findOrFail($trx->getKey());
        if (! in_array($order->trx_status, ['processing', 'reship_required'], true)) {
            throw new ProcessException('Pesanan belum dapat diproses untuk pengiriman.');
        }
        if ($order->children()->exists()) {
            throw new ProcessException(
                'Pesanan PO ini diteruskan ke seller berikutnya. Pengiriman hanya dilakukan oleh seller terakhir.'
            );
        }

        if ($order->trx_shipping_method === 'courier_express') {
            if (! in_array($order->trx_seller_type, ['warehouse', 'distributor'], true)) {
                throw new ProcessException('Layanan pengiriman ini hanya tersedia untuk gudang atau Distributor.');
            }

            $shippedOrder = $this->shipExpress(
                $order,
                (string) $data['pickup_method'],
                $data['pickup_schedule'] ?? null,
                (string) $data['delivery_note_number'],
                $data['items'],
                $data['courier'] ?? null,
            );
            $this->partnershipEmailService->sendOrderShipped($shippedOrder);

            return $shippedOrder;
        }
        if ($order->trx_shipping_method === 'courier_instant') {
            if ($order->trx_seller_type !== 'distributor') {
                throw new ProcessException('Pengambilan paket oleh kurir hanya tersedia untuk pengiriman oleh Distributor.');
            }

            $shippedOrder = $this->shipInstant(
                $order,
                (string) $data['delivery_note_number'],
                $data['items'],
            );
            $this->partnershipEmailService->sendOrderShipped($shippedOrder);

            return $shippedOrder;
        }

        $shippedOrder = DB::transaction(function () use ($order, $data): Trx {
            $lockedTrx = Trx::query()
                ->with(['shippingManual', 'shippingPickup'])
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedTrx->trx_status !== 'processing') {
                throw new ProcessException('Pesanan harus berstatus diproses sebelum dikirim.');
            }

            $status = in_array($lockedTrx->trx_shipping_method, ['courier_manual', 'pickup'], true)
                ? 'received'
                : 'shipped';
            if ($lockedTrx->trx_shipping_method === 'pickup') {
                if (! $lockedTrx->shippingPickup) {
                    throw new ProcessException('Data pengambilan pesanan tidak ditemukan.');
                }
                $lockedTrx->shippingPickup->update([
                    'shipping_pickup_schedule_datetime' => $data['pickup_schedule'] ?? null,
                    'shipping_pickup_delivery_note_number' => $data['delivery_note_number'],
                ]);
                $this->syncShipmentDetails($lockedTrx->shippingPickup, $lockedTrx, $data['items']);
                ShippingPickupStatus::query()->create([
                    'shipping_pickup_status_shipping_pickup_id' => $lockedTrx->shippingPickup->getKey(),
                    'shipping_pickup_status_ref_type' => 'trx',
                    'shipping_pickup_status_ref_id' => $lockedTrx->getKey(),
                    'shipping_pickup_status_value' => 'ready_to_pickup',
                    'shipping_pickup_status_datetime' => now(),
                ]);
                $this->pickupVerificationService->validateAndMarkPickedUp(
                    $lockedTrx,
                    $data['pickup_pin'] ?? null,
                    now(),
                );
            } elseif ($lockedTrx->trx_shipping_method === 'courier_manual') {
                if (! $lockedTrx->shippingManual) {
                    throw new ProcessException('Data pengiriman manual tidak ditemukan.');
                }
                $lockedTrx->shippingManual->update([
                    'shipping_courier_manual_awb' => $data['tracking_number'],
                    'shipping_courier_manual_delivery_note_number' => $data['delivery_note_number'],
                ]);
                $this->syncShipmentDetails($lockedTrx->shippingManual, $lockedTrx, $data['items']);
            }

            $this->consumeStockForShipment($lockedTrx);
            $lockedTrx->update(['trx_status' => $status, 'trx_status_datetime' => now()]);
            $this->preorderChainService->synchronizeFinalShipment($lockedTrx->refresh());

            return $this->transactionService->order($lockedTrx->refresh());
        });
        $this->partnershipEmailService->sendOrderShipped($shippedOrder);

        return $shippedOrder;
    }

    /** @return list<array<string, mixed>> */
    public function expressPickupSchedules(): array
    {
        return $this->shippingService->expressPickupSchedules();
    }

    /** @return array<string, mixed> */
    public function trackExpress(Trx $trx): array
    {
        $order = Trx::query()
            ->with(['shippingExpress.latestStatus', 'shippingInstant.latestStatus'])
            ->findOrFail($trx->getKey());
        $order = $this->preorderChainService->trackingOrder($order);
        if ($order->trx_shipping_method !== 'courier_express' || ! $order->shippingExpress) {
            throw new ProcessException('Tracking hanya tersedia untuk pengiriman ekspres.');
        }
        if (blank($order->shippingExpress->shipping_courier_express_awb)) {
            throw new ProcessException('AWB belum tersedia untuk pengiriman ini.');
        }

        return $this->shippingService->trackExpress(
            (string) $order->shippingExpress->shipping_courier_express_order_id,
        );
    }

    /** @param array<string, mixed> $data */
    public function expressCouriers(Trx $trx, array $data): array
    {
        $order = Trx::query()->with('shippingExpress')->findOrFail($trx->getKey());
        if ($order->trx_status !== 'reship_required') {
            throw new ProcessException('Pilihan kurir ulang hanya tersedia untuk pesanan yang perlu dikirim ulang.');
        }
        if ($order->trx_shipping_method !== 'courier_express') {
            throw new ProcessException('Pilihan kurir ulang hanya tersedia untuk pengiriman ekspres.');
        }

        return $this->shippingService->transactionExpressRates(
            $order,
            array_values($data['couriers'] ?? []),
        );
    }

    private function shipExpress(
        Trx $trx,
        string $pickupMethod,
        ?string $pickupSchedule,
        string $deliveryNoteNumber,
        array $items,
        ?array $courier,
    ): Trx {
        $lock = Cache::lock("admin-express-shipping:{$trx->getKey()}", 30);

        if (! $lock->get()) {
            throw new ProcessException('Permintaan pengambilan paket sedang diproses. Silakan coba kembali.', 409);
        }

        try {
            $this->validateShipmentItems($trx, $items);
            $isReship = $trx->trx_status === 'reship_required';
            $selectedCourier = $isReship
                ? $this->validatedExpressCourier($trx, $courier ?? [], $pickupMethod)
                : null;
            $storedCourier = ! $isReship && $this->storedExpressCourierNeedsRepair($trx)
                ? $this->validatedStoredExpressCourier($trx, $pickupMethod)
                : null;
            if ($selectedCourier !== null && $trx->shippingExpress) {
                $trx->shippingExpress->forceFill(
                    $this->expressCourierFields($selectedCourier, $pickupMethod)
                );
            } elseif ($storedCourier !== null && $trx->shippingExpress) {
                $trx->shippingExpress->forceFill(
                    $this->expressProviderFields($storedCourier, $pickupMethod)
                );
            }
            $referenceCode = $isReship
                ? $this->nextExpressReshipReferenceCode($trx)
                : $trx->trx_code;
            $pickup = $this->shippingService->createExpressPickup(
                $trx,
                (string) $pickupSchedule,
                $pickupMethod,
                $referenceCode,
            );

            return DB::transaction(function () use (
                $trx,
                $pickupMethod,
                $pickupSchedule,
                $deliveryNoteNumber,
                $items,
                $pickup,
                $isReship,
                $selectedCourier,
                $storedCourier,
                $referenceCode,
            ): Trx {
                $lockedTrx = Trx::query()
                    ->with('shippingExpress')
                    ->whereKey($trx->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $expectedStatus = $isReship ? 'reship_required' : 'processing';
                if ($lockedTrx->trx_status !== $expectedStatus) {
                    throw new ProcessException('Pesanan ini sudah pernah diproses untuk pengiriman.');
                }
                $shipping = $lockedTrx->shippingExpress;
                if (! $shipping || (! $isReship && filled($shipping->shipping_courier_express_pickup_number))) {
                    throw new ProcessException('Permintaan pengambilan paket untuk pesanan ini sudah pernah dibuat.');
                }

                $shipping->update([
                    ...($selectedCourier === null
                        ? ($storedCourier === null
                            ? []
                            : $this->expressProviderFields($storedCourier, $pickupMethod))
                        : $this->expressCourierFields($selectedCourier, $pickupMethod)),
                    'shipping_courier_express_order_id' => $pickup['order_id'],
                    'shipping_courier_express_pickup_method' => $pickupMethod,
                    'shipping_courier_express_pickup_number' => $pickup['pickup_number'],
                    'shipping_courier_express_schedule_datetime' => $pickupSchedule,
                    'shipping_courier_express_awb' => $pickup['tracking_number'],
                    'shipping_courier_express_delivery_note_number' => $deliveryNoteNumber,
                ]);
                $this->syncShipmentDetails($shipping, $lockedTrx, $items);
                ShippingCourierExpressStatus::query()->create([
                    'shipping_courier_express_status_shipping_courier_express_id' => $shipping->getKey(),
                    'shipping_courier_express_status_ref_type' => 'trx',
                    'shipping_courier_express_status_ref_id' => $lockedTrx->getKey(),
                    'shipping_courier_express_status_value' => 'processed_packages',
                    'shipping_courier_express_status_note' => $isReship
                        ? 'Pengiriman ulang berhasil dibuat oleh administrator.'
                        : 'Pickup STC berhasil dibuat oleh administrator.',
                    'shipping_courier_express_status_datetime' => now(),
                    'shipping_courier_express_status_ref_code' => $referenceCode,
                    'shipping_courier_express_status_external_ref_code' => $pickup['order_id']
                        ?: $pickup['pickup_number'],
                ]);
                if (! $isReship) {
                    $this->consumeStockForShipment($lockedTrx);
                }
                $lockedTrx->update(['trx_status' => 'shipped', 'trx_status_datetime' => now()]);
                $this->preorderChainService->synchronizeFinalShipment($lockedTrx->refresh());

                return $this->transactionService->order($lockedTrx->refresh());
            });
        } finally {
            $lock->release();
        }
    }

    private function shipInstant(
        Trx $trx,
        string $deliveryNoteNumber,
        array $items,
    ): Trx {
        $lock = Cache::lock("admin-instant-shipping:{$trx->getKey()}", 30);
        if (! $lock->get()) {
            throw new ProcessException('Permintaan kurir instan sedang diproses. Silakan coba kembali.', 409);
        }

        try {
            $this->validateShipmentItems($trx, $items);
            $isReship = $trx->trx_status === 'reship_required';
            $referenceCode = $isReship
                ? $this->nextInstantReshipReferenceCode($trx)
                : $trx->trx_code;
            $pickup = $this->shippingService->createInstantPickup($trx, $referenceCode);

            return DB::transaction(function () use (
                $trx,
                $deliveryNoteNumber,
                $items,
                $pickup,
                $isReship,
                $referenceCode,
            ): Trx {
                $lockedTrx = Trx::query()
                    ->with('shippingInstant')
                    ->whereKey($trx->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $expectedStatus = $isReship ? 'reship_required' : 'processing';
                if ($lockedTrx->trx_status !== $expectedStatus) {
                    throw new ProcessException('Pesanan ini sudah pernah diproses untuk pengiriman.');
                }
                $shipping = $lockedTrx->shippingInstant;
                if (! $shipping || (! $isReship && filled($shipping->shipping_courier_instant_order_id))) {
                    throw new ProcessException('Permintaan kurir instan untuk pesanan ini sudah pernah dibuat.');
                }

                $shipping->update([
                    'shipping_courier_instant_order_id' => $pickup['order_id'],
                    'shipping_courier_instant_awb' => $pickup['tracking_number'],
                    'shipping_courier_instant_delivery_note_number' => $deliveryNoteNumber,
                ]);
                $this->syncShipmentDetails($shipping, $lockedTrx, $items);
                ShippingCourierInstantStatus::query()->create([
                    'shipping_courier_instant_status_shipping_courier_instant_id' => $shipping->getKey(),
                    'shipping_courier_instant_status_ref_type' => 'trx',
                    'shipping_courier_instant_status_ref_id' => $lockedTrx->getKey(),
                    'shipping_courier_instant_status_value' => 'processed_packages',
                    'shipping_courier_instant_status_note' => $isReship
                        ? 'Pengiriman ulang instan berhasil dibuat oleh administrator.'
                        : 'Pickup instan STC berhasil dibuat oleh administrator.',
                    'shipping_courier_instant_status_datetime' => now(),
                    'shipping_courier_instant_status_ref_code' => $referenceCode,
                    'shipping_courier_instant_status_external_ref_code' => $pickup['order_id'],
                ]);
                if (! $isReship) {
                    $this->consumeStockForShipment($lockedTrx);
                }
                $lockedTrx->update(['trx_status' => 'shipped', 'trx_status_datetime' => now()]);
                $this->preorderChainService->synchronizeFinalShipment($lockedTrx->refresh());

                return $this->transactionService->order($lockedTrx->refresh());
            });
        } finally {
            $lock->release();
        }
    }

    private function nextExpressReshipReferenceCode(Trx $trx): string
    {
        $referenceCodes = ShippingCourierExpressStatus::query()
            ->where(
                'shipping_courier_express_status_shipping_courier_express_id',
                $trx->shippingExpress?->getKey() ?? 0,
            )
            ->pluck('shipping_courier_express_status_ref_code');

        return $this->nextReshipReferenceCode((string) $trx->trx_code, $referenceCodes);
    }

    private function nextInstantReshipReferenceCode(Trx $trx): string
    {
        $referenceCodes = ShippingCourierInstantStatus::query()
            ->where(
                'shipping_courier_instant_status_shipping_courier_instant_id',
                $trx->shippingInstant?->getKey() ?? 0,
            )
            ->pluck('shipping_courier_instant_status_ref_code');

        return $this->nextReshipReferenceCode((string) $trx->trx_code, $referenceCodes);
    }

    /** @param iterable<int, mixed> $referenceCodes */
    private function nextReshipReferenceCode(string $transactionCode, iterable $referenceCodes): string
    {
        $pattern = '/^'.preg_quote($transactionCode, '/').'-([0-9]+)$/';
        $lastSequence = 0;

        foreach ($referenceCodes as $referenceCode) {
            if (preg_match($pattern, (string) $referenceCode, $matches) === 1) {
                $lastSequence = max($lastSequence, (int) $matches[1]);
            }
        }

        return sprintf('%s-%03d', $transactionCode, $lastSequence + 1);
    }

    private function consumeStockForShipment(Trx $trx): void
    {
        if ($trx->trx_type === 'stock') {
            $this->stockAllocationService->consume($trx);
        }
    }

    /**
     * @param  array<string, mixed>  $courier
     * @return array<string, mixed>
     */
    private function validatedExpressCourier(Trx $trx, array $courier, string $pickupMethod): array
    {
        $rate = collect($this->shippingService->transactionExpressRates(
            $trx,
            [(string) ($courier['courier_code'] ?? '')],
        )['results'])->first(fn (array $candidate): bool => (string) $candidate['courier_code'] === (string) ($courier['courier_code'] ?? '')
            && (string) $candidate['courier_name'] === (string) ($courier['courier_name'] ?? '')
            && (string) $candidate['service_type'] === (string) ($courier['service_type'] ?? '')
        );
        if (! $rate) {
            throw new ProcessException(
                'Layanan kurir yang dipilih tidak tersedia lagi. Silakan cek ongkir kembali.'
            );
        }
        if ($pickupMethod === 'DROP-OFF' && ! $rate['drop_off_available']) {
            throw new ProcessException('Layanan kurir yang dipilih tidak mendukung pengantaran paket ke gerai kurir.');
        }

        return $rate;
    }

    /** @return array<string, mixed> */
    private function validatedStoredExpressCourier(Trx $trx, string $pickupMethod): array
    {
        $shipping = $trx->shippingExpress;
        if (! $shipping) {
            throw new ProcessException('Data pengiriman ekspres tidak ditemukan.');
        }

        $courierCode = (string) $shipping->shipping_courier_express_expedition_name;
        $courierName = (string) $shipping->shipping_courier_express_expedition_service;
        $rate = collect($this->shippingService->transactionExpressRates(
            $trx,
            [$courierCode],
        )['results'])->first(fn (array $candidate): bool => (string) $candidate['courier_code'] === $courierCode
            && ((string) $candidate['courier_name'] === $courierName
                || (string) $candidate['service_type'] === $courierName)
        );

        if (! $rate) {
            throw new ProcessException(
                'Layanan kurir pada pesanan tidak tersedia lagi. Silakan cek ongkir dan pilih layanan kembali.'
            );
        }
        if ($pickupMethod === 'DROP-OFF' && ! $rate['drop_off_available']) {
            throw new ProcessException('Layanan kurir yang dipilih tidak mendukung pengantaran paket ke gerai kurir.');
        }

        return $rate;
    }

    private function storedExpressCourierNeedsRepair(Trx $trx): bool
    {
        $shipping = $trx->shippingExpress;
        if (! $shipping) {
            return false;
        }

        $serviceType = trim((string) $shipping->shipping_courier_express_type);

        return $serviceType === '' || preg_match('/\s/u', $serviceType) === 1;
    }

    /**
     * @param  array<string, mixed>  $courier
     * @return array<string, mixed>
     */
    private function expressCourierFields(array $courier, string $pickupMethod): array
    {
        return [
            ...$this->expressProviderFields($courier, $pickupMethod),
            'shipping_courier_express_cost' => (int) $courier['cost'],
            'shipping_courier_express_insurance_is_force' => ShippingInsurance::isForced($courier),
            'shipping_courier_express_insurance' => ShippingInsurance::amount($courier),
        ];
    }

    /**
     * @param  array<string, mixed>  $courier
     * @return array<string, mixed>
     */
    private function expressProviderFields(array $courier, string $pickupMethod): array
    {
        return [
            'shipping_courier_express_type' => $courier['service_type'],
            'shipping_courier_express_expedition_name' => $courier['courier_code'],
            'shipping_courier_express_expedition_service' => $courier['courier_name'],
            'shipping_courier_express_etd' => $courier['etd'] ?? '',
            'shipping_courier_express_pickup_method' => $pickupMethod,
        ];
    }

    /** @param list<array<string, mixed>> $items */
    private function validateShipmentItems(Trx $trx, array $items): void
    {
        $expected = $trx->details()
            ->select(['trx_detail_product_id'])
            ->selectRaw('SUM(trx_detail_qty) AS quantity')
            ->groupBy('trx_detail_product_id')
            ->pluck('quantity', 'trx_detail_product_id')
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->sortKeys();
        $actual = collect($items)
            ->groupBy('product_id')
            ->map(fn ($rows): int => (int) collect($rows)->sum('quantity'))
            ->sortKeys();
        if ($expected->all() !== $actual->all()) {
            throw new ProcessException('Jumlah produk dan batch pengiriman harus sesuai pesanan.');
        }
    }

    /** @param list<array<string, mixed>> $items */
    private function syncShipmentDetails(Model $shipping, Trx $trx, array $items): void
    {
        $this->validateShipmentItems($trx, $items);
        $type = match ($shipping::class) {
            ShippingCourierExpress::class => 'courier_express',
            ShippingCourierInstant::class => 'courier_instant',
            ShippingCourierManual::class => 'courier_manual',
            default => 'pickup',
        };
        ShippingDetail::query()
            ->where('shipping_detail_shipping_type', $type)
            ->where('shipping_detail_shipping_id', $shipping->getKey())
            ->delete();
        ShippingDetail::query()->insert(collect($items)->map(fn (array $item): array => [
            'shipping_detail_shipping_type' => $type,
            'shipping_detail_shipping_id' => $shipping->getKey(),
            'shipping_detail_product_id' => $item['product_id'],
            'shipping_detail_batch_number' => $item['batch_number'],
            'shipping_detail_qty' => $item['quantity'],
            'shipping_detail_expire_date' => $item['expiry_date'] ?? null,
        ])->all());
    }
}
