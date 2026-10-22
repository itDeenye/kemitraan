<?php

namespace App\Services\Integration;

use App\Exceptions\ProcessException;
use App\Models\Member;
use App\Models\Trx;
use App\Models\TrxSupplyChainSync;
use App\Support\SupplyChainSyncEligibility;

class AdminSupplyChainSyncService
{
    public function __construct(private readonly SupplyChainSyncService $supplyChainSyncService) {}

    /** @return array{message: string, data: array<string, mixed>} */
    public function member(Member $member): array
    {
        $this->ensureEnabled();
        $member->refresh();
        if (filled($member->member_supply_chain_code)) {
            throw new ProcessException('Mitra sudah terintegrasi dengan Aplikasi Internal.', 409);
        }
        if (! SupplyChainSyncEligibility::member((int) $member->member_status, $member->member_supply_chain_code)) {
            throw new ProcessException('Hanya mitra aktif yang dapat disinkronkan ke Aplikasi Internal.');
        }

        $sync = $this->supplyChainSyncService->syncMember($member);

        return [
            'message' => $this->message($sync->member_supply_chain_sync_response, 'Sinkronisasi mitra berhasil.'),
            'data' => [
                'id' => (int) $member->getKey(),
                'customer_number' => $member->refresh()->member_supply_chain_code,
                'can_sync_supply_chain' => false,
            ],
        ];
    }

    /** @return array{message: string, data: array<string, mixed>} */
    public function sale(Trx $trx): array
    {
        $this->ensureEnabled();
        $trx->refresh();
        if (filled($trx->trx_supply_chain_code)) {
            throw new ProcessException('Transaksi sudah terintegrasi dengan Aplikasi Internal.', 409);
        }
        if (! SupplyChainSyncEligibility::sale($trx->trx_seller_type, $trx->trx_buyer_type, $trx->trx_type, $trx->trx_status, $trx->trx_supply_chain_code)) {
            throw new ProcessException('Hanya pesanan perusahaan dengan pembayaran disetujui yang dapat disinkronkan.');
        }

        $trx = $this->supplyChainSyncService->syncSale($trx);
        $sync = TrxSupplyChainSync::query()->where('trx_supply_chain_sync_trx_id', $trx->getKey())->first();
        if (blank($trx->trx_supply_chain_code)) {
            throw new ProcessException('Nomor penjualan Aplikasi Internal tidak tersedia.', 502);
        }

        return [
            'message' => $this->message(data_get($sync?->trx_supply_chain_sync_response, 'save'), 'Sinkronisasi transaksi berhasil.'),
            'data' => [
                'id' => (int) $trx->getKey(),
                'invoice_number' => $trx->trx_supply_chain_code,
                'supply_chain_status' => 'awaiting_callback',
                'can_sync_supply_chain' => false,
            ],
        ];
    }

    private function ensureEnabled(): void
    {
        if (! $this->supplyChainSyncService->enabled()) {
            throw new ProcessException('Layanan Aplikasi Internal belum diaktifkan.', 503);
        }
    }

    private function message(mixed $response, string $fallback): string
    {
        $message = is_array($response) ? ($response['message'] ?? null) : null;

        return is_string($message) && filled($message) ? $message : $fallback;
    }
}
