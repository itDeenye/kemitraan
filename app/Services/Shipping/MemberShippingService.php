<?php

namespace App\Services\Shipping;

use App\Contracts\Integrations\StcGateway;
use App\Exceptions\ProcessException;
use App\Models\Customer;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberStock;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\RefCity;
use App\Models\RefDistrict;
use App\Models\RefProvince;
use App\Models\RefSubdistrict;
use App\Models\ReturnModel;
use App\Models\ShippingCourierExpress;
use App\Models\ShippingCourierInstant;
use App\Models\Trx;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Purchase\PreorderChainService;
use App\Support\InstantShipping;
use App\Support\ShippingInsurance;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MemberShippingService
{
    private const STC_ITEM_NAME = 'Skincare';

    public function __construct(
        private readonly StcGateway $stcGateway,
        private readonly PreorderChainService $preorderChainService,
    ) {}

    /**
     * Menghitung ongkir pembelian atau penjualan berdasarkan tujuan yang dipilih.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     origin: array<string, int|string|null>,
     *     destination: array<string, int|string|null>,
     *     results: list<array<string, mixed>>
     * }
     */
    public function purchaseExpressRates(MemberAccount $account, array $data): array
    {
        $member = $this->member($account)->loadMissing('level');
        $isSale = isset($data['customer_id']);
        $directSeller = $isSale ? null : $this->directPurchaseSeller($member);
        $originSeller = $isSale
            ? null
            : $this->purchaseOriginSeller($member, collect($data['items']), $directSeller);
        $destination = $isSale
            ? $this->customerDestination($member, (int) $data['customer_id'])
            : $this->memberAddressDestination($member, (int) $data['address_id']);
        $origin = $isSale ? $this->memberOrigin($member) : $this->originFromSeller($originSeller);

        $rates = $this->expressRates($account, [
            'origin' => $origin,
            'destination' => $destination,
            'couriers' => $data['couriers'] ?? [],
            'items' => $data['items'],
        ], $isSale ? 'sale' : 'purchase');

        if ($originSeller) {
            $rates['origin'] = [
                ...$rates['origin'],
                ...$this->originSourceData($originSeller),
            ];
            $rates['shipping_method'] = $originSeller['type'] === 'warehouse'
                ? 'courier_express'
                : 'courier_manual';
        }

        return $rates;
    }

    /**
     * Menentukan lokasi asal fisik sebelum checkout tanpa memanggil penyedia kurir.
     * Untuk PO, lokasi ini adalah penjual terakhir yang benar-benar menyediakan stok.
     *
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @return array<string, int|string|null>
     */
    public function purchaseOrigin(MemberAccount $account, array $items): array
    {
        $member = $this->member($account)->loadMissing('level');
        $directSeller = $this->directPurchaseSeller($member);
        $originSeller = $this->purchaseOriginSeller($member, collect($items), $directSeller);
        $locationIds = $this->originFromSeller($originSeller);

        return [
            ...$this->rateLocation($locationIds['district_id'], $locationIds['subdistrict_id']),
            ...$this->originSourceData($originSeller),
            'type' => $originSeller['type'],
            'id' => $originSeller['id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     origin: array<string, int|string|null>,
     *     destination: array<string, int|string|null>,
     *     results: list<array<string, mixed>>
     * }
     */
    public function expressRates(
        MemberAccount $account,
        array $data,
        string $priceType = 'purchase',
    ): array {
        $member = $this->member($account)->loadMissing('level');
        $items = collect($data['items']);
        $products = $this->products($items);
        $package = $this->package($products, $items);
        $payload = $this->stcGateway->expressShippingRates([
            'client_id' => (int) config('services.stc.client_id'),
            'item_value' => $this->itemValue($member, $products, $items, $priceType),
            'insurance' => 0,
            'origin' => (int) data_get($data, 'origin.district_id'),
            'subdistrict_origin' => (int) data_get($data, 'origin.subdistrict_id'),
            'destination' => (int) data_get($data, 'destination.district_id'),
            'subdistrict_destination' => (int) data_get($data, 'destination.subdistrict_id'),
            'length' => $package['length'],
            'height' => $package['height'],
            'width' => $package['width'],
            'weight' => $package['weight'],
            'courier' => $data['couriers'] ?? [],
        ]);
        $details = $payload['details'];
        $origin = $this->rateLocation(
            (int) ($details['origin_district_id'] ?? data_get($data, 'origin.district_id')),
            (int) ($details['origin_subdistrict_id'] ?? data_get($data, 'origin.subdistrict_id')),
        );
        $destination = $this->rateLocation(
            (int) ($details['destination_district_id'] ?? data_get($data, 'destination.district_id')),
            (int) ($details['destination_subdistrict_id'] ?? data_get($data, 'destination.subdistrict_id')),
        );

        return [
            'origin' => $origin,
            'destination' => $destination,
            'results' => collect($payload['results'])->map(fn (array $rate): array => [
                'courier_code' => $rate['service'] ?? null,
                'courier_name' => $rate['service_name'] ?? null,
                'service_type' => $rate['service_type'] ?? null,
                'cost' => (int) ($rate['cost'] ?? 0),
                'etd' => $rate['etd'] ?? null,
                'drop_off_available' => (bool) ($rate['drop'] ?? false),
                'force_insurance' => ShippingInsurance::isForced($rate),
                'insurance' => ShippingInsurance::amount($rate),
                'logo_url' => $rate['logo'] ?? null,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $origin
     * @param  array<string, mixed>  $destination
     * @param  list<string>  $couriers
     * @param  list<array{product_id: int, quantity: int}>  $selectedItems
     * @return array<string, mixed>
     */
    public function returnExpressRates(
        ReturnModel $return,
        array $origin,
        array $destination,
        string $referenceType,
        array $couriers = [],
        array $selectedItems = [],
    ): array {
        $return->loadMissing(['details.product', 'trx.details']);
        $items = $selectedItems !== []
            ? collect($selectedItems)
            : $return->details
                ->groupBy('return_detail_product_id')
                ->map(function (Collection $details, int|string $productId) use ($referenceType): array {
                    $quantityColumn = $referenceType === 'return_replacement'
                        ? 'return_detail_received_qty'
                        : 'return_detail_qty';

                    return [
                        'product_id' => (int) $productId,
                        'quantity' => (int) $details->sum($quantityColumn),
                    ];
                })
                ->filter(fn (array $item): bool => $item['quantity'] > 0)
                ->values();
        $products = $return->details
            ->pluck('product')
            ->filter(fn (mixed $product): bool => $product instanceof Product)
            ->unique('product_id')
            ->keyBy('product_id')
            ->only($items->pluck('product_id')->all());
        if ($items->isEmpty() || $products->count() !== $items->count()) {
            throw new ProcessException('Rincian produk retur tidak lengkap untuk menghitung ongkir.');
        }

        $package = $this->package($products, $items);
        $prices = $return->trx?->details
            ->keyBy('trx_detail_product_id')
            ->map(fn ($detail): int => (int) $detail->trx_detail_nett_price)
            ?? collect();
        $itemValue = (int) $items->sum(
            fn (array $item): int => (int) $item['quantity']
                * (int) $prices->get($item['product_id'], 0),
        );
        $payload = $this->stcGateway->expressShippingRates([
            'client_id' => (int) config('services.stc.client_id'),
            'item_value' => $itemValue,
            'insurance' => 0,
            'origin' => (int) $origin['district_id'],
            'subdistrict_origin' => (int) $origin['subdistrict_id'],
            'destination' => (int) $destination['district_id'],
            'subdistrict_destination' => (int) $destination['subdistrict_id'],
            'length' => $package['length'],
            'height' => $package['height'],
            'width' => $package['width'],
            'weight' => $package['weight'],
            'courier' => $couriers,
        ]);
        $details = $payload['details'];

        return [
            'origin' => $this->rateLocation(
                (int) ($details['origin_district_id'] ?? $origin['district_id']),
                (int) ($details['origin_subdistrict_id'] ?? $origin['subdistrict_id']),
            ),
            'destination' => $this->rateLocation(
                (int) ($details['destination_district_id'] ?? $destination['district_id']),
                (int) ($details['destination_subdistrict_id'] ?? $destination['subdistrict_id']),
            ),
            'items' => $items->map(function (array $item) use ($products, $prices): array {
                /** @var Product $product */
                $product = $products->get($item['product_id']);
                $unitValue = (int) $prices->get($item['product_id'], 0);

                return [
                    'product_id' => (int) $item['product_id'],
                    'product_code' => $product->product_code,
                    'product_name' => $product->product_name,
                    'quantity' => (int) $item['quantity'],
                    'unit_weight' => (int) $product->product_weight,
                    'total_weight' => (int) $product->product_weight * (int) $item['quantity'],
                    'unit_value' => $unitValue,
                    'subtotal_value' => $unitValue * (int) $item['quantity'],
                ];
            })->values()->all(),
            'package' => [
                ...$package,
                'item_value' => $itemValue,
            ],
            'results' => collect($payload['results'])->map(fn (array $rate): array => [
                'courier_code' => $rate['service'] ?? null,
                'courier_name' => $rate['service_name'] ?? null,
                'service_type' => $rate['service_type'] ?? null,
                'cost' => (int) ($rate['cost'] ?? 0),
                'etd' => $rate['etd'] ?? null,
                'drop_off_available' => (bool) ($rate['drop'] ?? false),
                'force_insurance' => ShippingInsurance::isForced($rate),
                'insurance' => ShippingInsurance::amount($rate),
                'logo_url' => $rate['logo'] ?? null,
            ])->values()->all(),
        ];
    }

    /**
     * Mengambil ulang pilihan kurir untuk pengiriman transaksi yang gagal.
     * Nilai barang dan ukuran paket memakai snapshot transaksi sehingga tidak
     * berubah ketika master produk diperbarui setelah pesanan dibuat.
     *
     * @param  list<string>  $couriers
     * @return array<string, mixed>
     */
    public function transactionExpressRates(Trx $trx, array $couriers = []): array
    {
        $trx->loadMissing(['shippingExpress', 'details']);
        $shipping = $trx->shippingExpress;
        if (! $shipping) {
            throw new ProcessException('Data pengiriman ekspres tidak ditemukan.');
        }

        $origin = $this->locationIds(
            (int) $shipping->shipping_courier_express_origin_subdistrict_id
        );
        $destination = $this->locationIds(
            (int) $shipping->shipping_courier_express_destination_subdistrict_id
        );
        $payload = $this->stcGateway->expressShippingRates([
            'client_id' => (int) config('services.stc.client_id'),
            'item_value' => (int) $trx->trx_total_price,
            'insurance' => 0,
            'origin' => $origin['district_id'],
            'subdistrict_origin' => $origin['subdistrict_id'],
            'destination' => $destination['district_id'],
            'subdistrict_destination' => $destination['subdistrict_id'],
            'length' => (int) $shipping->shipping_courier_express_package_length,
            'height' => (int) $shipping->shipping_courier_express_package_height,
            'width' => (int) $shipping->shipping_courier_express_package_width,
            'weight' => (int) $shipping->shipping_courier_express_package_weight,
            'courier' => $couriers,
        ]);
        $details = $payload['details'];

        return [
            'origin' => $this->rateLocation(
                (int) ($details['origin_district_id'] ?? $origin['district_id']),
                (int) ($details['origin_subdistrict_id'] ?? $origin['subdistrict_id']),
            ),
            'destination' => $this->rateLocation(
                (int) ($details['destination_district_id'] ?? $destination['district_id']),
                (int) ($details['destination_subdistrict_id'] ?? $destination['subdistrict_id']),
            ),
            'results' => collect($payload['results'])->map(fn (array $rate): array => [
                'courier_code' => $rate['service'] ?? null,
                'courier_name' => $rate['service_name'] ?? null,
                'service_type' => $rate['service_type'] ?? null,
                'cost' => (int) ($rate['cost'] ?? 0),
                'etd' => $rate['etd'] ?? null,
                'drop_off_available' => (bool) ($rate['drop'] ?? false),
                'force_insurance' => ShippingInsurance::isForced($rate),
                'insurance' => ShippingInsurance::amount($rate),
                'logo_url' => $rate['logo'] ?? null,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     origin: array{latitude: float, longitude: float, address: string},
     *     destination: array{latitude: float, longitude: float, address: string},
     *     distance: float|int|null,
     *     distance_unit: string|null,
     *     results: list<array<string, mixed>>
     * }
     */
    public function instantRates(
        MemberAccount $account,
        array $data,
        string $priceType = 'purchase',
    ): array {
        $member = $this->member($account)->loadMissing('level');
        $items = collect($data['items']);
        $products = $this->products($items);
        $package = $this->package($products, $items);
        if ($package['weight'] > InstantShipping::MAX_WEIGHT_GRAMS) {
            throw new ProcessException('Berat paket kurir instan maksimal 40.000 gram.');
        }
        $payload = $this->stcGateway->instantShippingRates([
            'service' => array_values($data['services']),
            'item_price' => $this->itemValue($member, $products, $items, $priceType),
            'origin' => [
                'lat' => (float) data_get($data, 'origin.latitude'),
                'long' => (float) data_get($data, 'origin.longitude'),
                'address' => (string) data_get($data, 'origin.address'),
            ],
            'destination' => [
                'lat' => (float) data_get($data, 'destination.latitude'),
                'long' => (float) data_get($data, 'destination.longitude'),
                'address' => (string) data_get($data, 'destination.address'),
            ],
            'weight' => $package['weight'],
            'vehicle' => $data['vehicle'],
            'timezone' => $data['timezone'] ?? 'WIB',
        ]);
        $distance = data_get($payload, 'meta.distance');
        $distanceUnit = data_get($payload, 'meta.distance_unit');

        $results = collect($payload['result'])
            ->filter(fn (array $provider): bool => in_array(
                Str::lower(trim((string) ($provider['name'] ?? ''))),
                InstantShipping::COURIER_CODES,
                true,
            ))
            ->flatMap(function (array $provider) use ($data, $distance, $distanceUnit): Collection {
                return collect($provider['costs'] ?? [])->map(
                    fn (array $cost): array => [
                        'courier_code' => Str::lower(trim((string) ($provider['name'] ?? ''))),
                        'courier_name' => $provider['name'] ?? null,
                        'service_type' => $cost['service_type'] ?? null,
                        'vehicle' => $data['vehicle'],
                        'estimation' => $cost['estimation'] ?? null,
                        'cost' => (int) data_get($cost, 'price.shipping_costs', 0),
                        'admin_fee' => (int) data_get($cost, 'price.admin_fee', 0),
                        'total_cost' => (int) data_get($cost, 'price.shipping_costs', 0)
                            + (int) data_get($cost, 'price.admin_fee', 0),
                        'distance' => $distance,
                        'distance_unit' => $distanceUnit,
                    ],
                );
            })->values()->all();

        return [
            'origin' => [
                'latitude' => (float) data_get($data, 'origin.latitude'),
                'longitude' => (float) data_get($data, 'origin.longitude'),
                'address' => (string) data_get($data, 'origin.address'),
            ],
            'destination' => [
                'latitude' => (float) data_get($data, 'destination.latitude'),
                'longitude' => (float) data_get($data, 'destination.longitude'),
                'address' => (string) data_get($data, 'destination.address'),
            ],
            'distance' => $distance,
            'distance_unit' => is_string($distanceUnit) ? $distanceUnit : null,
            'results' => $results,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function expressPickupSchedules(): array
    {
        return collect($this->stcGateway->expressPickupSchedules())
            ->map(fn (array $schedule): array => [
                'time' => $schedule['clock'] ?? null,
                'available_until' => $schedule['until'] ?? null,
                'is_available' => $this->pickupScheduleIsAvailable($schedule),
            ])->values()->all();
    }

    /** @param array<string, mixed> $schedule */
    private function pickupScheduleIsAvailable(array $schedule): bool
    {
        if (filter_var($schedule['libur'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $expiration = $schedule['expired'] ?? null;
        if (is_bool($expiration)) {
            return ! $expiration;
        }
        if (is_numeric($expiration)) {
            return now()->timestamp < (int) $expiration;
        }

        return false;
    }

    /**
     * @return array{pickup_number: string, order_id: string, tracking_number: string|null}
     */
    public function createExpressPickup(
        Trx $trx,
        string $schedule,
        string $pickupMethod = 'PICKUP',
        ?string $referenceCode = null,
    ): array {
        $trx->loadMissing(['shippingExpress', 'details']);
        $shipping = $trx->shippingExpress;
        if (! $shipping) {
            throw new ProcessException('Data pengiriman ekspres tidak ditemukan.');
        }

        return $this->requestExpressPickup(
            $shipping,
            $referenceCode ?? $trx->trx_code,
            (int) $trx->trx_total_price,
            $schedule,
            $pickupMethod === 'DROP-OFF',
            $this->currentSellerPhone($trx),
        );
    }

    /**
     * @return array{pickup_number: string, order_id: string, tracking_number: string|null}
     */
    public function createReturnExpressPickup(
        ReturnModel $return,
        string $schedule,
        ?ShippingCourierExpress $shipping = null,
        ?string $referenceCode = null,
    ): array {
        $return->loadMissing(['shippingExpress', 'details.product', 'trx.details']);
        $shipping ??= $return->shippingExpress;
        if (! $shipping) {
            throw new ProcessException('Data pengiriman ekspres untuk retur tidak ditemukan.');
        }

        $prices = $return->trx?->details
            ->keyBy('trx_detail_product_id')
            ->map(fn ($detail): int => (int) $detail->trx_detail_nett_price)
            ?? collect();
        $itemValue = (int) $return->details->sum(
            fn ($detail): int => (int) $detail->return_detail_qty
                * (int) $prices->get($detail->return_detail_product_id, 0)
        );

        return $this->requestExpressPickup(
            $shipping,
            $referenceCode ?? $return->return_code,
            $itemValue,
            $schedule,
            $shipping->shipping_courier_express_pickup_method === 'DROP-OFF',
        );
    }

    /** @return array{order_id: string, tracking_number: string|null, status: int|string|null} */
    public function createInstantPickup(Trx $trx, ?string $referenceCode = null): array
    {
        $trx->loadMissing(['shippingInstant', 'details']);
        if (! $trx->shippingInstant) {
            throw new ProcessException('Data pengiriman instan tidak ditemukan.');
        }

        return $this->requestInstantPickup(
            $trx->shippingInstant,
            $referenceCode ?? $trx->trx_code,
            (int) $trx->trx_total_price,
        );
    }

    /** @return array<string, mixed> */
    public function trackExpress(string $orderId): array
    {
        if (blank($orderId)) {
            throw new ProcessException('Order ID STC belum tersedia untuk pengiriman ini.');
        }

        return $this->stcGateway->trackExpress($orderId);
    }

    /** @return list<array<string, mixed>> */
    public function trackingHistories(Trx $trx): array
    {
        $trackingOrder = $this->preorderChainService->trackingOrder($trx);
        $shipping = $trackingOrder->shippingExpress;
        if (
            $trackingOrder->trx_shipping_method !== 'courier_express'
            || ! $shipping
            || blank($shipping->shipping_courier_express_order_id)
            || blank($shipping->shipping_courier_express_awb)
        ) {
            return [];
        }

        $tracking = $this->trackExpress(
            (string) $shipping->shipping_courier_express_order_id,
        );

        return collect($tracking['histories'] ?? [])
            ->filter(fn (mixed $history): bool => is_array($history))
            ->values()
            ->all();
    }

    /** @return array{order_id: string, tracking_number: string|null, status: int|string|null} */
    public function createReturnInstantPickup(
        ReturnModel $return,
        ?ShippingCourierInstant $shipping = null,
        ?string $referenceCode = null,
    ): array {
        $return->loadMissing(['shippingInstant', 'details.product', 'trx.details']);
        $shipping ??= $return->shippingInstant;
        if (! $shipping) {
            throw new ProcessException('Data pengiriman instan retur tidak ditemukan.');
        }

        $prices = $return->trx?->details
            ->keyBy('trx_detail_product_id')
            ->map(fn ($detail): int => (int) $detail->trx_detail_nett_price)
            ?? collect();
        $itemValue = (int) $return->details->sum(
            fn ($detail): int => (int) $detail->return_detail_qty
                * (int) $prices->get($detail->return_detail_product_id, 0)
        );

        return $this->requestInstantPickup(
            $shipping,
            $referenceCode ?? $return->return_code,
            $itemValue,
        );
    }

    /**
     * @return array{order_id: string, tracking_number: string|null, status: int|string|null}
     */
    private function requestInstantPickup(
        ShippingCourierInstant $shipping,
        string $referenceCode,
        int $itemValue,
    ): array {
        if (
            $shipping->shipping_courier_instant_origin_latitude === null
            || $shipping->shipping_courier_instant_origin_longitude === null
            || $shipping->shipping_courier_instant_destination_latitude === null
            || $shipping->shipping_courier_instant_destination_longitude === null
        ) {
            throw new ProcessException('Koordinat pengiriman instan belum lengkap.');
        }

        $service = Str::lower(trim((string) $shipping->shipping_courier_instant_expedition_name));
        $serviceType = trim((string) $shipping->shipping_courier_instant_expedition_service);
        $vehicle = Str::lower(trim((string) $shipping->shipping_courier_instant_expedition_vehicle));
        $weight = (int) $shipping->shipping_courier_instant_package_weight;

        if (! in_array($service, InstantShipping::COURIER_CODES, true)) {
            throw new ProcessException('Kurir instan yang dipilih tidak didukung.');
        }
        if ($serviceType === '') {
            throw new ProcessException('Tipe layanan kurir instan belum tersedia.');
        }
        if (! in_array($vehicle, InstantShipping::VEHICLES, true)) {
            throw new ProcessException('Kendaraan kurir instan yang dipilih tidak didukung.');
        }
        if ($weight > InstantShipping::MAX_WEIGHT_GRAMS) {
            throw new ProcessException('Berat paket kurir instan maksimal 40.000 gram.');
        }

        return $this->stcGateway->createInstantPickup([
            'address' => $shipping->shipping_courier_instant_origin_address,
            'phone' => $this->stcPhone(
                $shipping->shipping_courier_instant_origin_phone,
                'Nomor telepon pengirim'
            ),
            'latitude' => (float) $shipping->shipping_courier_instant_origin_latitude,
            'longitude' => (float) $shipping->shipping_courier_instant_origin_longitude,
            'name' => $shipping->shipping_courier_instant_origin_name,
            'packages' => [[
                'order_id' => $referenceCode,
                'destination' => [
                    'name' => $shipping->shipping_courier_instant_destination_name,
                    'phone' => $this->stcPhone(
                        $shipping->shipping_courier_instant_destination_phone,
                        'Nomor telepon penerima'
                    ),
                    'latitude' => (float) $shipping->shipping_courier_instant_destination_latitude,
                    'longitude' => (float) $shipping->shipping_courier_instant_destination_longitude,
                    'address' => $shipping->shipping_courier_instant_destination_address,
                    'address_note' => $shipping->shipping_courier_instant_destination_address_note,
                ],
                'shipping_cost' => (int) $shipping->shipping_courier_instant_cost,
                'service' => $service,
                'service_type' => $serviceType,
                'package_type_id' => 7,
                'vehicle' => $vehicle,
                'items' => [[
                    'name' => self::STC_ITEM_NAME,
                    'description' => 'Paket produk DNY Skincare.',
                    'price' => $itemValue,
                    'weight' => $weight,
                ]],
            ]],
        ]);
    }

    /**
     * @return array{pickup_number: string, order_id: string, tracking_number: string|null}
     */
    private function requestExpressPickup(
        ShippingCourierExpress $shipping,
        string $referenceCode,
        int $itemValue,
        string $schedule,
        bool $dropOff,
        ?string $currentOriginPhone = null,
    ): array {

        $clientCode = trim((string) config('services.stc.client_code'));
        if ($clientCode === '') {
            throw new ProcessException('Layanan pengiriman belum siap digunakan. Silakan hubungi administrator.', 503);
        }

        $origin = $this->locationIds((int) $shipping->shipping_courier_express_origin_subdistrict_id);
        $destination = $this->locationIds(
            (int) $shipping->shipping_courier_express_destination_subdistrict_id
        );

        return $this->stcGateway->createExpressPickup([
            'client_code' => $clientCode,
            'reference_id' => $referenceCode,
            'name' => $shipping->shipping_courier_express_origin_name,
            'address' => $shipping->shipping_courier_express_origin_address,
            'phone' => $this->stcPhone(
                $currentOriginPhone,
                'Nomor telepon pengirim',
                $shipping->shipping_courier_express_origin_phone,
            ),
            'province_id' => $origin['province_id'],
            'city_id' => $origin['city_id'],
            'kecamatan_id' => $origin['district_id'],
            'kelurahan_id' => $origin['subdistrict_id'],
            'zipcode' => $shipping->shipping_courier_express_origin_zipcode ?? '',
            'schedule' => $schedule,
            'packages' => [[
                'destination_name' => $shipping->shipping_courier_express_destination_name,
                'destination_phone' => $this->stcPhone(
                    $shipping->shipping_courier_express_destination_phone,
                    'Nomor telepon penerima'
                ),
                'destination_address' => $shipping->shipping_courier_express_destination_address,
                'destination_province_id' => $destination['province_id'],
                'destination_city_id' => $destination['city_id'],
                'destination_kecamatan_id' => $destination['district_id'],
                'destination_kelurahan_id' => $destination['subdistrict_id'],
                'destination_zipcode' => $shipping->shipping_courier_express_destination_zipcode ?? '',
                'weight' => (int) $shipping->shipping_courier_express_package_weight,
                'width' => (int) $shipping->shipping_courier_express_package_width,
                'height' => (int) $shipping->shipping_courier_express_package_height,
                'length' => (int) $shipping->shipping_courier_express_package_length,
                'item_value' => $itemValue,
                'insurance_amount' => (int) $shipping->shipping_courier_express_insurance,
                'shipping_cost' => (int) $shipping->shipping_courier_express_cost,
                'service' => $shipping->shipping_courier_express_expedition_name,
                'service_type' => $shipping->shipping_courier_express_type,
                'item_name' => self::STC_ITEM_NAME,
                'package_type_id' => 7,
                'cod' => 0,
                'drop' => $dropOff,
                'note' => 'Paket produk DNY Skincare.',
            ]],
        ]);
    }

    private function stcPhone(?string $phone, string $label, ?string $fallbackPhone = null): string
    {
        $normalized = $this->normalizedStcPhone($phone);
        if ($normalized !== null) {
            return $normalized;
        }

        $fallback = $this->normalizedStcPhone($fallbackPhone);
        if ($fallback !== null) {
            return $fallback;
        }

        throw new ProcessException("{$label} harus diawali 08, 02, 628, atau +628.");
    }

    private function normalizedStcPhone(?string $phone): ?string
    {
        $normalized = preg_replace('/(?!^\+)\D+/', '', trim((string) $phone));
        $normalized = $normalized === null ? '' : $normalized;

        if (str_starts_with($normalized, '+62')) {
            $normalized = '62'.substr($normalized, 3);
        }

        if (str_starts_with($normalized, '628')) {
            return $normalized;
        }

        if (str_starts_with($normalized, '08')) {
            return '62'.substr($normalized, 1);
        }

        if (str_starts_with($normalized, '02')) {
            return $normalized;
        }

        if (str_starts_with($normalized, '8')) {
            return '62'.$normalized;
        }

        if (str_starts_with($normalized, '2')) {
            return '0'.$normalized;
        }

        return null;
    }

    private function currentSellerPhone(Trx $trx): ?string
    {
        if ($trx->trx_seller_type === 'warehouse') {
            return Warehouse::query()
                ->whereKey((int) $trx->trx_seller_id)
                ->value('warehouse_phone');
        }

        if (in_array($trx->trx_seller_type, ['distributor', 'agent', 'reseller', 'member'], true)) {
            return Member::query()
                ->whereKey((int) $trx->trx_seller_id)
                ->value('member_mobilephone');
        }

        return null;
    }

    private function member(MemberAccount $account): Member
    {
        $account->loadMissing('member.level');
        $member = $account->member;

        if (! $member || (int) $member->member_status !== 1) {
            throw new ProcessException('Data mitra aktif tidak ditemukan.', 403);
        }

        return $member;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  null|array{type: string, id: int, model: Member|Warehouse}  $directSeller
     * @return array{type: string, id: int, model: Member|Warehouse}
     */
    private function purchaseOriginSeller(Member $member, Collection $items, ?array $directSeller = null): array
    {
        $seller = $directSeller ?? $this->directPurchaseSeller($member);
        $productIds = $items->pluck('product_id')->map(fn (mixed $id): int => (int) $id)->all();
        $stocks = $this->sellerStocks($seller, $productIds);
        $hasStockLine = false;
        $hasPreorderLine = false;

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];
            $availableStock = max(0, (int) ($stocks->get($productId) ?? 0));

            if ($availableStock > 0 && $quantity > $availableStock) {
                throw new ProcessException(
                    "Jumlah produk tidak boleh melebihi stok tersedia. Maksimal {$availableStock} pcs."
                );
            }

            if ($quantity > $availableStock) {
                $hasPreorderLine = true;
            } else {
                $hasStockLine = true;
            }
        }

        if ($hasStockLine && $hasPreorderLine) {
            throw new ProcessException(
                'Pesanan tidak dapat mencampur produk siap kirim dan produk inden. '
                .'Pisahkan menjadi pesanan siap kirim atau pesanan inden.'
            );
        }

        return $hasPreorderLine
            ? $this->preorderChainService->terminalSeller($items, $seller)
            : $seller;
    }

    /** @return array{type: string, id: int, model: Member|Warehouse} */
    private function directPurchaseSeller(Member $member): array
    {
        if ($member->level?->member_level_code === 'DST') {
            $warehouse = Warehouse::query()
                ->whereKey(1)
                ->where('warehouse_is_active', 1)
                ->first();

            if (! $warehouse) {
                throw new ProcessException('Gudang aktif sebagai penjual tidak ditemukan.');
            }

            return ['type' => 'warehouse', 'id' => (int) $warehouse->getKey(), 'model' => $warehouse];
        }

        $parent = Member::query()
            ->with('level')
            ->whereKey($member->member_parent_member_id)
            ->where('member_status', 1)
            ->first();

        if (! $parent) {
            throw new ProcessException('Mitra penjual di atas jaringan tidak ditemukan.');
        }

        return [
            'type' => $this->memberType($parent),
            'id' => (int) $parent->getKey(),
            'model' => $parent,
        ];
    }

    /**
     * @param  array{type: string, id: int, model: Member|Warehouse}  $seller
     * @return array{district_id: int, subdistrict_id: int}
     */
    private function originFromSeller(array $seller): array
    {
        $model = $seller['model'];

        if ($model instanceof Warehouse) {
            return [
                'district_id' => (int) $model->warehouse_district_id,
                'subdistrict_id' => (int) $model->warehouse_subdistrict_id,
            ];
        }

        return [
            'district_id' => (int) $model->member_district_id,
            'subdistrict_id' => (int) $model->member_subdistrict_id,
        ];
    }

    /**
     * @param  array{type: string, id: int, model: Member|Warehouse}  $seller
     * @return array<string, mixed>
     */
    private function originSourceData(array $seller): array
    {
        $model = $seller['model'];

        if ($model instanceof Warehouse) {
            return [
                'source_type' => $seller['type'],
                'source_id' => $seller['id'],
                'name' => $model->warehouse_name,
                'phone' => $model->warehouse_phone,
                'address' => $model->warehouse_address,
            ];
        }

        return [
            'source_type' => $seller['type'],
            'source_id' => $seller['id'],
            'name' => $model->member_name,
            'phone' => $model->member_mobilephone,
            'address' => $model->member_address,
        ];
    }

    /**
     * @param  array{type: string, id: int, model: Member|Warehouse}  $seller
     * @param  list<int>  $productIds
     * @return Collection<int, int>
     */
    private function sellerStocks(array $seller, array $productIds): Collection
    {
        if ($seller['type'] === 'warehouse') {
            return WarehouseStock::query()
                ->where('warehouse_stock_warehouse_id', $seller['id'])
                ->whereIn('warehouse_stock_product_id', $productIds)
                ->get()
                ->mapWithKeys(fn (WarehouseStock $stock): array => [
                    (int) $stock->warehouse_stock_product_id => max(0, (int) $stock->warehouse_stock_balance),
                ]);
        }

        return MemberStock::query()
            ->where('member_stock_member_id', $seller['id'])
            ->whereIn('member_stock_product_id', $productIds)
            ->get()
            ->mapWithKeys(fn (MemberStock $stock): array => [
                (int) $stock->member_stock_product_id => max(0, (int) $stock->member_stock_balance),
            ]);
    }

    private function memberType(Member $member): string
    {
        return match ($member->level?->member_level_code) {
            'DST' => 'distributor',
            'AGT' => 'agent',
            'RSL' => 'reseller',
            default => 'member',
        };
    }

    /** @return array{district_id: int, subdistrict_id: int} */
    private function memberOrigin(Member $member): array
    {
        if (! $member->member_district_id || ! $member->member_subdistrict_id) {
            throw new ProcessException('Alamat asal mitra belum lengkap untuk menghitung ongkir.');
        }

        return [
            'district_id' => (int) $member->member_district_id,
            'subdistrict_id' => (int) $member->member_subdistrict_id,
        ];
    }

    /** @return array{district_id: int, subdistrict_id: int} */
    private function memberAddressDestination(Member $member, int $addressId): array
    {
        $address = MemberAddress::query()
            ->whereKey($addressId)
            ->where('member_address_member_id', $member->getKey())
            ->first();

        if (! $address) {
            throw new ProcessException('Alamat pengiriman tidak ditemukan.');
        }

        return [
            'district_id' => (int) $address->member_address_district_id,
            'subdistrict_id' => (int) $address->member_address_subdistrict_id,
        ];
    }

    /** @return array{district_id: int, subdistrict_id: int} */
    private function customerDestination(Member $member, int $customerId): array
    {
        $customer = Customer::query()
            ->whereKey($customerId)
            ->where('customer_member_id', $member->getKey())
            ->where('customer_is_deleted', 0)
            ->first();

        if (! $customer) {
            throw new ProcessException('Pelanggan tidak ditemukan.');
        }

        return [
            'district_id' => (int) $customer->customer_district_id,
            'subdistrict_id' => (int) $customer->customer_subdistrict_id,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, Product>
     */
    private function products(Collection $items): Collection
    {
        $productIds = $items->pluck('product_id')->map(fn (mixed $id): int => (int) $id)->all();
        $products = Product::query()
            ->availableInCatalog()
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        if ($products->count() !== count($productIds)) {
            throw new ProcessException('Salah satu produk tidak aktif atau tidak tersedia di katalog.');
        }

        return $products;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{weight: int, length: int, width: int, height: int}
     */
    private function package(Collection $products, Collection $items): array
    {
        $invalidProduct = $products->first(fn (Product $product): bool => (int) $product->product_weight < 1
            || (int) $product->product_length < 1
            || (int) $product->product_width < 1
            || (int) $product->product_height < 1
        );

        if ($invalidProduct instanceof Product) {
            throw new ProcessException(
                "Berat dan dimensi produk {$invalidProduct->product_name} belum lengkap. Perbarui master produk sebelum menghitung ongkir.",
                422,
            );
        }

        $weight = 0;
        $length = 0;
        $width = 0;
        $height = 0;

        foreach ($items as $item) {
            $product = $products->get((int) $item['product_id']);
            $quantity = (int) $item['quantity'];
            $weight += (int) $product->product_weight * $quantity;
            $length = max($length, (int) $product->product_length);
            $width = max($width, (int) $product->product_width);
            $height += (int) $product->product_height * $quantity;
        }

        return compact('weight', 'length', 'width', 'height');
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function itemValue(Member $member, Collection $products, Collection $items, string $priceType): int
    {
        $prices = $priceType === 'purchase'
            ? ProductPrice::query()
                ->where('product_price_member_level_id', $member->member_member_level_id)
                ->whereIn('product_price_product_id', $products->keys())
                ->get()
                ->keyBy('product_price_product_id')
            : collect();

        return (int) $items->sum(function (array $item) use ($priceType, $prices, $products): int {
            $product = $products->get((int) $item['product_id']);
            $price = $priceType === 'purchase'
                ? (int) ($prices->get($product->getKey())?->product_price_value ?? $product->product_customer_price)
                : (int) $product->product_customer_price;

            return $price * (int) $item['quantity'];
        });
    }

    /** @return array<string, int|string|null> */
    private function rateLocation(int $districtId, int $subdistrictId): array
    {
        $provinceTable = (new RefProvince)->getTable();
        $cityTable = (new RefCity)->getTable();
        $districtTable = (new RefDistrict)->getTable();
        $subdistrictTable = (new RefSubdistrict)->getTable();
        $location = RefSubdistrict::query()
            ->from("{$subdistrictTable} as subdistrict")
            ->leftJoin(
                "{$districtTable} as district",
                'district.district_id',
                '=',
                'subdistrict.subdistrict_district_id',
            )
            ->leftJoin("{$cityTable} as city", 'city.city_id', '=', 'district.district_city_id')
            ->leftJoin("{$provinceTable} as province", 'province.province_id', '=', 'city.city_province_id')
            ->where('subdistrict.subdistrict_id', $subdistrictId)
            ->where('district.district_id', $districtId)
            ->select([
                'province.province_id',
                'province.province_name',
                'city.city_id',
                'city.city_name',
                'city.city_type',
                'district.district_name',
                'subdistrict.subdistrict_name',
                'subdistrict.subdistrict_zip_code',
            ])
            ->first();
        $cityDisplayName = $location
            ? trim(implode(' ', array_filter([$location->city_type, $location->city_name])))
            : null;
        $displayName = $location
            ? collect([
                $location->subdistrict_name,
                $location->district_name,
                $cityDisplayName,
                $location->province_name,
            ])->filter(fn (mixed $value): bool => filled($value))->implode(', ')
            : null;

        return [
            'province_id' => $location ? (int) $location->province_id : null,
            'province_name' => $location?->province_name,
            'city_id' => $location ? (int) $location->city_id : null,
            'city_name' => $location?->city_name,
            'city_type' => $location?->city_type,
            'district_id' => $districtId,
            'district_name' => $location?->district_name,
            'subdistrict_id' => $subdistrictId,
            'subdistrict_name' => $location?->subdistrict_name,
            'postal_code' => $location?->subdistrict_zip_code,
            'display_name' => $displayName,
        ];
    }

    /** @return array{province_id: int, city_id: int, district_id: int, subdistrict_id: int} */
    private function locationIds(int $subdistrictId): array
    {
        $subdistrict = RefSubdistrict::query()
            ->where('subdistrict_id', $subdistrictId)
            ->first();
        if (! $subdistrict) {
            throw new ProcessException('Kelurahan pengiriman tidak ditemukan pada referensi wilayah.');
        }

        $district = RefDistrict::query()
            ->where('district_id', $subdistrict->subdistrict_district_id)
            ->first();
        $city = $district ? RefCity::query()
            ->where('city_id', $district->district_city_id)
            ->first() : null;
        if (! $district || ! $city) {
            throw new ProcessException('Kecamatan atau kota pengiriman tidak ditemukan pada referensi wilayah.');
        }

        return [
            'province_id' => (int) $city->city_province_id,
            'city_id' => (int) $city->city_id,
            'district_id' => (int) $district->district_id,
            'subdistrict_id' => (int) $subdistrict->subdistrict_id,
        ];
    }
}
