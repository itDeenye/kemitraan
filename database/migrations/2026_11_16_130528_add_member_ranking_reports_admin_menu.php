<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PREFIX = '/report/member-rankings/';

    private const PARENT_DESCRIPTION = 'Laporan peringkat mitra.';

    private const REPORTS = [
        ['Top Pembelanjaan', 'purchases', 'mdi-cart-outline', '/partnership/sales-report'],
        ['Top Rekrutan', 'recruitment', 'mdi-account-plus-outline', '/partnership/members'],
        ['Top Reward Bulanan', 'monthly-rewards', 'mdi-calendar-star', '/rewards/monthly'],
        ['Poin Tahunan', 'annual-points', 'mdi-star-circle-outline', '/rewards/annual'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $menus = DB::table('site_administrator_menu');
        if (! (clone $menus)->exists()) {
            return;
        }

        $nextId = max(167, (int) (clone $menus)->max('administrator_menu_id') + 1);
        $parentId = (clone $menus)->where('administrator_menu_par_id', 0)
            ->where('administrator_menu_title', 'Laporan')->value('administrator_menu_id');
        if ($parentId === null) {
            $parentId = $nextId++;
            $menus->insert([
                'administrator_menu_id' => $parentId, 'administrator_menu_par_id' => 0,
                'administrator_menu_title' => 'Laporan', 'administrator_menu_description' => self::PARENT_DESCRIPTION,
                'administrator_menu_link' => '#', 'administrator_menu_icon' => 'mdi-file-chart-outline',
                'administrator_menu_class' => '', 'administrator_menu_order_by' => 12, 'administrator_menu_is_active' => 1,
            ]);
        }

        $privileges = DB::table('site_administrator_privilege');
        foreach (self::REPORTS as $index => [$title, $report, $icon, $sourceLink]) {
            $link = self::PREFIX.$report;
            if ((clone $menus)->where('administrator_menu_link', $link)->exists()) {
                continue;
            }

            $menuId = $nextId++;
            $menus->insert([
                'administrator_menu_id' => $menuId, 'administrator_menu_par_id' => $parentId,
                'administrator_menu_title' => $title, 'administrator_menu_description' => '',
                'administrator_menu_link' => $link, 'administrator_menu_icon' => $icon,
                'administrator_menu_class' => '', 'administrator_menu_order_by' => $index + 1, 'administrator_menu_is_active' => 1,
            ]);
            $sourceId = (clone $menus)->where('administrator_menu_link', $sourceLink)->value('administrator_menu_id');
            if ($sourceId !== null) {
                $roles = (clone $privileges)->where('administrator_privilege_administrator_menu_id', $sourceId)
                    ->distinct()->pluck('administrator_privilege_administrator_group_id');
                foreach ($roles as $roleId) {
                    $privileges->insert([
                        'administrator_privilege_administrator_group_id' => $roleId,
                        'administrator_privilege_administrator_menu_id' => $menuId,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $menus = DB::table('site_administrator_menu');
        $links = array_map(fn (array $report): string => self::PREFIX.$report[1], self::REPORTS);
        $menuIds = (clone $menus)->whereIn('administrator_menu_link', $links)->pluck('administrator_menu_id');
        DB::table('site_administrator_privilege')->whereIn('administrator_privilege_administrator_menu_id', $menuIds)->delete();
        (clone $menus)->whereIn('administrator_menu_id', $menuIds)->delete();
        (clone $menus)->where('administrator_menu_title', 'Laporan')->where('administrator_menu_par_id', 0)
            ->where('administrator_menu_description', self::PARENT_DESCRIPTION)
            ->whereNotIn('administrator_menu_id', (clone $menus)->where('administrator_menu_par_id', '>', 0)->distinct()->pluck('administrator_menu_par_id'))
            ->delete();
    }
};
