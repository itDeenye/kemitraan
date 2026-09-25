import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { routes as autoRoutes } from 'vue-router/auto-routes'
import { useAuthStore } from '@/shared/stores/auth'
import { getOpaqueRouteRedirect } from '@/shared/utils/route-id'

const memberParent = autoRoutes.find((r: any) => r.path === '/member')
const autoChildren: any[] = memberParent?.children || []

const loginChild = autoChildren.find((r: any) => r.path?.toLowerCase() === 'login')
const resetPasswordChild = autoChildren.find((r: any) => r.path?.toLowerCase() === 'reset-password')
const forgotPasswordChild = autoChildren.find((r: any) => r.path?.toLowerCase() === 'forgot-password')
const layoutChildren = autoChildren.filter((r: any) => 
    r.path?.toLowerCase() !== 'login' && 
    r.path?.toLowerCase() !== 'reset-password' &&
    r.path?.toLowerCase() !== 'forgot-password'
)

const routes: RouteRecordRaw[] = [
    {
        path: '/member',
        component: () => import('../layouts/MemberLayout.vue'),
        children: [
            {
                path: '',
                redirect: '/member/dashboard',
            },
            ...layoutChildren,
        ],
    },
]

if (loginChild) {
    routes.push({
        path: '/member/login',
        component: loginChild.component,
        meta: { title: 'Login Mitra', guestOnly: true },
    })
}

if (resetPasswordChild) {
    routes.push({
        path: '/member/reset-password',
        component: resetPasswordChild.component,
        meta: { title: 'Reset Password', guestOnly: true },
    })
}

if (forgotPasswordChild) {
    routes.push({
        path: '/member/forgot-password',
        component: forgotPasswordChild.component,
        meta: { title: 'Lupa Password', guestOnly: true },
    })
}

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(_to, _from, savedPosition) {
        return savedPosition || { top: 0 }
    },
})

router.beforeEach(async (to, _from, next) => {
    const opaqueRedirect = getOpaqueRouteRedirect(to)
    if (opaqueRedirect) {
        return next(opaqueRedirect)
    }

    const authStore = useAuthStore()

    let menuTitle = to.meta?.title as string | undefined;
    if (!menuTitle) {
        const staticTitles: Record<string, string> = {
            '/member/dashboard': 'Beranda',
            '/member/menus': 'Menu',
            '/member/stock': 'Stok',
            '/member/stock/mutation': 'Mutasi Stok',
            '/member/stock/adjustment': 'Penyesuaian Stok',
            '/member/stock/returns': 'Retur Barang',
            '/member/stock/returns/create': 'Ajukan Retur Barang',
            '/member/stock/returns/[id]': 'Detail Retur Barang',
            '/member/transactions': 'Transaksi',
            '/member/transactions/orders': 'Pesanan Pembelian',
            '/member/transactions/orders/[id]': 'Detail Pesanan',
            '/member/transactions/orders/create': 'Buat Pesanan',
            '/member/transactions/orders/payment': 'Pembayaran Pesanan',
            '/member/transactions/goods-receipts': 'Penerimaan Barang',
            '/member/transactions/goods-receipts/[id]': 'Detail Penerimaan',
            '/member/transactions/sales': 'Pesanan Penjualan',
            '/member/transactions/sales/create': 'POS',
            '/member/transactions/sales/payment': 'Pembayaran Pesanan',
            '/member/transactions/sales/shipping': 'Pengiriman Pesanan',
            '/member/transactions/sales/[id]': 'Detail Pesanan',
            '/member/rewards': 'Komisi',
            '/member/rewards/monthly': 'Reward Bulanan',
            '/member/rewards/monthly/[id]': 'Detail Reward',
            '/member/rewards/annual': 'Reward Tahunan',
            '/member/rewards/stockist': 'Reward Stokis',
            '/member/rewards/stockist/[id]': 'Detail Reward',
            '/member/rewards/sharing': 'Sharing Profit',
            '/member/profile': 'Profil',
            '/member/profile/edit': 'Edit Profil',
            '/member/profile/address': 'Alamat',
            '/member/profile/address-form': 'Tambah Alamat',
            '/member/profile/bank': 'Rekening Bank',
            '/member/profile/bank-form': 'Tambah Rekening Bank',
            '/member/profile/partnership': 'Status Kemitraan',
            '/member/profile/security': 'Keamanan Akun',
            '/member/network': 'Jaringan Mitra',
            '/member/network/registrations': 'Pendaftaran Mitra',
            '/member/notifications': 'Notifikasi',
        };
        
        menuTitle = staticTitles[to.path];
        
        if (!menuTitle) {
            if (to.path.startsWith('/member/stock/returns/') && to.path !== '/member/stock/returns/create') menuTitle = 'Detail Retur';
            else if (to.path.startsWith('/member/rewards/monthly/')) menuTitle = 'Detail Reward';
            else if (to.path.startsWith('/member/rewards/stockist/')) menuTitle = 'Detail Reward';
            else if (to.path.startsWith('/member/rewards/sharing/')) menuTitle = 'Detail Sharing Profit';
            else if (to.path.startsWith('/member/transactions/orders/') && !['/member/transactions/orders/create', '/member/transactions/orders/payment'].includes(to.path)) menuTitle = 'Detail Pesanan';
            else if (to.path.startsWith('/member/transactions/sales/') && !['/member/transactions/sales/create', '/member/transactions/sales/payment', '/member/transactions/sales/shipping'].includes(to.path)) menuTitle = 'Detail Pesanan';
            else if (to.path.startsWith('/member/transactions/goods-receipts/')) menuTitle = 'Detail Penerimaan';
            else if (to.path.startsWith('/member/network/registrations/')) menuTitle = 'Detail Pendaftaran';
        }

        if (!menuTitle) {
            for (const item of authStore.sidebarMenus) {
                if (item.to === to.path || (item.to !== '/member' && item.to && to.path.startsWith(item.to + '/'))) {
                    menuTitle = item.title;
                    break;
                }
                if (item.children) {
                    const child = item.children.find(c => c.to === to.path || (c.to !== '/member' && c.to && to.path.startsWith(c.to + '/')));
                    if (child) {
                        menuTitle = child.title;
                        break;
                    }
                }
            }
        }
        
        if (!menuTitle) {
            if (typeof to.name === 'string' && to.name.includes('[...all]')) {
                menuTitle = 'Halaman Tidak Ditemukan';
            } else {
                const pathParts = to.path.split('/');
                const lastPart = pathParts[pathParts.length - 1];
                if (lastPart && lastPart !== 'member') {
                    menuTitle = lastPart
                        .split('-')
                        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                        .join(' ');
                }
            }
        }
    }

    const title = menuTitle || 'Mitra'
    to.meta.title = title
    document.title = `${title} - DNY SkinCare Mitra`

    const isGuestPage = to.path === '/member/login' || to.path === '/member/reset-password' || to.path === '/member/forgot-password'

    // jika sudah login, redirect ke dashboard
    if (isGuestPage) {
        if (authStore.token) {
            return next({ path: '/member/dashboard' })
        }
        return next()
    }

    // jika belum login, redirect ke login
    if (!authStore.token) {
        sessionStorage.setItem('member_intended_url', to.fullPath);
        return next({ path: '/member/login' })
    }

    // Validasi user data
    if (!authStore.user) {
        const isValid = await authStore.fetchUser()
        if (!isValid) {
            sessionStorage.setItem('member_intended_url', to.fullPath);
            return next({ path: '/member/login' })
        }
    }

    next()
})

export default router
