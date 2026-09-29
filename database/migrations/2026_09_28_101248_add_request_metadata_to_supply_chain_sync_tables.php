<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_supply_chain_syncs', function (Blueprint $table): void {
            $table->string('member_supply_chain_sync_url', 500)
                ->nullable()
                ->after('member_supply_chain_sync_status');
            $table->json('member_supply_chain_sync_param')
                ->nullable()
                ->after('member_supply_chain_sync_url');
        });

        Schema::table('trx_supply_chain_syncs', function (Blueprint $table): void {
            $table->json('trx_supply_chain_sync_url')
                ->nullable()
                ->after('trx_supply_chain_sync_stage');
            $table->json('trx_supply_chain_sync_param')
                ->nullable()
                ->after('trx_supply_chain_sync_url');
        });
    }

    public function down(): void
    {
        Schema::table('member_supply_chain_syncs', function (Blueprint $table): void {
            $table->dropColumn([
                'member_supply_chain_sync_url',
                'member_supply_chain_sync_param',
            ]);
        });

        Schema::table('trx_supply_chain_syncs', function (Blueprint $table): void {
            $table->dropColumn([
                'trx_supply_chain_sync_url',
                'trx_supply_chain_sync_param',
            ]);
        });
    }
};
