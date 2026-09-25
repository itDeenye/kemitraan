import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
// @ts-ignore
import { routes as autoRoutes } from 'vue-router/auto-routes'
import { useAuthStore } from '@/shared/stores/auth'
import { getOpaqueRouteRedirect } from '@/shared/utils/route-id'

/**
 * unplugin-vue-router menghasilkan tree structure:
 * [{ path: '/admin', children: [{ path: 'dashboard' }, { path: 'login' }, ...] }]
 *
 * Ekstrak CHILDREN dari parent '/admin',
 * lalu pisahkan login (tanpa layout) dari sisanya (dengan layout).
 */
const adminParent = autoRoutes.find((r: any) => r.path === '/admin')
const autoChildren: any[] = adminParent?.children || []

// Pisahkan login dari children karena tidak menggunakan AdminLayout
const loginChild = autoChildren.find((r: any) => r.path?.toLowerCase() === 'login')
const forgotPasswordChild = autoChildren.find((r: any) => r.path?.toLowerCase() === 'forgot-password')
const resetPasswordChild = autoChildren.find((r: any) => r.path?.toLowerCase() === 'reset-password')
const layoutChildren = autoChildren.filter((r: any) => 
    r.path?.toLowerCase() !== 'login' && 
    r.path?.toLowerCase() !== 'forgot-password' && 
    r.path?.toLowerCase() !== 'reset-password'
)

const routes: RouteRecordRaw[] = [
    {
        path: '/admin',
        component: () => import('../layouts/AdminLayout.vue'),
        children: [
            {
                path: '',
                redirect: '/admin/dashboard',
            },
            ...layoutChildren,
        ],
    },
]

// Tambahkan login sebagai rute standalone (tanpa layout)
if (loginChild) {
    routes.push({
        path: '/admin/login',
        component: loginChild.component,
        meta: { title: 'Login Admin', guestOnly: true },
    })
}

if (forgotPasswordChild) {
    routes.push({
        path: '/admin/forgot-password',
        component: forgotPasswordChild.component,
        meta: { title: 'Lupa Password Admin', guestOnly: true },
    })
}

if (resetPasswordChild) {
    routes.push({
        path: '/admin/reset-password',
        component: resetPasswordChild.component,
        meta: { title: 'Reset Password Admin', guestOnly: true },
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

    // Cari title dari meta atau menu aktif
    let menuTitle = to.meta?.title as string | undefined;
    if (!menuTitle) {
        for (const item of authStore.sidebarMenus) {
            if (item.to === to.path || (item.to !== '/admin' && item.to && to.path.startsWith(item.to + '/'))) {
                menuTitle = item.title;
                break;
            }
            if (item.children) {
                const child = item.children.find(c => c.to === to.path || (c.to !== '/admin' && c.to && to.path.startsWith(c.to + '/')));
                if (child) {
                    menuTitle = child.title;
                    break;
                }
            }
        }
    }

    const title = menuTitle || 'Admin'
    document.title = `${title} - DNY SkinCare Admin`

    const isGuestPage = to.path === '/admin/login' || to.path === '/admin/forgot-password' || to.path === '/admin/reset-password'

    // Halaman login (guest-only): jika sudah login, redirect ke dashboard
    if (isGuestPage) {
        if (authStore.token) {
            return next({ path: '/admin/dashboard' })
        }
        return next()
    }

    // Halaman terproteksi: jika belum login, redirect ke login
    if (!authStore.token) {
        sessionStorage.setItem('admin_intended_url', to.fullPath);
        return next({ path: '/admin/login' })
    }

    // Validasi user data
    if (!authStore.user) {
        const isValid = await authStore.fetchUser()
        if (!isValid) {
            sessionStorage.setItem('admin_intended_url', to.fullPath);
            return next({ path: '/admin/login' })
        }
    }

    // Role-based route guard: cek apakah route ada di menu list user
    // Catch-all route (404) selalu diizinkan agar tidak infinite redirect
    const isCatchAll = to.name === '/admin/[...all]' || to.matched.some(r => r.path.includes('[...all]') || r.path.includes(':all(.*)'));
    if (!isCatchAll && !authStore.isRouteAllowed(to.path)) {
        return next({ path: '/admin/not-found', replace: true })
    }

    next()
})

export default router
