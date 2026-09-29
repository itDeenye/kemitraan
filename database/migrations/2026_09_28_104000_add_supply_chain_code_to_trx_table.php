<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx', function (Blueprint $table): void {
            $table->string('trx_supply_chain_code', 30)
                ->nullable()
                ->after('trx_code')
                ->comment('Nomor penjualan pada sistem Penjualan Supply Chain');
            $table->unique('trx_supply_chain_code', 'trx_sc_code_unique');
        });

        DB::table('trx_supply_chain_syncs')
            ->whereNotNull('trx_supply_chain_sync_response')
            ->orderBy('trx_supply_chain_sync_id')
            ->chunkById(500, function ($syncs): void {
                foreach ($syncs as $sync) {
                    $response = json_decode((string) $sync->trx_supply_chain_sync_response, true);
                    $saleNumber = is_array($response)
                        ? data_get($response, 'save.jual_no')
                        : null;
                    if (blank($saleNumber)) {
                        continue;
                    }

                    DB::table('trx')
                        ->where('trx_id', $sync->trx_supply_chain_sync_trx_id)
                        ->update([
                            'trx_supply_chain_code' => trim((string) $saleNumber),
                        ]);
                }
            }, 'trx_supply_chain_sync_id');
    }

    public function down(): void
    {
        Schema::table('trx', function (Blueprint $table): void {
            $table->dropUnique('trx_sc_code_unique');
            $table->dropColumn('trx_supply_chain_code');
        });
    }
};
