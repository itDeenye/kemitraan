<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_supply_chain_syncs')) {
            Schema::create('member_supply_chain_syncs', function (Blueprint $table): void {
                $table->increments('member_supply_chain_sync_id');
                $table->unsignedInteger('member_supply_chain_sync_member_id');
                $table->string('member_supply_chain_sync_customer_no', 30)->nullable();
                $table->string('member_supply_chain_sync_status', 20)->default('pending');
                $table->json('member_supply_chain_sync_response')->nullable();
                $table->text('member_supply_chain_sync_error')->nullable();
                $table->dateTime('member_supply_chain_sync_synced_datetime')->nullable();
                $table->dateTime('member_supply_chain_sync_created_datetime');
                $table->dateTime('member_supply_chain_sync_updated_datetime');
            });
        }

        if (! Schema::hasIndex('member_supply_chain_syncs', 'member_sc_sync_member_unique')) {
            Schema::table('member_supply_chain_syncs', function (Blueprint $table): void {
                $table->unique(
                    'member_supply_chain_sync_member_id',
                    'member_sc_sync_member_unique',
                );
            });
        }

        if (! Schema::hasIndex('member_supply_chain_syncs', 'member_sc_sync_customer_index')) {
            Schema::table('member_supply_chain_syncs', function (Blueprint $table): void {
                $table->index(
                    'member_supply_chain_sync_customer_no',
                    'member_sc_sync_customer_index',
                );
            });
        }

        if (! Schema::hasIndex('member_supply_chain_syncs', 'member_sc_sync_status_index')) {
            Schema::table('member_supply_chain_syncs', function (Blueprint $table): void {
                $table->index(
                    'member_supply_chain_sync_status',
                    'member_sc_sync_status_index',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_supply_chain_syncs');
    }
};
