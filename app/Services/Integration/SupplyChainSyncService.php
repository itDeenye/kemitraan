<?php

namespace App\Services\Integration;

use App\Contracts\Integrations\SupplyChainGateway;
use App\Exceptions\ProcessException;
use App\Exceptions\SupplyChainRequestException;
use App\Models\Member;
use App\Models\MemberSupplyChainSync;
use App\Models\ShippingDetail;
use App\Models\Trx;
use App\Models\TrxSupplyChainSync;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SupplyChainSyncService
{
    private const API_PREFIX = '/api/v1/penjualan-supply-chain';

    public function __construct(private readonly SupplyChainGateway $gateway) {}

    public function enabled(): bool
    {
        return (bool) config('services.supply_chain.enabled', false);
    }

    public function approvalCallbackEnabled(): bool
    {
        return (bool) config('services.supply_chain.approval_callback_enabled', false);
    }

    public function syncMember(Member $member): MemberSupplyChainSync
    {
        $sync = MemberSupplyChainSync::query()->firstOrCreate([
            'member_supply_chain_sync_member_id' => $member->getKey(),
        ], [
            'member_supply_chain_sync_status' => 'pending',
        ]);

        if (! $this->enabled()
            || ($sync->member_supply_chain_sync_status === 'synced'
                && filled($sync->member_supply_chain_sync_customer_no))) {
            if (filled($sync->member_supply_chain_sync_customer_no)) {
                $this->persistMemberCode(
                    $member,
                    (string) $sync->member_supply_chain_sync_customer_no,
                );
            }

            return $sync;
        }

        try {
            $param = $this->memberPayload($member);
            $sync->update([
                'member_supply_chain_sync_status' => 'processing',
                'member_supply_chain_sync_url' => $this->endpointUrl('tambah-customer'),
                'member_supply_chain_sync_param' => $param,
                'member_supply_chain_sync_error' => null,
            ]);
            $response = $this->gateway->createCustomer($param);
            $sync->update([
                'member_supply_chain_sync_customer_no' => (string) $response['cust_no'],
                'member_supply_chain_sync_status' => 'synced',
                'member_supply_chain_sync_response' => $response,
                'member_supply_chain_sync_error' => null,
                'member_supply_chain_sync_synced_datetime' => now(),
            ]);
            $this->persistMemberCode($member, (string) $response['cust_no']);

            return $sync->refresh();
        } catch (Throwable $exception) {
            $values = [
                'member_supply_chain_sync_status' => 'failed',
                'member_supply_chain_sync_error' => Str::limit($exception->getMessage(), 65000),
            ];
            if ($exception instanceof SupplyChainRequestException) {
                $values['member_supply_chain_sync_response'] = $this->failureResponse($exception);
            }
            $sync->update($values);

            throw $exception;
        }
    }

    public function shouldSyncSale(Trx $trx): bool
    {
        return $this->enabled()
            && $trx->trx_seller_type === 'warehouse'
            && $trx->trx_type === 'stock'
            && in_array($trx->trx_buyer_type, ['distributor', 'agent', 'reseller'], true)
            && $trx->trx_status === 'processing';
    }

    private function persistMemberCode(Member $member, string $customerNumber): void
    {
        if ($member->member_supply_chain_code === $customerNumber) {
            return;
        }

        $member->forceFill([
            'member_supply_chain_code' => $customerNumber,
        ])->save();
    }

    private function persistTransactionCode(Trx $trx, string $saleNumber): void
    {
        $saleNumber = trim($saleNumber);
        if ($saleNumber === '' || $trx->trx_supply_chain_code === $saleNumber) {
            return;
        }

        $trx->forceFill([
            'trx_supply_chain_code' => $saleNumber,
        ])->save();
    }

    public function syncSale(Trx $trx): Trx
    {
        if (! $this->shouldSyncSale($trx)) {
            return $trx;
        }

        $sync = TrxSupplyChainSync::query()->firstOrCreate([
            'trx_supply_chain_sync_trx_id' => $trx->getKey(),
        ], [
            'trx_supply_chain_sync_status' => 'pending',
        ]);
        if ($sync->trx_supply_chain_sync_status === 'synced') {
            $this->persistTransactionCode(
                $trx,
                (string) data_get($sync->trx_supply_chain_sync_response, 'save.jual_no'),
            );

            return $trx;
        }

        $trx->loadMissing([
            'buyer.level',
            'buyer.defaultAddress.province',
            'buyer.defaultAddress.city',
            'buyer.defaultAddress.district',
            'sellerWarehouse',
            'details',
            'shippingExpress',
            'shippingInstant',
            'shippingManual',
            'shippingPickup',
        ]);

        if (! $trx->buyer instanceof Member || ! $trx->sellerWarehouse) {
            throw new ProcessException('Data pembeli atau gudang transaksi Supply Chain tidak lengkap.');
        }

        $sync->update([
            'trx_supply_chain_sync_status' => 'processing',
            'trx_supply_chain_sync_error' => null,
        ]);

        $requestStage = null;

        try {
            $memberSync = $this->syncMember($trx->buyer);
            $responses = is_array($sync->trx_supply_chain_sync_response)
                ? $sync->trx_supply_chain_sync_response
                : [];
            $stage = $sync->trx_supply_chain_sync_stage;

            if (! in_array($stage, ['saved', 'posted', 'approved'], true)) {
                $param = $this->salePayload(
                    $trx,
                    $trx->buyer,
                    (string) $memberSync->member_supply_chain_sync_customer_no,
                );
                $requestStage = 'save';
                $this->persistRequest($sync, 'save', 'simpan', $param);
                $responses['save'] = $this->gateway->saveSale($param);
                $stage = 'saved';
                $this->persistStage($sync, $stage, $responses);
            }

            $saleNumber = (string) data_get($responses, 'save.jual_no');
            if ($saleNumber === '') {
                throw new ProcessException('Nomor penjualan Supply Chain tidak tersedia untuk diposting.');
            }
            $this->persistTransactionCode($trx, $saleNumber);

            if ($this->approvalCallbackEnabled()) {
                $sync->update([
                    'trx_supply_chain_sync_status' => 'awaiting_callback',
                    'trx_supply_chain_sync_stage' => $stage,
                    'trx_supply_chain_sync_response' => $responses,
                    'trx_supply_chain_sync_error' => null,
                    'trx_supply_chain_sync_synced_datetime' => null,
                ]);

                return $trx->refresh();
            }

            if ($stage === 'saved') {
                $param = ['jual_no' => $saleNumber];
                $requestStage = 'posting';
                $this->persistRequest($sync, 'posting', 'posting', $param);
                $responses['posting'] = $this->gateway->postSale($saleNumber);
                $stage = 'posted';
                $this->persistStage($sync, $stage, $responses);
            }

            if ($stage === 'posted') {
                $param = ['jual_no' => $saleNumber];
                $requestStage = 'approve';
                $this->persistRequest($sync, 'approve', 'approve', $param);
                $responses['approve'] = $this->gateway->approveSale($saleNumber);
                $stage = 'approved';
                $this->persistStage($sync, $stage, $responses);
            }

            $this->applyApprovalShipping($trx, Arr::get($responses, 'approve', []));
            $sync->update([
                'trx_supply_chain_sync_status' => 'synced',
                'trx_supply_chain_sync_stage' => 'approved',
                'trx_supply_chain_sync_response' => $responses,
                'trx_supply_chain_sync_error' => null,
                'trx_supply_chain_sync_synced_datetime' => now(),
            ]);

            return $trx->refresh();
        } catch (Throwable $exception) {
            $values = [
                'trx_supply_chain_sync_status' => 'failed',
                'trx_supply_chain_sync_error' => Str::limit($exception->getMessage(), 65000),
            ];
            if ($requestStage !== null && $exception instanceof SupplyChainRequestException) {
                $responses = is_array($sync->trx_supply_chain_sync_response)
                    ? $sync->trx_supply_chain_sync_response
                    : [];
                $responses[$requestStage] = $this->failureResponse($exception);
                $values['trx_supply_chain_sync_response'] = $responses;
            }
            $sync->update($values);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function receiveSaleApproval(array $payload, string $callbackUrl): array
    {
        $saleNumber = trim((string) ($payload['invoice_number'] ?? ''));
        if ($saleNumber === '') {
            throw new ProcessException('Nomor faktur Supply Chain wajib dikirim.', 422);
        }

        return DB::transaction(function () use ($payload, $callbackUrl, $saleNumber): array {
            $trx = Trx::query()
                ->where('trx_supply_chain_code', $saleNumber)
                ->lockForUpdate()
                ->first();
            if (! $trx) {
                throw new ProcessException('Transaksi Kemitraan untuk faktur Supply Chain tidak ditemukan.', 404);
            }
            if ($trx->trx_seller_type !== 'warehouse'
                || $trx->trx_type !== 'stock'
                || ! in_array($trx->trx_buyer_type, ['distributor', 'agent', 'reseller'], true)) {
                throw new ProcessException('Transaksi callback bukan pembelian stok ke perusahaan.', 422);
            }

            $this->persistTransactionCode($trx, $saleNumber);
            $trx->loadMissing([
                'details',
                'shippingExpress',
                'shippingInstant',
                'shippingManual',
                'shippingPickup',
            ]);
            $shipping = $this->applyApprovalShipping(
                $trx,
                $this->callbackApprovalPayload($payload),
                true,
            );

            $sync = TrxSupplyChainSync::query()->firstOrCreate([
                'trx_supply_chain_sync_trx_id' => $trx->getKey(),
            ], [
                'trx_supply_chain_sync_status' => 'pending',
            ]);
            $urls = is_array($sync->trx_supply_chain_sync_url)
                ? $sync->trx_supply_chain_sync_url
                : [];
            $params = is_array($sync->trx_supply_chain_sync_param)
                ? $sync->trx_supply_chain_sync_param
                : [];
            $responses = is_array($sync->trx_supply_chain_sync_response)
                ? $sync->trx_supply_chain_sync_response
                : [];
            $urls['callback'] = $callbackUrl;
            $params['callback'] = $payload;
            $responses['callback'] = [
                'accepted' => true,
                'invoice_number' => $saleNumber,
                'delivery_note_number' => $shipping['delivery_note_number'],
                'batch_count' => $shipping['batch_count'],
                'received_at' => now()->toISOString(),
            ];
            $sync->update([
                'trx_supply_chain_sync_status' => 'synced',
                'trx_supply_chain_sync_stage' => 'approved',
                'trx_supply_chain_sync_url' => $urls,
                'trx_supply_chain_sync_param' => $params,
                'trx_supply_chain_sync_response' => $responses,
                'trx_supply_chain_sync_error' => null,
                'trx_supply_chain_sync_synced_datetime' => now(),
            ]);

            return [
                'transaction_code' => (string) $trx->trx_code,
                'invoice_number' => $saleNumber,
                'delivery_note_number' => $shipping['delivery_note_number'],
                'batch_count' => $shipping['batch_count'],
                'batch_quantity' => $shipping['batch_quantity'],
                'sync_status' => 'synced',
            ];
        });
    }

    /** @return array<string, mixed> */
    public function memberPayload(Member $member): array
    {
        $member->loadMissing([
            'level',
            'defaultAddress.province',
            'defaultAddress.city',
            'defaultAddress.district',
        ]);
        $address = $member->defaultAddress;
        if (! $address) {
            throw new ProcessException('Alamat utama member belum tersedia untuk sinkronisasi Supply Chain.');
        }

        $level = match ($member->level?->member_level_code) {
            'DST' => 'DU',
            'AGT' => 'AGT',
            'RSL' => 'RSL',
            default => null,
        };
        if ($level === null) {
            throw new ProcessException('Level member belum didukung oleh Supply Chain.');
        }

        return array_filter([
            'Cust_Name' => Str::limit((string) $member->member_name, 100, ''),
            'provinsi' => (string) $address->province?->province_name,
            'kabupaten' => (string) $address->city?->city_name,
            'kecamatan' => (string) $address->district?->district_name,
            'cust_phone1' => (string) ($address->member_address_phone ?: $member->member_mobilephone),
            'Cust_Addres' => Str::limit((string) $address->member_address_full, 255, ''),
            'cust_birthday' => $member->member_birth_date?->format('Y-m-d'),
            'no_kemitraan' => $this->externalCode((string) $member->member_code),
            'level' => $level,
            'NoKTP' => filled($member->member_identity_no) ? (string) $member->member_identity_no : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function externalCode(string $code): string
    {
        if (app()->environment('production')) {
            return $code;
        }

        $prefix = trim((string) config('services.supply_chain.member_prefix'), " /\\\t\n\r\0\x0B");

        if ($prefix === '' || Str::startsWith($code, $prefix.'/')) {
            return $code;
        }

        return $prefix.'/'.$code;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function callbackApprovalPayload(array $payload): array
    {
        $products = collect((array) ($payload['products'] ?? []))
            ->map(static fn (array $product): array => [
                'brg_code' => $product['product_code'],
                'jual_qty' => $product['quantity'],
                'no_batch' => $product['batch_number'],
                'expired_date' => $product['expiry_date'],
            ])
            ->values()
            ->all();

        return [
            'surat_jalan' => [
                'header' => ['sj_no' => $payload['delivery_note_number'] ?? null],
                'detail_label' => ['products' => $products],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function salePayload(Trx $trx, Member $buyer, string $customerNumber): array
    {
        $address = $buyer->defaultAddress;
        if (! $address) {
            throw new ProcessException('Alamat utama pembeli belum tersedia untuk penjualan Supply Chain.');
        }

        $products = $trx->details->map(function ($detail, int $position): array {
            $quantity = (int) $detail->trx_detail_qty;
            $netPrice = (int) $detail->trx_detail_nett_price;

            return [
                'kode_produk' => $this->externalProductCode(
                    (string) $detail->trx_detail_product_code,
                    $position,
                ),
                'jual_qty' => $quantity,
                'jual_harga' => (int) $detail->trx_detail_product_price,
                'jual_diskonqty' => 0,
                'jual_diskon' => (int) $detail->trx_detail_discount_value * $quantity,
                'jual_total' => $netPrice * $quantity,
            ];
        })->values()->all();

        if ($products === []) {
            throw new ProcessException('Produk transaksi Supply Chain tidak ditemukan.');
        }

        return [
            'cust_no' => $customerNumber,
            'nama_pengirim' => Str::limit((string) $trx->sellerWarehouse->warehouse_name, 100, ''),
            'telp_pengirim' => (string) $trx->sellerWarehouse->warehouse_phone,
            'nama_penerima' => Str::limit((string) $buyer->member_name, 100, ''),
            'alamat_penerima' => Str::limit((string) $address->member_address_full, 255, ''),
            'telp_penerima' => (string) ($address->member_address_phone ?: $buyer->member_mobilephone),
            'jual_total' => array_sum(array_column($products, 'jual_total')),
            'ongkos_kirim' => (int) $trx->trx_shipping_cost,
            'jual_ket' => $this->externalCode((string) $trx->trx_code),
            'produk' => $products,
        ];
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    private function persistStage(TrxSupplyChainSync $sync, string $stage, array $responses): void
    {
        $sync->update([
            'trx_supply_chain_sync_status' => 'processing',
            'trx_supply_chain_sync_stage' => $stage,
            'trx_supply_chain_sync_response' => $responses,
            'trx_supply_chain_sync_error' => null,
        ]);
    }

    /** @param array<string, mixed> $param */
    private function persistRequest(
        TrxSupplyChainSync $sync,
        string $stage,
        string $endpoint,
        array $param,
    ): void {
        $urls = is_array($sync->trx_supply_chain_sync_url)
            ? $sync->trx_supply_chain_sync_url
            : [];
        $params = is_array($sync->trx_supply_chain_sync_param)
            ? $sync->trx_supply_chain_sync_param
            : [];
        $urls[$stage] = $this->endpointUrl($endpoint);
        $params[$stage] = $param;

        $sync->update([
            'trx_supply_chain_sync_url' => $urls,
            'trx_supply_chain_sync_param' => $params,
        ]);
    }

    private function endpointUrl(string $endpoint): string
    {
        return rtrim((string) config('services.supply_chain.base_url'), '/')
            .self::API_PREFIX.'/'.ltrim($endpoint, '/');
    }

    private function externalProductCode(string $productCode, int $position): string
    {
        if (app()->environment('production')) {
            return $productCode;
        }

        $testingCodes = array_values(array_filter(array_map(
            static fn (mixed $code): string => strtoupper(trim((string) $code)),
            (array) config('services.supply_chain.testing_product_codes', []),
        )));
        $normalizedProductCode = strtoupper(trim($productCode));
        if (in_array($normalizedProductCode, $testingCodes, true)) {
            return $normalizedProductCode;
        }

        $fallback = strtoupper(trim((string) config(
            'services.supply_chain.testing_product_fallback',
            'DNY0003',
        )));
        $fallbackPosition = array_search($fallback, $testingCodes, true);
        if ($fallbackPosition === false || $testingCodes === []) {
            throw new ProcessException('Kode produk fallback Supply Chain tidak tersedia pada daftar produk testing.');
        }

        return $testingCodes[($fallbackPosition + $position) % count($testingCodes)];
    }

    /** @return array<string, mixed> */
    private function failureResponse(SupplyChainRequestException $exception): array
    {
        return [
            'http_status' => $exception->responseStatus(),
            'body' => $exception->responsePayload(),
        ];
    }

    /**
     * @param  array<string, mixed>  $approval
     * @return array{delivery_note_number: string|null, batch_count: int, batch_quantity: int}
     */
    private function applyApprovalShipping(Trx $trx, array $approval, bool $replaceDetails = false): array
    {
        $shipping = $this->shippingHeader($trx);
        if ($shipping === null) {
            if ($replaceDetails) {
                throw new ProcessException('Data pengiriman transaksi belum tersedia.', 422);
            }

            return [
                'delivery_note_number' => null,
                'batch_count' => 0,
                'batch_quantity' => 0,
            ];
        }

        [$type, $header, $deliveryNoteColumn] = $shipping;
        $deliveryNote = $this->firstValue(
            (array) data_get($approval, 'surat_jalan.header', []),
            ['sj_no', 'surat_jalan'],
        );
        if ($replaceDetails && blank($deliveryNote)) {
            throw new ProcessException('Nomor surat jalan Supply Chain wajib dikirim.', 422);
        }
        if ($replaceDetails && mb_strlen((string) $deliveryNote) > 50) {
            throw new ProcessException('Nomor surat jalan Supply Chain maksimal 50 karakter.', 422);
        }
        if (filled($deliveryNote)) {
            $header->forceFill([$deliveryNoteColumn => (string) $deliveryNote])->save();
        }

        $productIds = $trx->details->flatMap(function ($detail, int $position): array {
            $productCode = (string) $detail->trx_detail_product_code;
            $productId = (int) $detail->trx_detail_product_id;

            return [
                $productCode => $productId,
                $this->externalProductCode($productCode, $position) => $productId,
            ];
        });

        $preparedRows = [];
        $receivedQuantities = [];
        foreach ($this->batchRows($approval) as $row) {
            $productCode = $this->firstValue($row, ['brg_code', 'kode_produk', 'product_code']);
            $batchNumber = $this->firstValue($row, ['batch_number', 'no_batch', 'batch_no', 'nomor_batch']);
            $productId = $productIds->get((string) $productCode);
            if (blank($productCode) && blank($batchNumber)) {
                continue;
            }
            if (! $productId) {
                if ($replaceDetails) {
                    throw new ProcessException("Kode produk callback {$productCode} tidak ditemukan pada transaksi.", 422);
                }

                continue;
            }
            if (blank($batchNumber)) {
                if ($replaceDetails) {
                    throw new ProcessException("Nomor batch produk {$productCode} wajib dikirim.", 422);
                }

                continue;
            }
            if ($replaceDetails && mb_strlen((string) $batchNumber) > 50) {
                throw new ProcessException("Nomor batch produk {$productCode} maksimal 50 karakter.", 422);
            }

            $expiryDate = $this->normalizeExpiryDate($this->firstValue($row, [
                'expiry_date',
                'expired_date',
                'expire_date',
                'tanggal_kedaluwarsa',
                'tgl_expired',
                'ed',
            ]));
            if ($replaceDetails && blank($expiryDate)) {
                throw new ProcessException("Tanggal kedaluwarsa produk {$productCode} wajib dikirim.", 422);
            }
            if ($replaceDetails) {
                try {
                    $expiryDate = CarbonImmutable::parse((string) $expiryDate)->toDateString();
                } catch (Throwable) {
                    throw new ProcessException("Tanggal kedaluwarsa produk {$productCode} tidak valid.", 422);
                }
            }

            $quantity = (int) ($this->firstValue($row, ['jual_qty', 'qty', 'quantity', 'jumlah']) ?: 0);
            if ($replaceDetails && $quantity < 1) {
                throw new ProcessException("Jumlah batch produk {$productCode} wajib lebih dari nol.", 422);
            }
            $receivedQuantities[$productId] = ($receivedQuantities[$productId] ?? 0) + $quantity;
            $preparedRows[] = [
                'shipping_detail_shipping_type' => $type,
                'shipping_detail_shipping_id' => $header->getKey(),
                'shipping_detail_product_id' => $productId,
                'shipping_detail_batch_number' => (string) $batchNumber,
                'shipping_detail_qty' => $quantity,
                'shipping_detail_expire_date' => $expiryDate,
            ];
        }

        if ($replaceDetails) {
            if ($preparedRows === []) {
                throw new ProcessException('Rincian batch Supply Chain wajib dikirim.', 422);
            }

            $expectedQuantities = $trx->details
                ->groupBy('trx_detail_product_id')
                ->map(fn ($details): int => (int) $details->sum('trx_detail_qty'));
            foreach ($expectedQuantities as $productId => $expectedQuantity) {
                if (($receivedQuantities[$productId] ?? 0) !== $expectedQuantity) {
                    $productCode = (string) optional(
                        $trx->details->firstWhere('trx_detail_product_id', $productId),
                    )->trx_detail_product_code;
                    throw new ProcessException(
                        "Jumlah batch produk {$productCode} tidak sesuai dengan jumlah transaksi.",
                        422,
                    );
                }
            }

            ShippingDetail::query()
                ->where('shipping_detail_shipping_type', $type)
                ->where('shipping_detail_shipping_id', $header->getKey())
                ->delete();
        }

        foreach ($preparedRows as $preparedRow) {
            ShippingDetail::query()->updateOrCreate([
                'shipping_detail_shipping_type' => $preparedRow['shipping_detail_shipping_type'],
                'shipping_detail_shipping_id' => $preparedRow['shipping_detail_shipping_id'],
                'shipping_detail_product_id' => $preparedRow['shipping_detail_product_id'],
                'shipping_detail_batch_number' => $preparedRow['shipping_detail_batch_number'],
            ], [
                'shipping_detail_qty' => $preparedRow['shipping_detail_qty'],
                'shipping_detail_expire_date' => $preparedRow['shipping_detail_expire_date'],
            ]);
        }

        return [
            'delivery_note_number' => filled($deliveryNote) ? (string) $deliveryNote : null,
            'batch_count' => count($preparedRows),
            'batch_quantity' => array_sum(array_column($preparedRows, 'shipping_detail_qty')),
        ];
    }

    /** @return null|array{string, Model, string} */
    private function shippingHeader(Trx $trx): ?array
    {
        return match ($trx->trx_shipping_method) {
            'courier_express' => $trx->shippingExpress
                ? ['courier_express', $trx->shippingExpress, 'shipping_courier_express_delivery_note_number']
                : null,
            'courier_instant' => $trx->shippingInstant
                ? ['courier_instant', $trx->shippingInstant, 'shipping_courier_instant_delivery_note_number']
                : null,
            'courier_manual' => $trx->shippingManual
                ? ['courier_manual', $trx->shippingManual, 'shipping_courier_manual_delivery_note_number']
                : null,
            'pickup' => $trx->shippingPickup
                ? ['pickup', $trx->shippingPickup, 'shipping_pickup_delivery_note_number']
                : null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $approval
     * @return list<array<string, mixed>>
     */
    private function batchRows(array $approval): array
    {
        $rows = [];
        $walk = function (array $values) use (&$walk, &$rows): void {
            if (Arr::isAssoc($values)) {
                $rows[] = $values;
            }

            foreach ($values as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk((array) data_get($approval, 'surat_jalan', []));

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function firstValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeExpiryDate(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return Str::startsWith($value, ['1900-01-01', '0000-00-00']) ? null : $value;
    }
}
