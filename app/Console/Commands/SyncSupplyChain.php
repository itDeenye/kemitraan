<?php

namespace App\Console\Commands;

use App\Jobs\SyncSupplyChainMember;
use App\Jobs\SyncSupplyChainSale;
use App\Models\Member;
use App\Models\Trx;
use Illuminate\Console\Command;

class SyncSupplyChain extends Command
{
    protected $signature = 'supply-chain:sync
                            {--members : Antrekan member aktif yang belum memiliki nomor customer eksternal}
                            {--sales : Antrekan pembelian ke perusahaan yang belum tersinkron}
                            {--scheduled : Mode cron; jangan antrekan ulang data yang sudah gagal}';

    protected $description = 'Menyinkronkan member lama dan penjualan perusahaan ke Supply Chain';

    public function handle(): int
    {
        if (! config('services.supply_chain.enabled')) {
            $this->error('Integrasi Supply Chain belum diaktifkan.');

            return self::FAILURE;
        }

        $syncMembers = (bool) $this->option('members');
        $syncSales = (bool) $this->option('sales');
        $scheduled = (bool) $this->option('scheduled');
        if (! $syncMembers && ! $syncSales) {
            $syncMembers = true;
            $syncSales = true;
        }

        $memberCount = 0;
        if ($syncMembers) {
            $members = Member::query()
                ->where('member_status', 1)
                ->whereNull('member_supply_chain_code');
            if ($scheduled) {
                $members->where(function ($query): void {
                    $query->whereDoesntHave('supplyChainSync')
                        ->orWhereHas('supplyChainSync', fn ($syncQuery) => $syncQuery
                            ->where('member_supply_chain_sync_status', '!=', 'failed'));
                });
            }
            $members->chunkById(100, function ($members) use (&$memberCount): void {
                foreach ($members as $member) {
                    SyncSupplyChainMember::dispatch((int) $member->getKey());
                    $memberCount++;
                }
            }, 'member_id');
        }

        $saleCount = 0;
        if ($syncSales) {
            $sales = Trx::query()
                ->where('trx_seller_type', 'warehouse')
                ->where('trx_type', 'stock')
                ->whereIn('trx_buyer_type', ['distributor', 'agent', 'reseller'])
                ->where('trx_status', 'processing')
                ->whereNull('trx_supply_chain_code');
            if ($scheduled) {
                $sales->where(function ($query): void {
                    $query->whereDoesntHave('supplyChainSync')
                        ->orWhereHas('supplyChainSync', fn ($syncQuery) => $syncQuery
                            ->where('trx_supply_chain_sync_status', '!=', 'failed'));
                });
            }
            $sales->chunkById(100, function ($transactions) use (&$saleCount): void {
                foreach ($transactions as $trx) {
                    SyncSupplyChainSale::dispatch((int) $trx->getKey());
                    $saleCount++;
                }
            }, 'trx_id');
        }

        $this->info("Job member: {$memberCount}; job penjualan: {$saleCount}.");

        return self::SUCCESS;
    }
}
