<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_supply_chain_syncs', function (Blueprint $table): void {
            $table->increments('trx_supply_chain_sync_id');
            $table->unsignedInteger('trx_supply_chain_sync_trx_id');
            $table->string('trx_supply_chain_sync_status', 20)->default('pending');
            $table->string('trx_supply_chain_sync_stage', 20)->nullable();
            $table->json('trx_supply_chain_sync_response')->nullable();
            $table->text('trx_supply_chain_sync_error')->nullable();
            $table->dateTime('trx_supply_chain_sync_synced_datetime')->nullable();
            $table->dateTime('trx_supply_chain_sync_created_datetime');
            $table->dateTime('trx_supply_chain_sync_updated_datetime');

            $table->unique('trx_supply_chain_sync_trx_id', 'trx_sc_sync_trx_unique');
            $table->index('trx_supply_chain_sync_status', 'trx_sc_sync_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_supply_chain_syncs');
    }
};
