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
        $menus = DB::table('site_administrator_menu');
        if ((clone $menus)->where('administrator_menu_link', '/transactions/payment-verification-history')->exists()) {
            return;
        }
        $verificationId = (clone $menus)->where('administrator_menu_link', '/transactions/payment-verification')
            ->value('administrator_menu_id');
        $parentId = (clone $menus)->where('administrator_menu_id', $verificationId)->value('administrator_menu_par_id');
        if ($parentId === null) {
            return;
        }
        $menuId = $menus->insertGetId([
            'administrator_menu_par_id' => $parentId,
            'administrator_menu_title' => 'Riwayat Verifikasi Pembayaran',
            'administrator_menu_description' => 'Riwayat pembayaran yang telah disetujui atau ditolak, hanya untuk dilihat.',
            'administrator_menu_link' => '/transactions/payment-verification-history',
            'administrator_menu_icon' => 'mdi-cash-clock',
            'administrator_menu_class' => '',
            'administrator_menu_order_by' => 4,
            'administrator_menu_is_active' => 1,
        ], 'administrator_menu_id');
        $verificationRoles = DB::table('site_administrator_privilege')
            ->where('administrator_privilege_administrator_menu_id', $verificationId)
            ->pluck('administrator_privilege_administrator_group_id');
        $roleIds = DB::table('site_administrator_group')
            ->whereIn('administrator_group_id', $verificationRoles)
            ->orWhereRaw('LOWER(administrator_group_title) IN (?, ?)', ['finance', 'keuangan'])
            ->pluck('administrator_group_id');
        foreach ($roleIds as $roleId) {
            DB::table('site_administrator_privilege')->insert([
                'administrator_privilege_administrator_group_id' => $roleId,
                'administrator_privilege_administrator_menu_id' => $menuId,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $menuIds = DB::table('site_administrator_menu')
            ->where('administrator_menu_link', '/transactions/payment-verification-history')
            ->pluck('administrator_menu_id');
        DB::table('site_administrator_privilege')->whereIn('administrator_privilege_administrator_menu_id', $menuIds)->delete();
        DB::table('site_administrator_menu')->whereIn('administrator_menu_id', $menuIds)->delete();
    }
};
