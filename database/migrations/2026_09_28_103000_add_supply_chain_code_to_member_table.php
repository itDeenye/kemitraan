<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member', function (Blueprint $table): void {
            $table->string('member_supply_chain_code', 30)
                ->nullable()
                ->after('member_code')
                ->comment('Nomor customer pada sistem Penjualan Supply Chain');
            $table->unique('member_supply_chain_code', 'member_sc_code_unique');
        });

        DB::table('member_supply_chain_syncs')
            ->where('member_supply_chain_sync_status', 'synced')
            ->whereNotNull('member_supply_chain_sync_customer_no')
            ->orderBy('member_supply_chain_sync_id')
            ->chunkById(500, function ($syncs): void {
                foreach ($syncs as $sync) {
                    DB::table('member')
                        ->where('member_id', $sync->member_supply_chain_sync_member_id)
                        ->update([
                            'member_supply_chain_code' => $sync->member_supply_chain_sync_customer_no,
                        ]);
                }
            }, 'member_supply_chain_sync_id');
    }

    public function down(): void
    {
        Schema::table('member', function (Blueprint $table): void {
            $table->dropUnique('member_sc_code_unique');
            $table->dropColumn('member_supply_chain_code');
        });
    }
};
