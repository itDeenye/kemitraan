<?php

namespace Database\Seeders;

use App\Models\MemberGroup;
use App\Models\MemberLevel;
use App\Models\SiteAdministratorGroup;
use App\Models\SiteAdministratorMenu;
use App\Models\SiteAdministratorPrivilege;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $superuser = $this->administratorGroup(1, 'Super Administrator', 'superuser');
        $administrator = $this->administratorGroup(2, 'Administrator', 'administrator');
        $adminMenus = $this->seedAdminMenus();

        $administrator->menus()->sync($adminMenus['visible_ids']);
        $superuser->menus()->sync([]);
        SiteAdministratorPrivilege::query()
            ->whereNotIn('administrator_privilege_administrator_menu_id', $adminMenus['menu_ids'])
            ->delete();
        SiteAdministratorMenu::query()->whereNotIn('administrator_menu_id', $adminMenus['menu_ids'])->delete();

        $this->memberLevel(1, 'DST', 'Distributor', 'Level kemitraan Distributor', 5_000, 1);
        $this->memberLevel(2, 'AGT', 'Agent', 'Level kemitraan Agent', 4_000, 2);
        $this->memberLevel(3, 'RSL', 'Reseller', 'Level kemitraan Reseller', 3_000, 3);

        $this->memberGroup(1, 'Distributor', 'Akses portal distributor');
        $this->memberGroup(2, 'Agent', 'Akses portal agent');
        $this->memberGroup(3, 'Reseller', 'Akses portal reseller');
    }

    /** @return array{menu_ids: array<int, int>, visible_ids: array<int, int>} */
    private function seedAdminMenus(): array
    {
        $menus = [
            [1, 0, 'Dashboard', 'Dashboard', '/dashboard', 'mdi-view-dashboard', 1],
            [2, 0, 'Perusahaan', 'Company', '#', 'mdi-domain', 2],
            [3, 0, 'Produk', 'Products', '#', 'mdi-package-variant', 3],
            [4, 0, 'Inventory', 'Inventory', '#', 'mdi-warehouse', 4],
            [5, 0, 'Kemitraan', '', '#', 'mdi-handshake', 5],
            [6, 0, 'Pelanggan', '', '/customers', 'mdi-account-heart', 6],
            [7, 0, 'Transaksi', '', '#', 'mdi-cart', 7],
            [153, 0, 'Manajemen STC', '', '#', 'mdi-wallet', 8],
            [10, 0, 'Komisi & Reward', '', '#', 'mdi-trophy', 10],
            [12, 0, 'Sistem & Konfigurasi', '', '#', 'mdi-cog-outline', 11],
            [161, 0, 'Analitik User', '', '/user-analytic', 'mdi-poll', 13],
            [101, 2, 'Konfigurasi Alamat', '', '/company/addresses', 'mdi-map-marker-radius', 2],
            [102, 2, 'Manajemen Rekening', '', '/company/banks', 'mdi-bank', 1],
            [103, 3, 'Data Produk', 'Produk', '/product/products', 'mdi-package-variant-closed', 1],
            [105, 4, 'Stok Produk', '', '/inventory/stock', 'mdi-package-variant-closed', 1],
            [106, 4, 'Penyesuaian Stok', '', '/inventory/stock-adjusment', 'mdi-package-variant-closed-plus', 2],
            [108, 4, 'Pengiriman Barang', '', '/inventory/shipping', 'mdi-truck-fast', 3],
            [109, 5, 'Data Mitra', '', '/partnership/members', 'mdi-account-group', 1],
            [113, 7, 'Pesanan Penjualan', '', '/transactions/sales-orders', 'mdi-cart-arrow-down', 1],
            [114, 7, 'Verifikasi Pembayaran', '', '/transactions/payment-verification', 'mdi-cash-check', 4],
            [121, 10, 'Reward Tahunan', '', '/rewards/annual', 'mdi-star-circle', 2],
            [122, 10, 'Reward Bulanan', '', '/rewards/monthly', 'mdi-calendar-star', 1],
            [123, 10, 'Reward Stokis', '', '/rewards/stockist', 'mdi-store', 3],
            [128, 12, 'User Administrator', '', '/system/administrators', 'mdi-account-cog', 1],
            [129, 12, 'Menu Admin', '', '/system/menus', 'mdi-menu', 3],
            [162, 12, 'Konfigurasi Komisi', '', '/system/commission-config', 'mdi-application-cog-outline', 4],
            [163, 12, 'Konfigurasi Kemitraan', '', '/system/partnership-config', 'mdi-account-group-outline', 5],
            [164, 12, 'Trail Log', '', '/system/trail-log', 'mdi-clock-edit-outline', 6],
            [133, 3, 'Kategori Produk', '', '/product/categories', 'mdi-shape', 2],
            [134, 12, 'Role & Permission', '', '/system/roles', 'mdi-shield-account', 2],
            [135, 5, 'Approval Registrasi', '', '/partnership/registration-approvals', 'mdi-file-document-check', 4],
            [136, 5, 'Approval Upgrade', '', '/partnership/member-upgrades', 'mdi-account-arrow-up', 5],
            [137, 5, 'Downgrade Mitra', '', '/partnership/member-downgrades', 'mdi-account-arrow-down', 6],
            [138, 5, 'Data Stokis', '', '/partnership/stockists', 'mdi-store', 7],
            [139, 5, 'Stok Produk', '', '/partnership/member-stock', 'mdi-package-variant-closed', 8],
            [140, 7, 'Retur Penjualan', '', '/transactions/sales-returns', 'mdi-keyboard-return', 5],
            [143, 5, 'Mitra Distributor', '', '/partnership/member-registration', 'mdi-account-plus', 3],
            [151, 3, 'Harga Produk', '', '/product/prices', 'mdi-cash', 3],
            [152, 5, 'Laporan Penjualan Mitra', '', '/partnership/sales-report', 'mdi-file', 9],
            [154, 153, 'Saldo STC', '', '/stc/saldo', 'mdi-currency-usd', 1],
            [155, 153, 'Top Up STC', '', '/stc/topup', 'mdi-wallet-plus', 2],
            [157, 10, 'Sharing Profit', '', '/rewards/profit-sharing', 'mdi-cash', 4],
            [158, 10, 'Riwayat Sharing Profit', '', '/rewards/profit-sharing-history', 'mdi-share-all-outline', 5],
            [159, 10, 'Laporan Pencapaian Poin', '', '/rewards/point-achievement-report', 'mdi-file-certificate-outline', 6],
            [160, 7, 'Screening Stok', '', '/transactions/stock-screening', 'mdi-clipboard-text-search-outline', 2],
        ];
        $menuIds = array_column($menus, 0);
        $parentIds = array_values(array_unique(array_filter(array_column($menus, 1))));

        foreach ($menus as [$id, $parentId, $title, $description, $route, $icon, $sortOrder]) {
            $this->adminMenu($id, $parentId, $title, $description, $route, $icon, $sortOrder);
        }

        return [
            'menu_ids' => $menuIds,
            'visible_ids' => array_values(array_diff($menuIds, $parentIds)),
        ];
    }

    private function adminMenu(
        int $id,
        int $parentId,
        string $title,
        string $description,
        string $route,
        string $icon,
        int $sortOrder,
    ): void {
        $menu = SiteAdministratorMenu::query()->find($id) ?? new SiteAdministratorMenu;
        $menu->administrator_menu_id = $id;
        $menu->fill([
            'administrator_menu_par_id' => $parentId,
            'administrator_menu_title' => $title,
            'administrator_menu_description' => $description,
            'administrator_menu_link' => $route,
            'administrator_menu_icon' => $icon,
            'administrator_menu_class' => '',
            'administrator_menu_order_by' => $sortOrder,
            'administrator_menu_is_active' => 1,
        ])->save();
    }

    private function memberGroup(int $id, string $name, string $description): MemberGroup
    {
        $group = MemberGroup::query()->find($id) ?? new MemberGroup;
        $group->member_group_id = $id;
        $group->fill([
            'member_group_name' => $name,
            'member_group_description' => $description,
            'member_group_is_active' => 1,
        ])->save();

        return $group;
    }

    private function memberLevel(
        int $id,
        string $code,
        string $name,
        string $description,
        int $pointValue,
        int $sortOrder,
    ): MemberLevel {
        $level = MemberLevel::query()->find($id) ?? new MemberLevel;
        $level->member_level_id = $id;
        $level->fill([
            'member_level_code' => $code,
            'member_level_name' => $name,
            'member_level_description' => $description,
            'member_level_min_order' => 0,
            'member_level_point_value' => $pointValue,
            'member_level_sort_order' => $sortOrder,
            'member_level_is_active' => 1,
        ])->save();

        return $level;
    }

    private function administratorGroup(int $id, string $title, string $type): SiteAdministratorGroup
    {
        $group = SiteAdministratorGroup::query()->find($id) ?? new SiteAdministratorGroup;
        $group->administrator_group_id = $id;
        $group->fill([
            'administrator_group_title' => $title,
            'administrator_group_type' => $type,
            'administrator_group_is_active' => 1,
        ])->save();

        return $group;
    }
}
