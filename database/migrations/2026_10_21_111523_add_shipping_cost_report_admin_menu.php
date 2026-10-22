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
        $parentId = DB::table('site_administrator_menu')->where('administrator_menu_title', 'Manajemen STC')->value('administrator_menu_id');
        if ($parentId === null || DB::table('site_administrator_menu')->where('administrator_menu_link', '/stc/shipping-cost-report')->exists()) {
            return;
        }
        $menuId = DB::table('site_administrator_menu')->insertGetId([
            'administrator_menu_par_id' => $parentId, 'administrator_menu_title' => 'Laporan Ongkir Non-Pembeli',
            'administrator_menu_description' => 'Biaya pengiriman yang tidak ditagihkan kepada pembeli.',
            'administrator_menu_link' => '/stc/shipping-cost-report', 'administrator_menu_icon' => 'mdi-truck-check-outline',
            'administrator_menu_class' => '', 'administrator_menu_order_by' => 3, 'administrator_menu_is_active' => 1,
        ], 'administrator_menu_id');
        $balanceId = DB::table('site_administrator_menu')->where('administrator_menu_link', '/stc/saldo')->value('administrator_menu_id');
        $roles = DB::table('site_administrator_privilege')->where('administrator_privilege_administrator_menu_id', $balanceId)
            ->distinct()->pluck('administrator_privilege_administrator_group_id');
        foreach ($roles as $roleId) {
            DB::table('site_administrator_privilege')->insert([
                'administrator_privilege_administrator_group_id' => $roleId, 'administrator_privilege_administrator_menu_id' => $menuId,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $ids = DB::table('site_administrator_menu')->where('administrator_menu_link', '/stc/shipping-cost-report')
            ->where('administrator_menu_description', 'Biaya pengiriman yang tidak ditagihkan kepada pembeli.')->pluck('administrator_menu_id');
        DB::table('site_administrator_privilege')->whereIn('administrator_privilege_administrator_menu_id', $ids)->delete();
        DB::table('site_administrator_menu')->whereIn('administrator_menu_id', $ids)->delete();
    }
};
