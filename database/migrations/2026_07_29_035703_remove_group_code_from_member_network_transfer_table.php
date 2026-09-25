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
        Schema::table('member_network_transfer', function (Blueprint $table) {
            $table->dropUnique('uq_member_network_transfer_group_member');
            $table->dropColumn('member_network_transfer_group_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_network_transfer', function (Blueprint $table) {
            $table->string('member_network_transfer_group_code', 50)
                ->nullable()
                ->after('member_network_transfer_id')
                ->comment('Kode pengelompokan perpindahan yang diterapkan bersamaan');
            $table->unique(
                ['member_network_transfer_group_code', 'member_network_transfer_member_id'],
                'uq_member_network_transfer_group_member'
            );
        });
    }
};
