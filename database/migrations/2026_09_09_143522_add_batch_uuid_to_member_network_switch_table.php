<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('member_network_switch', function (Blueprint $table): void {
            $table->uuid('network_switch_batch_uuid')
                ->nullable()
                ->after('network_switch_transfer_id');
            $table->index(
                ['network_switch_batch_uuid', 'network_switch_applied_datetime'],
                'member_network_switch_batch_applied_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_network_switch', function (Blueprint $table): void {
            $table->dropIndex('member_network_switch_batch_applied_index');
            $table->dropColumn('network_switch_batch_uuid');
        });
    }
};
