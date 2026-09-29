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
        Schema::table('member_history', function (Blueprint $table) {
            $table->unsignedInteger('member_history_upgrade_qualified_id')
                ->default(0)
                ->after('member_history_member_id')
                ->comment('ID approval upgrade yang menghasilkan history');
            $table->unsignedInteger('member_history_network_transfer_id')
                ->default(0)
                ->after('member_history_upgrade_qualified_id')
                ->comment('ID perpindahan jaringan yang menghasilkan history');

            $table->index('member_history_upgrade_qualified_id', 'idx_member_history_upgrade_qualified');
            $table->index('member_history_network_transfer_id', 'idx_member_history_network_transfer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_history', function (Blueprint $table) {
            $table->dropIndex('idx_member_history_upgrade_qualified');
            $table->dropIndex('idx_member_history_network_transfer');
            $table->dropColumn([
                'member_history_upgrade_qualified_id',
                'member_history_network_transfer_id',
            ]);
        });
    }
};
