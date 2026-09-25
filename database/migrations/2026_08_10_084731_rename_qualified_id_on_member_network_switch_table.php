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
        Schema::table('member_network_switch', function (Blueprint $table) {
            $table->renameColumn(
                'network_switch_qualified_id',
                'network_switch_upgrade_qualified_id'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_network_switch', function (Blueprint $table) {
            $table->renameColumn(
                'network_switch_upgrade_qualified_id',
                'network_switch_qualified_id'
            );
        });
    }
};
