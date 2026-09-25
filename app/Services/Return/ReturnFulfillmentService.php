<?php

namespace App\Services\Return;

use App\Exceptions\ProcessException;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberStock;
use App\Models\MemberStockLog;
use App\Models\Product;
use App\Models\RefCity;
use App\Models\RefDistrict;
use App\Models\RefProvince;
use App\Models\RefSubdistrict;
use App\Models\ReturnModel;
use App\Models\ReturnStatusLog;
use App\Models\ShippingCourierExpress;
use App\Models\ShippingCourierExpressStatus;
use App\Models\ShippingCourierInstant;
use App\Models\ShippingCourierInstantStatus;
use App\Models\ShippingCourierManual;
use App\Models\ShippingCourierManualStatus;
use App\Models\ShippingDetail;
use App\Models\ShippingPickup;
use App\Models\ShippingPickupStatus;
use App\Models\SiteAdministrator;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WarehouseStockLog;
use App\Services\Notification\MemberNotificationService;
use App\Services\Shipping\MemberShippingService;
use App\Support\ShippingInsurance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturnFulfillmentService
{
    public function __construct(
        private readonly MemberShippingService $shippingService,
        private readonly MemberNotificationService $memberNotificationService,
    ) {}

    /** @param list<string> $couriers
     * @return array<string, mixed>
     */
    public function courierOptions(
        ReturnModel $return,
        string $referenceType,
        array $couriers,
        array $items = [],
    ): array {
        $this->ensureShippingOptionsAvailable($return, $referenceType);
        [$origin, $destination] = $this->returnShippingLocations($return, $referenceType);
        $selectedItems = $this->courierRateItems($return, $referenceType, $items);

        return [
            'reference_type' => $referenceType,
            ...$this->shippingService->returnExpressRates(
                $return,
                $origin,
                $destination,
                $referenceType,
                $couriers,
                $selectedItems,
            ),
        ];
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @return list<array{product_id: int, quantity: int}>
     */
    private function courierRateItems(ReturnModel $return, string $referenceType, array $items): array
    {
        if ($items === []) {
            return [];
        }

        $expectedQuantities = $this->quantities(
            $return,
            $referenceType === 'return_replacement',
        )->sortKeys();
        $selectedQuantities = collect($items)
            ->mapWithKeys(fn (array $item): array => [
                (int) $item['product_id'] => (int) $item['quantity'],
            ])
            ->sortKeys();
        if ($referenceType === 'return_replacement'
            && $selectedQuantities->all() !== $expectedQuantities->all()) {
            throw new ProcessException(
                'Jumlah produk pengganti untuk perhitungan kurir harus sama dengan jumlah yang diterima perusahaan.'
            );
        }

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];
            if (! $expectedQuantities->has($productId)
                || $quantity > (int) $expectedQuantities->get($productId)) {
                throw new ProcessException(
                    'Jumlah produk untuk perhitungan kurir tidak boleh melebihi pengajuan retur.'
                );
            }
        }

        return collect($items)->map(fn (array $item): array => [
            'product_id' => (int) $item['product_id'],
            'quantity' => (int) $item['quantity'],
        ])->values()->all();
    }

    /** @return array{reference_type: string, results: list<array<string, mixed>>} */
    public function pickupSchedules(ReturnModel $return, string $referenceType): array
    {
        $this->ensureShippingOptionsAvailable($return, $referenceType);

        return [
            'reference_type' => $referenceType,
            'results' => $this->shippingService->expressPickupSchedules(),
        ];
    }

    /** @return array{reference_type: string, results: list<array<string, mixed>>} */
    public function memberPickupSchedules(ReturnModel $return, MemberAccount $account): array
    {
        $account->loadMissing('member');
        $member = $account->member;
        if (! $member || (int) $return->return_member_id !== (int) $member->getKey()) {
            throw new ProcessException('Data retur tidak ditemukan.', 404);
        }
        if ($return->return_status !== 'approved'
            || $return->return_shipping_method !== 'courier_express') {
            throw new ProcessException('Jadwal pickup kurir belum tersedia untuk retur ini.');
        }

        return [
            'reference_type' => 'return_company',
            'results' => $this->shippingService->expressPickupSchedules(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function prepareReturnToCompanyShipping(ReturnModel $return, array $data): Model
    {
        if ($return->return_status !== 'submitted') {
            throw new ProcessException('Pengiriman retur hanya dapat disiapkan saat pengajuan dibuat.');
        }

        $this->reserveMemberStock($return);

        return $this->createShipping($return, $data, 'return_company');
    }

    /** @param array<string, mixed> $data */
    public function approve(ReturnModel $return, SiteAdministrator $administrator, array $data): ReturnModel
    {
        return $this->withLock($return, function () use ($return, $administrator, $data): ReturnModel {
            return DB::transaction(function () use ($return, $administrator, $data): ReturnModel {
                $lockedReturn = $this->lockedReturn($return);
                if (! in_array($lockedReturn->return_status, ['submitted', 'return_shipping_failed'], true)) {
                    throw new ProcessException('Hanya retur yang menunggu keputusan yang dapat disetujui.');
                }

                $this->ensureMemberStockReserved($lockedReturn);
                $shipping = null;
                $usesLegacyShippingPayload = isset($data['shipping_method']);
                if (isset($data['shipping_method'])) {
                    $shipping = $this->createShipping(
                        $lockedReturn,
                        $data,
                        'return_company',
                    );
                    $lockedReturn->fill([
                        'return_shipping_cost_bearer' => $data['shipping_cost_bearer'] ?? 'member',
                        'return_shipping_method' => $data['shipping_method'],
                        'return_shipping_cost' => $this->shippingCost($data),
                    ]);
                }
                $shipping ??= $this->preparedShipping($lockedReturn, 'return_company');
                if (! $shipping) {
                    throw new ProcessException('Pilihan pengiriman retur belum tersedia.');
                }
                $now = now();
                $lockedReturn->update([
                    'return_status' => 'approved',
                    'return_shipping_cost_bearer' => $lockedReturn->return_shipping_cost_bearer ?: 'member',
                    'return_shipping_method' => $lockedReturn->return_shipping_method,
                    'return_shipping_cost' => (int) $lockedReturn->return_shipping_cost,
                    'return_approved_by' => $administrator->getKey(),
                    'return_approved_datetime' => $now,
                ]);
                $this->log($lockedReturn, 'approved', $data['note'] ?? 'Retur disetujui oleh admin.', $administrator->getKey());

                if ($lockedReturn->return_shipping_method === 'pickup' && filled($data['pickup_pin'] ?? null)) {
                    if (! $shipping instanceof ShippingPickup) {
                        throw new ProcessException('Data pengiriman pickup retur tidak ditemukan.');
                    }
                    $this->validatePickupCode(
                        $lockedReturn,
                        $shipping,
                        (string) $data['pickup_pin'],
                    );
                    $shipping->load('details');
                    $this->receiveReturnByCompany(
                        $lockedReturn,
                        $shipping,
                        $administrator,
                    );
                } elseif ($usesLegacyShippingPayload || $lockedReturn->return_shipping_method === 'pickup') {
                    $this->dispatchShipping(
                        $lockedReturn->refresh(),
                        $shipping,
                        $data + $this->preparedShippingData($shipping),
                        'return_company',
                    );
                    $lockedReturn->update(['return_status' => 'waiting_member_shipment']);
                    $this->log(
                        $lockedReturn,
                        'waiting_member_shipment',
                        'Metode pengiriman retur telah ditentukan. Member diminta menyerahkan barang retur.',
                        $administrator->getKey(),
                    );
                }

                if ($lockedReturn->return_status !== 'received_by_company') {
                    $this->memberNotificationService->returnStatus(
                        $lockedReturn,
                        'Pengajuan Retur Disetujui',
                        "Pengajuan retur {$lockedReturn->return_code} telah disetujui. Buka detail retur untuk melanjutkan proses pengiriman.",
                    );
                }

                return $this->detail($lockedReturn->refresh());
            });
        });
    }

    /** @param array{pickup_schedule: string} $data */
    public function shipReturnToCompany(
        ReturnModel $return,
        MemberAccount $account,
        array $data,
    ): ReturnModel {
        $account->loadMissing('member');
        $member = $account->member;
        if (! $member || (int) $return->return_member_id !== (int) $member->getKey()) {
            throw new ProcessException('Data retur tidak ditemukan.', 404);
        }

        return $this->withLock($return, function () use ($return, $member, $data): ReturnModel {
            return DB::transaction(function () use ($return, $member, $data): ReturnModel {
                $lockedReturn = $this->lockedReturn($return);
                if ($lockedReturn->return_status !== 'approved') {
                    throw new ProcessException('Pengiriman retur hanya dapat dijadwalkan setelah pengajuan disetujui.');
                }
                if ($lockedReturn->return_shipping_method !== 'courier_express') {
                    throw new ProcessException('Jadwal pengiriman hanya digunakan untuk retur dengan metode kurir express.');
                }

                $shipping = $this->preparedShipping($lockedReturn, 'return_company');
                if (! $shipping instanceof ShippingCourierExpress) {
                    throw new ProcessException('Pilihan kurir retur belum tersedia.');
                }

                $shipping->update([
                    'shipping_courier_express_schedule_datetime' => $data['pickup_schedule'],
                ]);
                $shippingData = $this->preparedShippingData($shipping->refresh());
                $shippingData['courier']['pickup_schedule'] = $data['pickup_schedule'];
                $this->dispatchShipping(
                    $lockedReturn,
                    $shipping,
                    $shippingData,
                    'return_company',
                );
                $lockedReturn->update(['return_status' => 'waiting_member_shipment']);
                $this->log(
                    $lockedReturn,
                    'waiting_member_shipment',
                    'Jadwal pengiriman retur telah dipilih oleh member.',
                    $member->getKey(),
                );

                return $this->detail($lockedReturn->refresh());
            });
        });
    }

    public function reject(ReturnModel $return, SiteAdministrator $administrator, ?string $note): ReturnModel
    {
        return DB::transaction(function () use ($return, $administrator, $note): ReturnModel {
            $lockedReturn = $this->lockedReturn($return);
            if ($lockedReturn->return_status !== 'submitted') {
                throw new ProcessException('Pengajuan retur ini sudah pernah diproses.');
            }

            $this->releaseMemberReservation($lockedReturn);
            $lockedReturn->update([
                'return_status' => 'rejected',
                'return_approved_by' => $administrator->getKey(),
                'return_approved_datetime' => now(),
            ]);
            $this->log($lockedReturn, 'rejected', $note ?? 'Retur ditolak oleh admin.', $administrator->getKey());
            $reason = filled($note) ? " Alasan: {$note}" : '';
            $this->memberNotificationService->returnStatus(
                $lockedReturn,
                'Pengajuan Retur Ditolak',
                "Pengajuan retur {$lockedReturn->return_code} ditolak.{$reason}",
            );

            return $this->detail($lockedReturn->refresh());
        });
    }

    public function receive(ReturnModel $return, SiteAdministrator $administrator): ReturnModel
    {
        return DB::transaction(function () use ($return, $administrator): ReturnModel {
            $lockedReturn = $this->lockedReturn($return);
            if (! in_array($lockedReturn->return_status, ['waiting_member_shipment', 'return_in_transit'], true)) {
                throw new ProcessException('Barang retur belum berada pada tahap pengiriman ke perusahaan.');
            }

            if ($lockedReturn->return_shipping_method === 'pickup') {
                throw new ProcessException('Retur pickup diterima menggunakan PIN saat approval admin.');
            }

            $shipping = $this->preparedShipping($lockedReturn, 'return_company');
            if (! $shipping) {
                throw new ProcessException('Data pengiriman retur tidak ditemukan.');
            }
            $this->receiveReturnByCompany($lockedReturn, $shipping, $administrator);

            return $this->detail($lockedReturn->refresh());
        });
    }

    private function receiveReturnByCompany(
        ReturnModel $return,
        Model $shipping,
        SiteAdministrator $administrator,
    ): void {
        $shipping->loadMissing('details');
        $receivedQuantities = $this->recordReceivedQuantities($return, $shipping->details);
        $this->consumeMemberReservation($return, $receivedQuantities);
        $this->completeShippingStatus($return, 'return_company');
        $return->update([
            'return_status' => 'received_by_company',
            'return_received_by' => $administrator->getKey(),
            'return_received_datetime' => now(),
        ]);
        $receivedQuantity = (int) $receivedQuantities->sum();
        $requestedQuantity = (int) $this->quantities($return)->sum();
        $this->log(
            $return,
            'received_by_company',
            "Barang retur diterima Perusahaan {$receivedQuantity} dari {$requestedQuantity} pcs yang diajukan.",
            $administrator->getKey(),
        );
        $this->memberNotificationService->returnStatus(
            $return,
            'Barang Retur Diterima Perusahaan',
            "Barang retur {$return->return_code} telah diterima Perusahaan dan sedang disiapkan penggantinya.",
        );
    }

    /** @param array<string, mixed> $data */
    public function shipReplacement(
        ReturnModel $return,
        SiteAdministrator $administrator,
        array $data,
    ): ReturnModel {
        return $this->withLock($return, function () use ($return, $administrator, $data): ReturnModel {
            return DB::transaction(function () use ($return, $administrator, $data): ReturnModel {
                $lockedReturn = $this->lockedReturn($return);
                if (! in_array($lockedReturn->return_status, ['received_by_company', 'replacement_shipping_failed'], true)) {
                    throw new ProcessException('Barang pengganti hanya dapat dikirim setelah retur diterima perusahaan.');
                }
                if ($lockedReturn->return_shipping_method === 'courier_express'
                    && $data['shipping_method'] !== 'courier_express') {
                    throw new ProcessException(
                        'Barang pengganti wajib dikirim menggunakan Kurir Ekspres karena retur sebelumnya menggunakan Kurir Ekspres.'
                    );
                }

                $this->deductWarehouseStock($lockedReturn);
                $this->reserveReplacementIncoming($lockedReturn);
                $shipping = $this->createShipping(
                    $lockedReturn,
                    $data,
                    'return_replacement',
                );
                $this->dispatchShipping($lockedReturn, $shipping, $data, 'return_replacement');
                $lockedReturn->update([
                    'return_status' => 'replacement_in_transit',
                    'return_replacement_shipping_method' => $data['shipping_method'],
                    'return_replacement_shipping_cost' => $this->shippingCost($data),
                    'return_replacement_shipped_by' => $administrator->getKey(),
                    'return_replacement_shipped_datetime' => now(),
                ]);
                $this->log(
                    $lockedReturn,
                    'replacement_in_transit',
                    $data['note'] ?? ($data['shipping_method'] === 'pickup'
                        ? 'Batch barang pengganti telah dicatat dan siap diambil member.'
                        : 'Barang pengganti telah dikirim oleh Perusahaan.'),
                    $administrator->getKey(),
                );
                $this->memberNotificationService->returnStatus(
                    $lockedReturn,
                    'Barang Pengganti Dikirim',
                    "Barang pengganti untuk retur {$lockedReturn->return_code} telah dikirim. Buka detail retur untuk melihat pengiriman.",
                );

                return $this->detail($lockedReturn->refresh());
            });
        });
    }

    /** @param array<string, mixed> $data */
    public function complete(ReturnModel $return, MemberAccount $account, array $data): ReturnModel
    {
        $account->loadMissing('member');
        $member = $account->member;
        if (! $member || (int) $return->return_member_id !== (int) $member->getKey()) {
            throw new ProcessException('Data retur tidak ditemukan.', 404);
        }

        return DB::transaction(function () use ($return, $member, $data): ReturnModel {
            $lockedReturn = $this->lockedReturn($return);
            if ($lockedReturn->return_status !== 'replacement_in_transit') {
                throw new ProcessException('Barang pengganti belum berada pada tahap pengiriman.');
            }

            if (filled($data['delivery_note_number'] ?? null)) {
                $this->validateDeliveryNoteNumber(
                    $lockedReturn,
                    'return_replacement',
                    (string) $data['delivery_note_number'],
                );
            } elseif (! $this->preparedShipping($lockedReturn, 'return_replacement')) {
                throw new ProcessException('Data pengiriman barang pengganti tidak ditemukan.');
            }
            $this->addReplacementStock($lockedReturn, $member);
            $this->completeShippingStatus($lockedReturn, 'return_replacement');
            $lockedReturn->update([
                'return_status' => 'completed',
                'return_completed_datetime' => now(),
            ]);
            $this->log(
                $lockedReturn,
                'completed',
                'Barang pengganti telah diterima oleh member.',
                $member->getKey(),
            );

            return $this->detail($lockedReturn->refresh());
        });
    }

    public function failShipping(ReturnModel $return, string $referenceType, string $event): void
    {
        $lockedReturn = $this->lockedReturn($return);
        if ($referenceType === 'return_company'
            && in_array($lockedReturn->return_status, ['waiting_member_shipment', 'return_in_transit'], true)) {
            $this->releaseMemberReservation($lockedReturn);
            $lockedReturn->update(['return_status' => 'return_shipping_failed']);
            $this->log(
                $lockedReturn,
                'return_shipping_failed',
                "Pengiriman retur gagal dengan status {$event}. Stok mitra yang ditahan telah dikembalikan.",
                0,
            );
            $this->memberNotificationService->returnStatus(
                $lockedReturn,
                'Pengiriman Retur Gagal',
                "Pengiriman retur {$lockedReturn->return_code} gagal. Buka detail retur untuk mencoba kembali.",
            );

            return;
        }

        if ($referenceType === 'return_replacement'
            && $lockedReturn->return_status === 'replacement_in_transit') {
            $this->restoreReplacementStock($lockedReturn);
            $lockedReturn->update(['return_status' => 'replacement_shipping_failed']);
            $this->log(
                $lockedReturn,
                'replacement_shipping_failed',
                "Pengiriman barang pengganti gagal dengan status {$event}. Stok dalam perjalanan telah dikembalikan ke perusahaan.",
                0,
            );
            $this->memberNotificationService->returnStatus(
                $lockedReturn,
                'Pengiriman Barang Pengganti Gagal',
                "Pengiriman barang pengganti retur {$lockedReturn->return_code} gagal. Buka detail retur untuk informasi selanjutnya.",
            );
        }
    }

    private function lockedReturn(ReturnModel $return): ReturnModel
    {
        return ReturnModel::query()
            ->with(['goodsReceive.trx.details', 'details.product', 'details.goodsReceiveDetail'])
            ->whereKey($return->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function detail(ReturnModel $return): ReturnModel
    {
        return $return->load([
            'goodsReceive.trx',
            'details.product',
            'details.goodsReceiveDetail',
            'member.level',
            'statusLogs',
            'shippingExpress.latestStatus',
            'replacementShippingExpress.latestStatus',
            'shippingInstant.latestStatus',
            'replacementShippingInstant.latestStatus',
            'shippingManual.latestStatus',
            'replacementShippingManual.latestStatus',
            'shippingPickup.latestStatus',
            'replacementShippingPickup.latestStatus',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function createShipping(ReturnModel $return, array $data, string $referenceType): Model
    {
        $warehouse = $this->mainWarehouse();
        [$origin, $destination] = $this->returnShippingLocations($return, $referenceType, $warehouse);
        $package = $this->package($return, $referenceType);

        $shipping = match ($data['shipping_method']) {
            'courier_express' => $this->createExpressShipping(
                $return,
                $data,
                $referenceType,
                $origin,
                $destination,
                $package,
            ),
            'courier_instant' => $this->createInstantShipping(
                $return,
                $data,
                $referenceType,
                $origin,
                $destination,
                $package,
            ),
            'courier_manual' => $this->createManualShipping(
                $return,
                $data,
                $referenceType,
                $origin,
                $destination,
                $package,
            ),
            'pickup' => $this->createPickupShipping($return, $data, $referenceType, $warehouse),
            default => throw new ProcessException('Metode pengiriman retur tidak didukung.'),
        };
        $this->syncReturnShipmentDetails($shipping, $return, $data['items'], $referenceType);

        return $shipping;
    }

    private function ensureShippingOptionsAvailable(ReturnModel $return, string $referenceType): void
    {
        $allowedStatuses = match ($referenceType) {
            'return_company' => ['submitted', 'return_shipping_failed'],
            'return_replacement' => ['received_by_company', 'replacement_shipping_failed'],
            default => throw new ProcessException('Tahap pengiriman retur tidak valid.'),
        };

        if (! in_array($return->return_status, $allowedStatuses, true)) {
            throw new ProcessException('Pilihan pengiriman belum tersedia pada status retur saat ini.');
        }
    }

    private function preparedShipping(ReturnModel $return, string $referenceType): ?Model
    {
        $method = $referenceType === 'return_company'
            ? $return->return_shipping_method
            : $return->return_replacement_shipping_method;

        return match ($method) {
            'courier_express' => ShippingCourierExpress::query()
                ->where('shipping_courier_express_ref_type', $referenceType)
                ->where('shipping_courier_express_ref_id', $return->getKey())
                ->latest('shipping_courier_express_id')
                ->first(),
            'courier_instant' => ShippingCourierInstant::query()
                ->where('shipping_courier_instant_ref_type', $referenceType)
                ->where('shipping_courier_instant_ref_id', $return->getKey())
                ->latest('shipping_courier_instant_id')
                ->first(),
            'courier_manual' => ShippingCourierManual::query()
                ->where('shipping_courier_manual_ref_type', $referenceType)
                ->where('shipping_courier_manual_ref_id', $return->getKey())
                ->latest('shipping_courier_manual_id')
                ->first(),
            'pickup' => ShippingPickup::query()
                ->where('shipping_pickup_ref_type', $referenceType)
                ->where('shipping_pickup_ref_id', $return->getKey())
                ->latest('shipping_pickup_id')
                ->first(),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function preparedShippingData(Model $shipping): array
    {
        return match (true) {
            $shipping instanceof ShippingCourierExpress => [
                'shipping_method' => 'courier_express',
                'delivery_note_number' => $shipping->shipping_courier_express_delivery_note_number,
                'courier' => [
                    'pickup_schedule' => $shipping->shipping_courier_express_schedule_datetime,
                ],
            ],
            $shipping instanceof ShippingCourierInstant => [
                'shipping_method' => 'courier_instant',
                'delivery_note_number' => $shipping->shipping_courier_instant_delivery_note_number,
                'courier' => [],
            ],
            $shipping instanceof ShippingCourierManual => [
                'shipping_method' => 'courier_manual',
                'delivery_note_number' => $shipping->shipping_courier_manual_delivery_note_number,
                'courier' => [],
            ],
            $shipping instanceof ShippingPickup => [
                'shipping_method' => 'pickup',
                'delivery_note_number' => $shipping->shipping_pickup_delivery_note_number,
                'courier' => [
                    'pickup_schedule' => $shipping->shipping_pickup_schedule_datetime,
                ],
            ],
            default => [],
        };
    }

    private function mainWarehouse(): Warehouse
    {
        $warehouse = Warehouse::query()->find(1);
        if (! $warehouse) {
            throw new ProcessException('Gudang utama perusahaan tidak ditemukan.');
        }

        return $warehouse;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function returnShippingLocations(
        ReturnModel $return,
        string $referenceType,
        ?Warehouse $warehouse = null,
    ): array {
        $warehouse ??= $this->mainWarehouse();
        $memberLocation = $this->memberLocation($return);
        $warehouseLocation = $this->warehouseLocation($warehouse);

        return match ($referenceType) {
            'return_company' => [$memberLocation, $warehouseLocation],
            'return_replacement' => [$warehouseLocation, $memberLocation],
            default => throw new ProcessException('Tahap pengiriman retur tidak valid.'),
        };
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>  $origin
     * @param  array<string, mixed>  $destination
     * @param  array{weight: int, length: int, width: int, height: int}  $package
     */
    private function createExpressShipping(
        ReturnModel $return,
        array $data,
        string $referenceType,
        array $origin,
        array $destination,
        array $package,
    ): ShippingCourierExpress {
        $courier = $data['courier'];

        return ShippingCourierExpress::query()->create([
            'shipping_courier_express_ref_type' => $referenceType,
            'shipping_courier_express_ref_id' => $return->getKey(),
            'shipping_courier_express_type' => $courier['service_type'] ?? $courier['type'],
            'shipping_courier_express_expedition_name' => $courier['courier_code'] ?? $courier['name'],
            'shipping_courier_express_expedition_service' => $courier['courier_name'] ?? $courier['service'],
            'shipping_courier_express_etd' => $courier['etd'] ?? '',
            'shipping_courier_express_order_id' => '',
            'shipping_courier_express_pickup_method' => $courier['pickup_method'],
            'shipping_courier_express_pickup_number' => '',
            'shipping_courier_express_schedule_datetime' => $courier['pickup_schedule'] ?? null,
            'shipping_courier_express_awb' => null,
            'shipping_courier_express_delivery_note_number' => $data['delivery_note_number'],
            'shipping_courier_express_cost' => $courier['cost'],
            'shipping_courier_express_insurance_is_force' => ShippingInsurance::isForced($courier),
            'shipping_courier_express_insurance' => ShippingInsurance::amount($courier),
            'shipping_courier_express_package_weight' => $package['weight'],
            'shipping_courier_express_package_length' => $package['length'],
            'shipping_courier_express_package_width' => $package['width'],
            'shipping_courier_express_package_height' => $package['height'],
            ...$this->shippingFields('shipping_courier_express', $origin, $destination),
        ]);
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>  $origin
     * @param  array<string, mixed>  $destination
     * @param  array{weight: int, length: int, width: int, height: int}  $package
     */
    private function createInstantShipping(
        ReturnModel $return,
        array $data,
        string $referenceType,
        array $origin,
        array $destination,
        array $package,
    ): ShippingCourierInstant {
        $courier = $data['courier'];

        return ShippingCourierInstant::query()->create([
            'shipping_courier_instant_ref_type' => $referenceType,
            'shipping_courier_instant_ref_id' => $return->getKey(),
            'shipping_courier_instant_type' => $courier['type'],
            'shipping_courier_instant_expedition_name' => $courier['name'],
            'shipping_courier_instant_expedition_service' => $courier['service'],
            'shipping_courier_instant_expedition_vehicle' => $courier['vehicle'],
            'shipping_courier_instant_estimation_hours' => Str::substr((string) ($courier['etd'] ?? ''), 0, 10),
            'shipping_courier_instant_order_id' => '',
            'shipping_courier_instant_awb' => null,
            'shipping_courier_instant_delivery_note_number' => $data['delivery_note_number'],
            'shipping_courier_instant_admin_fee' => $courier['admin_fee'] ?? 0,
            'shipping_courier_instant_cost' => $courier['cost'],
            'shipping_courier_instant_insurance' => 0,
            'shipping_courier_instant_package_weight' => $package['weight'],
            'shipping_courier_instant_origin_name' => Str::substr((string) $origin['name'], 0, 50),
            'shipping_courier_instant_origin_phone' => Str::substr((string) $origin['phone'], 0, 16),
            'shipping_courier_instant_origin_address' => $origin['address'],
            'shipping_courier_instant_origin_address_note' => '',
            'shipping_courier_instant_origin_latitude' => $courier['origin_latitude'],
            'shipping_courier_instant_origin_longitude' => $courier['origin_longitude'],
            'shipping_courier_instant_destination_name' => Str::substr((string) $destination['name'], 0, 50),
            'shipping_courier_instant_destination_phone' => Str::substr((string) $destination['phone'], 0, 16),
            'shipping_courier_instant_destination_address' => $destination['address'],
            'shipping_courier_instant_destination_address_note' => '',
            'shipping_courier_instant_destination_latitude' => $courier['destination_latitude'],
            'shipping_courier_instant_destination_longitude' => $courier['destination_longitude'],
        ]);
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>  $origin
     * @param  array<string, mixed>  $destination
     * @param  array{weight: int, length: int, width: int, height: int}  $package
     */
    private function createManualShipping(
        ReturnModel $return,
        array $data,
        string $referenceType,
        array $origin,
        array $destination,
        array $package,
    ): ShippingCourierManual {
        $courier = $data['courier'];

        return ShippingCourierManual::query()->create([
            'shipping_courier_manual_ref_type' => $referenceType,
            'shipping_courier_manual_ref_id' => $return->getKey(),
            'shipping_courier_manual_name' => $courier['courier_code'] ?? $courier['name'],
            'shipping_courier_manual_service' => $courier['courier_name'] ?? $courier['service'],
            'shipping_courier_manual_type' => $courier['service_type'] ?? $courier['type'] ?? '',
            'shipping_courier_manual_awb' => $courier['tracking_number'],
            'shipping_courier_manual_delivery_note_number' => $data['delivery_note_number'],
            'shipping_courier_manual_price' => $courier['cost'],
            'shipping_courier_manual_package_weight' => $package['weight'],
            'shipping_courier_manual_package_dimension' => "{$package['length']}x{$package['width']}x{$package['height']}",
            ...$this->shippingFields('shipping_courier_manual', $origin, $destination),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function createPickupShipping(
        ReturnModel $return,
        array $data,
        string $referenceType,
        Warehouse $warehouse,
    ): ShippingPickup {
        return ShippingPickup::query()->create([
            'shipping_pickup_ref_type' => $referenceType,
            'shipping_pickup_ref_id' => $return->getKey(),
            'shipping_pickup_seller_address' => $warehouse->warehouse_address,
            'shipping_pickup_seller_name' => $warehouse->warehouse_name,
            'shipping_pickup_seller_mobilephone' => $warehouse->warehouse_phone,
            'shipping_pickup_schedule_datetime' => data_get($data, 'courier.pickup_schedule') ?? now(),
            'shipping_pickup_pin' => (string) random_int(10000, 99999),
            'shipping_pickup_delivery_note_number' => $data['delivery_note_number'] ?? null,
        ]);
    }

    private function reserveMemberStock(ReturnModel $return): void
    {
        foreach ($this->quantities($return) as $productId => $quantity) {
            $stock = MemberStock::query()
                ->where('member_stock_member_id', $return->return_member_id)
                ->where('member_stock_product_id', $productId)
                ->lockForUpdate()
                ->first();
            $available = $stock
                ? (int) $stock->member_stock_balance - (int) $stock->member_stock_transfer_out
                : 0;
            if (! $stock || $available < $quantity) {
                throw new ProcessException('Stok mitra untuk barang retur tidak mencukupi.');
            }
            $stock->increment('member_stock_transfer_out', $quantity);
        }
    }

    private function ensureMemberStockReserved(ReturnModel $return): void
    {
        foreach ($this->quantities($return) as $productId => $quantity) {
            $stock = MemberStock::query()
                ->where('member_stock_member_id', $return->return_member_id)
                ->where('member_stock_product_id', $productId)
                ->lockForUpdate()
                ->first();
            if (! $stock) {
                throw new ProcessException('Stok mitra untuk barang retur tidak mencukupi.');
            }

            $reserved = (int) $stock->member_stock_transfer_out;
            $missingReservation = max(0, $quantity - $reserved);
            if ($missingReservation === 0) {
                continue;
            }

            $available = (int) $stock->member_stock_balance - $reserved;
            if ($available < $missingReservation) {
                throw new ProcessException('Stok mitra untuk barang retur tidak mencukupi.');
            }

            $stock->increment('member_stock_transfer_out', $missingReservation);
        }
    }

    private function releaseMemberReservation(ReturnModel $return): void
    {
        foreach ($this->quantities($return) as $productId => $quantity) {
            $stock = MemberStock::query()
                ->where('member_stock_member_id', $return->return_member_id)
                ->where('member_stock_product_id', $productId)
                ->lockForUpdate()
                ->first();
            if ($stock) {
                $stock->update([
                    'member_stock_transfer_out' => max(
                        0,
                        (int) $stock->member_stock_transfer_out - $quantity,
                    ),
                ]);
            }
        }
    }

    /** @param Collection<int, int> $receivedQuantities */
    private function consumeMemberReservation(
        ReturnModel $return,
        Collection $receivedQuantities,
    ): void {
        foreach ($this->quantities($return) as $productId => $requestedQuantity) {
            $receivedQuantity = (int) $receivedQuantities->get($productId, 0);
            $stock = MemberStock::query()
                ->where('member_stock_member_id', $return->return_member_id)
                ->where('member_stock_product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();
            if ((int) $stock->member_stock_transfer_out < $requestedQuantity
                || (int) $stock->member_stock_balance < $receivedQuantity) {
                throw new ProcessException('Stok yang ditahan untuk barang retur tidak sesuai.');
            }
            $balance = (int) $stock->member_stock_balance - $receivedQuantity;
            $stock->update([
                'member_stock_balance' => $balance,
                'member_stock_transfer_out' => (int) $stock->member_stock_transfer_out
                    - $requestedQuantity,
            ]);
            if ($receivedQuantity === 0) {
                continue;
            }
            MemberStockLog::query()->create([
                'member_stock_log_member_id' => $return->return_member_id,
                'member_stock_log_product_id' => $productId,
                'member_stock_log_type' => 'out',
                'member_stock_log_quantity' => $receivedQuantity,
                'member_stock_log_unit_price' => $this->unitPrice($return, $productId),
                'member_stock_log_balance' => $balance,
                'member_stock_log_note' => "Retur {$return->return_code} diterima Perusahaan",
                'member_stock_log_datetime' => now(),
            ]);
        }
    }

    private function deductWarehouseStock(ReturnModel $return): void
    {
        foreach ($this->quantities($return, true) as $productId => $quantity) {
            $stock = WarehouseStock::query()
                ->where('warehouse_stock_warehouse_id', 1)
                ->where('warehouse_stock_product_id', $productId)
                ->lockForUpdate()
                ->first();
            $available = $stock
                ? (int) $stock->warehouse_stock_balance - (int) $stock->warehouse_stock_transfer_out
                : 0;
            if (! $stock || $available < $quantity) {
                throw new ProcessException('Stok perusahaan untuk barang pengganti tidak mencukupi.');
            }
            $balance = (int) $stock->warehouse_stock_balance - $quantity;
            $stock->update(['warehouse_stock_balance' => $balance]);
            WarehouseStockLog::query()->create([
                'warehouse_stock_log_warehouse_id' => 1,
                'warehouse_stock_log_product_id' => $productId,
                'warehouse_stock_log_type' => 'out',
                'warehouse_stock_log_quantity' => $quantity,
                'warehouse_stock_log_unit_price' => $this->unitPrice($return, $productId),
                'warehouse_stock_log_balance' => $balance,
                'warehouse_stock_log_note' => "Barang pengganti retur {$return->return_code}",
                'warehouse_stock_log_datetime' => now(),
            ]);
        }
    }

    private function reserveReplacementIncoming(ReturnModel $return): void
    {
        foreach ($this->quantities($return, true) as $productId => $quantity) {
            $stock = MemberStock::query()->firstOrCreate(
                [
                    'member_stock_member_id' => $return->return_member_id,
                    'member_stock_product_id' => $productId,
                ],
                [
                    'member_stock_balance' => 0,
                    'member_stock_transfer_in' => 0,
                    'member_stock_transfer_out' => 0,
                ],
            );
            $stock = MemberStock::query()->whereKey($stock->getKey())->lockForUpdate()->firstOrFail();
            $stock->increment('member_stock_transfer_in', $quantity);
        }
    }

    private function restoreReplacementStock(ReturnModel $return): void
    {
        foreach ($this->quantities($return, true) as $productId => $quantity) {
            $warehouseStock = WarehouseStock::query()
                ->where('warehouse_stock_warehouse_id', 1)
                ->where('warehouse_stock_product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();
            $warehouseBalance = (int) $warehouseStock->warehouse_stock_balance + $quantity;
            $warehouseStock->update(['warehouse_stock_balance' => $warehouseBalance]);
            WarehouseStockLog::query()->create([
                'warehouse_stock_log_warehouse_id' => 1,
                'warehouse_stock_log_product_id' => $productId,
                'warehouse_stock_log_type' => 'in',
                'warehouse_stock_log_quantity' => $quantity,
                'warehouse_stock_log_unit_price' => $this->unitPrice($return, $productId),
                'warehouse_stock_log_balance' => $warehouseBalance,
                'warehouse_stock_log_note' => "Pengiriman pengganti retur {$return->return_code} gagal",
                'warehouse_stock_log_datetime' => now(),
            ]);

            $memberStock = MemberStock::query()
                ->where('member_stock_member_id', $return->return_member_id)
                ->where('member_stock_product_id', $productId)
                ->lockForUpdate()
                ->first();
            if ($memberStock) {
                $memberStock->update([
                    'member_stock_transfer_in' => max(
                        0,
                        (int) $memberStock->member_stock_transfer_in - $quantity,
                    ),
                ]);
            }
        }
    }

    private function addReplacementStock(ReturnModel $return, Member $member): void
    {
        foreach ($this->quantities($return, true) as $productId => $quantity) {
            $stock = MemberStock::query()->firstOrCreate(
                [
                    'member_stock_member_id' => $member->getKey(),
                    'member_stock_product_id' => $productId,
                ],
                ['member_stock_balance' => 0],
            );
            $stock = MemberStock::query()->whereKey($stock->getKey())->lockForUpdate()->firstOrFail();
            $balance = (int) $stock->member_stock_balance + $quantity;
            $stock->update([
                'member_stock_balance' => $balance,
                'member_stock_transfer_in' => max(
                    0,
                    (int) $stock->member_stock_transfer_in - $quantity,
                ),
            ]);
            MemberStockLog::query()->create([
                'member_stock_log_member_id' => $member->getKey(),
                'member_stock_log_product_id' => $productId,
                'member_stock_log_type' => 'in',
                'member_stock_log_quantity' => $quantity,
                'member_stock_log_unit_price' => $this->unitPrice($return, $productId),
                'member_stock_log_balance' => $balance,
                'member_stock_log_note' => "Penerimaan barang pengganti retur {$return->return_code}",
                'member_stock_log_datetime' => now(),
            ]);
        }
    }

    /** @return Collection<int, int> */
    private function quantities(ReturnModel $return, bool $received = false)
    {
        $column = $received ? 'return_detail_received_qty' : 'return_detail_qty';

        return $return->details
            ->groupBy('return_detail_product_id')
            ->map(fn ($details): int => (int) $details->sum($column))
            ->filter(fn (int $quantity): bool => $quantity > 0);
    }

    /**
     * @param  iterable<int, ShippingDetail>  $items
     * @return Collection<int, int>
     */
    private function recordReceivedQuantities(ReturnModel $return, iterable $items): Collection
    {
        $receivedByProduct = collect($items)
            ->groupBy('shipping_detail_product_id')
            ->map(fn (Collection $details): int => (int) $details->sum('shipping_detail_qty'));

        foreach ($return->details->sortBy('return_detail_id') as $detail) {
            $productId = (int) $detail->return_detail_product_id;
            $remaining = (int) $receivedByProduct->get($productId, 0);
            $received = min((int) $detail->return_detail_qty, $remaining);
            $detail->update([
                'return_detail_received_qty' => $received,
            ]);
            $receivedByProduct->put($productId, $remaining - $received);
        }
        if ($receivedByProduct->contains(fn (int $quantity): bool => $quantity > 0)) {
            throw new ProcessException('Rincian pengiriman retur tidak sesuai dengan pengajuan.');
        }
        $return->load('details');

        return $this->quantities($return, true);
    }

    private function unitPrice(ReturnModel $return, int $productId): int
    {
        return (int) ($return->trx?->details
            ->firstWhere('trx_detail_product_id', $productId)?->trx_detail_nett_price ?? 0);
    }

    /** @return array{weight: int, length: int, width: int, height: int} */
    private function package(ReturnModel $return, string $referenceType): array
    {
        $weight = 0;
        $length = 1;
        $width = 1;
        $height = 0;
        foreach ($return->details as $detail) {
            $product = $detail->product ?? Product::query()->findOrFail($detail->return_detail_product_id);
            $quantity = $referenceType === 'return_replacement'
                ? (int) $detail->return_detail_received_qty
                : (int) $detail->return_detail_qty;
            if ($quantity === 0) {
                continue;
            }
            $weight += max(1, (int) $product->product_weight) * $quantity;
            $length = max($length, (int) $product->product_length);
            $width = max($width, (int) $product->product_width);
            $height += max(1, (int) $product->product_height) * $quantity;
        }

        return compact('weight', 'length', 'width', 'height');
    }

    /** @return array<string, mixed> */
    private function memberLocation(ReturnModel $return): array
    {
        return $this->location([
            'name' => $return->return_pickup_name,
            'phone' => $return->return_pickup_phone,
            'address' => $return->return_pickup_address,
            'province_id' => $return->return_pickup_province_id,
            'city_id' => $return->return_pickup_city_id,
            'district_id' => $return->return_pickup_district_id,
            'subdistrict_id' => $return->return_pickup_subdistrict_id,
        ]);
    }

    /** @return array<string, mixed> */
    private function warehouseLocation(Warehouse $warehouse): array
    {
        return $this->location([
            'name' => $warehouse->warehouse_name,
            'phone' => $warehouse->warehouse_phone,
            'address' => $warehouse->warehouse_address,
            'province_id' => $warehouse->warehouse_province_id,
            'city_id' => $warehouse->warehouse_city_id,
            'district_id' => $warehouse->warehouse_district_id,
            'subdistrict_id' => $warehouse->warehouse_subdistrict_id,
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function location(array $data): array
    {
        $subdistrict = RefSubdistrict::query()
            ->where('subdistrict_id', $data['subdistrict_id'])
            ->first();

        return [
            ...$data,
            'province_name' => RefProvince::query()->where('province_id', $data['province_id'])->value('province_name') ?? '',
            'city_name' => RefCity::query()->where('city_id', $data['city_id'])->value('city_name') ?? '',
            'district_name' => RefDistrict::query()->where('district_id', $data['district_id'])->value('district_name') ?? '',
            'subdistrict_name' => $subdistrict?->subdistrict_name ?? '',
            'zipcode' => $subdistrict?->subdistrict_zip_code,
        ];
    }

    /** @param array<string, mixed> $origin
     * @param  array<string, mixed>  $destination
     * @return array<string, mixed>
     */
    private function shippingFields(string $prefix, array $origin, array $destination): array
    {
        $fields = [];
        foreach (['origin' => $origin, 'destination' => $destination] as $side => $location) {
            $fields["{$prefix}_{$side}_name"] = Str::substr((string) $location['name'], 0, 50);
            $fields["{$prefix}_{$side}_phone"] = Str::substr((string) $location['phone'], 0, 16);
            $fields["{$prefix}_{$side}_address"] = $location['address'];
            $fields["{$prefix}_{$side}_subdistrict_id"] = (int) $location['subdistrict_id'];
            $fields["{$prefix}_{$side}_subdistrict_name"] = Str::substr((string) $location['subdistrict_name'], 0, 50);
            $fields["{$prefix}_{$side}_district_name"] = Str::substr((string) $location['district_name'], 0, 50);
            $fields["{$prefix}_{$side}_city_name"] = Str::substr((string) $location['city_name'], 0, 50);
            $fields["{$prefix}_{$side}_province_name"] = Str::substr((string) $location['province_name'], 0, 50);
            $fields["{$prefix}_{$side}_zipcode"] = filled($location['zipcode'])
                ? Str::substr((string) $location['zipcode'], 0, 5)
                : null;
        }

        return $fields;
    }

    /** @param array<int, array<string, mixed>> $items */
    private function syncReturnShipmentDetails(
        Model $shipping,
        ReturnModel $return,
        array $items,
        string $referenceType,
    ): void {
        $type = match ($shipping::class) {
            ShippingCourierExpress::class => 'courier_express',
            ShippingCourierInstant::class => 'courier_instant',
            ShippingCourierManual::class => 'courier_manual',
            default => 'pickup',
        };

        $expectedQuantities = $this->quantities(
            $return,
            $referenceType === 'return_replacement',
        )->sortKeys();
        $shippedItems = collect($items);
        $shippedQuantities = $shippedItems
            ->groupBy('product_id')
            ->map(fn (Collection $productItems): int => (int) $productItems->sum('quantity'))
            ->sortKeys();
        if ($referenceType === 'return_replacement'
            && $expectedQuantities->all() !== $shippedQuantities->all()) {
            throw new ProcessException(
                'Seluruh jumlah produk pengganti harus sama dengan jumlah barang yang diterima perusahaan. Produk boleh dipecah ke beberapa batch.'
            );
        }
        if ($referenceType === 'return_company') {
            $hasInvalidQuantity = $shippedQuantities->contains(
                fn (int $quantity, int $productId): bool => ! $expectedQuantities->has($productId)
                    || $quantity > (int) $expectedQuantities->get($productId),
            );
            if ($hasInvalidQuantity) {
                throw new ProcessException(
                    'Jumlah produk yang disetujui tidak boleh melebihi jumlah pengajuan retur.'
                );
            }
        }

        ShippingDetail::query()
            ->where('shipping_detail_shipping_type', $type)
            ->where('shipping_detail_shipping_id', $shipping->getKey())
            ->delete();
        ShippingDetail::query()->insert($shippedItems->map(
            fn (array $item): array => [
                'shipping_detail_shipping_type' => $type,
                'shipping_detail_shipping_id' => $shipping->getKey(),
                'shipping_detail_product_id' => $item['product_id'],
                'shipping_detail_batch_number' => $item['batch_number'],
                'shipping_detail_qty' => $item['quantity'],
                'shipping_detail_expire_date' => $item['expiry_date'],
            ]
        )->all());
    }

    private function validateDeliveryNoteNumber(
        ReturnModel $return,
        string $referenceType,
        string $submittedNumber,
    ): Model {
        $method = $referenceType === 'return_company'
            ? $return->return_shipping_method
            : $return->return_replacement_shipping_method;
        $shipping = match ($method) {
            'courier_express' => ShippingCourierExpress::query()
                ->where('shipping_courier_express_ref_type', $referenceType)
                ->where('shipping_courier_express_ref_id', $return->getKey())
                ->latest('shipping_courier_express_id')
                ->first(),
            'courier_instant' => ShippingCourierInstant::query()
                ->where('shipping_courier_instant_ref_type', $referenceType)
                ->where('shipping_courier_instant_ref_id', $return->getKey())
                ->latest('shipping_courier_instant_id')
                ->first(),
            'courier_manual' => ShippingCourierManual::query()
                ->where('shipping_courier_manual_ref_type', $referenceType)
                ->where('shipping_courier_manual_ref_id', $return->getKey())
                ->latest('shipping_courier_manual_id')
                ->first(),
            'pickup' => ShippingPickup::query()
                ->where('shipping_pickup_ref_type', $referenceType)
                ->where('shipping_pickup_ref_id', $return->getKey())
                ->latest('shipping_pickup_id')
                ->first(),
            default => null,
        };
        $deliveryNoteNumber = match (true) {
            $shipping instanceof ShippingCourierExpress => $shipping->shipping_courier_express_delivery_note_number,
            $shipping instanceof ShippingCourierInstant => $shipping->shipping_courier_instant_delivery_note_number,
            $shipping instanceof ShippingCourierManual => $shipping->shipping_courier_manual_delivery_note_number,
            $shipping instanceof ShippingPickup => $shipping->shipping_pickup_delivery_note_number,
            default => null,
        };

        if (blank($deliveryNoteNumber) || $deliveryNoteNumber !== $submittedNumber) {
            throw new ProcessException(
                'Nomor surat jalan tidak sesuai dengan pengiriman retur yang harus diterima.'
            );
        }

        return $shipping->load('details');
    }

    private function validatePickupCode(
        ReturnModel $return,
        ShippingPickup $shipping,
        ?string $pin,
    ): void {
        if (blank($shipping->shipping_pickup_pin)) {
            throw new ProcessException('Kode pengambilan untuk retur ini tidak ditemukan.');
        }

        if ($pin === null || $pin === '') {
            throw ValidationException::withMessages([
                'pickup_pin' => ['PIN pickup wajib diisi untuk memproses penerimaan retur.'],
            ]);
        }

        if (! hash_equals((string) $shipping->shipping_pickup_pin, $pin)) {
            throw ValidationException::withMessages([
                'pickup_pin' => ['PIN pickup tidak sesuai.'],
            ]);
        }

        $latestStatus = ShippingPickupStatus::query()
            ->where('shipping_pickup_status_shipping_pickup_id', $shipping->getKey())
            ->lockForUpdate()
            ->latest('shipping_pickup_status_id')
            ->value('shipping_pickup_status_value');

        if (! in_array($latestStatus, ['picked_up', 'completed'], true)) {
            $this->pickupStatus($return, $shipping, 'picked_up');
        }
    }

    /** @param array<string, mixed> $data */
    private function dispatchShipping(
        ReturnModel $return,
        Model $shipping,
        array $data,
        string $referenceType,
    ): void {
        $referenceCode = $referenceType === 'return_replacement'
            ? $return->return_code.'/R'
            : $return->return_code;

        if ($shipping instanceof ShippingCourierExpress) {
            $pickup = $this->shippingService->createReturnExpressPickup(
                $return,
                $data['courier']['pickup_schedule'],
                $shipping,
                $referenceCode,
            );
            $shipping->update([
                'shipping_courier_express_order_id' => $pickup['order_id'],
                'shipping_courier_express_pickup_number' => $pickup['pickup_number'],
                'shipping_courier_express_awb' => $pickup['tracking_number'],
            ]);
            $this->expressStatus($return, $shipping, 'processed_packages', $pickup['order_id']);

            return;
        }

        if ($shipping instanceof ShippingCourierInstant) {
            $pickup = $this->shippingService->createReturnInstantPickup($return, $shipping, $referenceCode);
            $shipping->update([
                'shipping_courier_instant_order_id' => $pickup['order_id'],
                'shipping_courier_instant_awb' => $pickup['tracking_number'],
            ]);
            $this->instantStatus($return, $shipping, 'processed_packages', $pickup['order_id']);

            return;
        }

        if ($shipping instanceof ShippingCourierManual) {
            $this->manualStatus($return, $shipping, 'processed_packages');

            return;
        }

        if ($shipping instanceof ShippingPickup) {
            $this->pickupStatus($return, $shipping, 'ready_to_pickup');

            return;
        }

        throw new ProcessException('Data pengiriman retur tidak didukung.');
    }

    private function expressStatus(
        ReturnModel $return,
        ShippingCourierExpress $shipping,
        string $status,
        ?string $externalReference = null,
    ): void {
        ShippingCourierExpressStatus::query()->create([
            'shipping_courier_express_status_shipping_courier_express_id' => $shipping->getKey(),
            'shipping_courier_express_status_ref_type' => $shipping->shipping_courier_express_ref_type,
            'shipping_courier_express_status_ref_id' => $return->getKey(),
            'shipping_courier_express_status_value' => $status,
            'shipping_courier_express_status_note' => $status === 'completed'
                ? 'Pengiriman retur telah diterima.'
                : 'Order kurir retur berhasil dibuat.',
            'shipping_courier_express_status_datetime' => now(),
            'shipping_courier_express_status_ref_code' => $return->return_code,
            'shipping_courier_express_status_external_ref_code' => $externalReference,
        ]);
    }

    private function instantStatus(
        ReturnModel $return,
        ShippingCourierInstant $shipping,
        string $status,
        ?string $externalReference = null,
    ): void {
        ShippingCourierInstantStatus::query()->create([
            'shipping_courier_instant_status_shipping_courier_instant_id' => $shipping->getKey(),
            'shipping_courier_instant_status_ref_type' => $shipping->shipping_courier_instant_ref_type,
            'shipping_courier_instant_status_ref_id' => $return->getKey(),
            'shipping_courier_instant_status_value' => $status,
            'shipping_courier_instant_status_note' => $status === 'completed'
                ? 'Pengiriman retur telah diterima.'
                : 'Order kurir retur berhasil dibuat.',
            'shipping_courier_instant_status_datetime' => now(),
            'shipping_courier_instant_status_ref_code' => Str::substr($return->return_code, 0, 20),
            'shipping_courier_instant_status_external_ref_code' => $externalReference,
        ]);
    }

    private function manualStatus(
        ReturnModel $return,
        ShippingCourierManual $shipping,
        string $status,
    ): void {
        ShippingCourierManualStatus::query()->create([
            'shipping_courier_manual_status_shipping_courier_manual_id' => $shipping->getKey(),
            'shipping_courier_manual_status_ref_type' => $shipping->shipping_courier_manual_ref_type,
            'shipping_courier_manual_status_ref_id' => $return->getKey(),
            'shipping_courier_manual_status_value' => $status,
            'shipping_courier_manual_status_note' => $status === 'completed'
                ? 'Pengiriman retur telah diterima.'
                : 'Nomor resi manual telah dicatat.',
            'shipping_courier_manual_status_datetime' => now(),
        ]);
    }

    private function pickupStatus(ReturnModel $return, ShippingPickup $shipping, string $status): void
    {
        ShippingPickupStatus::query()->create([
            'shipping_pickup_status_shipping_pickup_id' => $shipping->getKey(),
            'shipping_pickup_status_ref_type' => $shipping->shipping_pickup_ref_type,
            'shipping_pickup_status_ref_id' => $return->getKey(),
            'shipping_pickup_status_value' => $status,
            'shipping_pickup_status_datetime' => now(),
        ]);
    }

    private function completeShippingStatus(ReturnModel $return, string $referenceType): void
    {
        $method = $referenceType === 'return_company'
            ? $return->return_shipping_method
            : $return->return_replacement_shipping_method;

        switch ($method) {
            case 'courier_express':
                $this->completeExpressStatus($return, $referenceType);
                break;
            case 'courier_instant':
                $this->completeInstantStatus($return, $referenceType);
                break;
            case 'courier_manual':
                $this->completeManualStatus($return, $referenceType);
                break;
            case 'pickup':
                $this->completePickupStatus($return, $referenceType);
                break;
        }
    }

    private function completeExpressStatus(ReturnModel $return, string $referenceType): void
    {
        $shipping = ShippingCourierExpress::query()
            ->where('shipping_courier_express_ref_type', $referenceType)
            ->where('shipping_courier_express_ref_id', $return->getKey())
            ->latest('shipping_courier_express_id')
            ->first();
        if ($shipping) {
            $this->expressStatus($return, $shipping, 'completed');
        }
    }

    private function completeInstantStatus(ReturnModel $return, string $referenceType): void
    {
        $shipping = ShippingCourierInstant::query()
            ->where('shipping_courier_instant_ref_type', $referenceType)
            ->where('shipping_courier_instant_ref_id', $return->getKey())
            ->latest('shipping_courier_instant_id')
            ->first();
        if ($shipping) {
            $this->instantStatus($return, $shipping, 'completed');
        }
    }

    private function completeManualStatus(ReturnModel $return, string $referenceType): void
    {
        $shipping = ShippingCourierManual::query()
            ->where('shipping_courier_manual_ref_type', $referenceType)
            ->where('shipping_courier_manual_ref_id', $return->getKey())
            ->latest('shipping_courier_manual_id')
            ->first();
        if ($shipping) {
            $this->manualStatus($return, $shipping, 'completed');
        }
    }

    private function completePickupStatus(ReturnModel $return, string $referenceType): void
    {
        $shipping = ShippingPickup::query()
            ->where('shipping_pickup_ref_type', $referenceType)
            ->where('shipping_pickup_ref_id', $return->getKey())
            ->latest('shipping_pickup_id')
            ->first();
        if ($shipping) {
            $this->pickupStatus($return, $shipping, 'completed');
        }
    }

    /** @param array<string, mixed> $data */
    private function shippingCost(array $data): int
    {
        return $data['shipping_method'] === 'pickup'
            ? 0
            : (int) data_get($data, 'courier.cost', 0);
    }

    private function log(ReturnModel $return, string $status, string $note, int $creatorId): void
    {
        ReturnStatusLog::query()->create([
            'return_status_log_return_id' => $return->getKey(),
            'return_status_log_status' => $status,
            'return_status_log_note' => Str::substr($note, 0, 255),
            'return_status_log_created_by' => $creatorId,
            'return_status_log_created_datetime' => now(),
        ]);
    }

    /** @template T
     * @param  callable(): T  $callback
     * @return T
     */
    private function withLock(ReturnModel $return, callable $callback): mixed
    {
        $lock = Cache::lock("return-fulfillment:{$return->getKey()}", 30);
        if (! $lock->get()) {
            throw new ProcessException('Proses retur sedang berjalan. Silakan coba kembali.', 409);
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
