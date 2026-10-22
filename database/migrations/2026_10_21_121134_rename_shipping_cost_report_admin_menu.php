<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('site_administrator_menu')
            ->where('administrator_menu_link', '/stc/shipping-cost-report')
            ->where('administrator_menu_title', 'Laporan Ongkir Non-Pembeli')
            ->update(['administrator_menu_title' => 'Laporan Biaya Pengiriman']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('site_administrator_menu')
            ->where('administrator_menu_link', '/stc/shipping-cost-report')
            ->where('administrator_menu_title', 'Laporan Biaya Pengiriman')
            ->update(['administrator_menu_title' => 'Laporan Ongkir Non-Pembeli']);
    }
};
