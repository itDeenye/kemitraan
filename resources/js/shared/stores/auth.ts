import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import type { ApiMenuItem, SidebarMenuItem } from '@/admin/types/menu';
import type { AuthUser } from '@/shared/types';

function isExternalRoute(route: string): boolean {
    return route.startsWith('http://') || 
           route.startsWith('https://') || 
           route.startsWith('//') || 
           route.startsWith('/telescope');
}

// Format API menus for sidebar display
function transformMenusForSidebar(apiMenus: ApiMenuItem[], panel: string): SidebarMenuItem[] {
    return apiMenus.map(item => {
        const sidebarItem: SidebarMenuItem = {
            id: item.id,
            title: item.title || item.name,
            icon: normalizeIcon(item.icon),
        };

        // Override specific hardcoded routes if needed
        let routePath = item.route;
        if (routePath === '/system/trail-log') {
            routePath = '/telescope';
        }

        if (routePath && routePath !== '#') {
            if (isExternalRoute(routePath)) {
                sidebarItem.href = routePath;
                sidebarItem.target = '_blank';
            } else {
                sidebarItem.to = `/${panel}${routePath}`;
            }
        }

        if (item.children && item.children.length > 0) {
            sidebarItem.children = item.children.map(child => {
                const childItem: SidebarMenuItem = {
                    id: child.id,
                    title: child.title || child.name,
                    icon: normalizeIcon(child.icon),
                };

                let childRoutePath = child.route;
                if (childRoutePath === '/system/trail-log') {
                    childRoutePath = '/telescope';
                }

                if (childRoutePath && childRoutePath !== '#') {
                    if (isExternalRoute(childRoutePath)) {
                        childItem.href = childRoutePath;
                        childItem.target = '_blank';
                    } else {
                        childItem.to = `/${panel}${childRoutePath}`;
                    }
                }
                return childItem;
            });
        }

        return sidebarItem;
    });
}

// Ensure 'mdi-' prefix
function normalizeIcon(icon: string): string {
    if (!icon) return '';
    if (icon.startsWith('mdi-')) return icon;
    return `mdi-${icon}`;
}

/**
 * Extract flat list of allowed route prefixes from raw API menus.
 * Returns paths WITH panel prefix, e.g. ['/admin/dashboard', '/admin/product/products', ...]
 */
function extractAllowedRoutes(apiMenus: ApiMenuItem[], panel: string): string[] {
    const routes: string[] = [];
    for (const item of apiMenus) {
        if (item.route && item.route !== '#') {
            routes.push(`/${panel}${item.route}`);
        }
        if (item.children && item.children.length > 0) {
            for (const child of item.children) {
                if (child.route && child.route !== '#') {
                    routes.push(`/${panel}${child.route}`);
                }
            }
        }
    }
    return routes;
}

/** Refresh buffer: refresh 5 minutes before expiry */
const REFRESH_BUFFER_MS = 5 * 60 * 1000;

/** Minimum delay to prevent timer from firing immediately for near-expired tokens */
const MIN_REFRESH_DELAY_MS = 10 * 1000;

export const useAuthStore = defineStore('auth', () => {
    const getPanel = () => window.location.pathname.startsWith('/admin') ? 'admin' : 'member';

    const getTokenKey = () => `${getPanel()}_access_token`;
    const getRefreshTokenKey = () => `${getPanel()}_refresh_token`;
    const getExpiresAtKey = () => `${getPanel()}_expires_at`;
    const getRefreshTokenExpiresAtKey = () => `${getPanel()}_refresh_token_expires_at`;
    const getUserKey = () => `${getPanel()}_user`;
    const getMenusKey = () => `${getPanel()}_menus`;

    const storedToken = localStorage.getItem(getTokenKey());
    const storedRefreshToken = localStorage.getItem(getRefreshTokenKey());
    const storedExpiresAt = localStorage.getItem(getExpiresAtKey());
    const storedRefreshTokenExpiresAt = localStorage.getItem(getRefreshTokenExpiresAtKey());
    const storedUser = localStorage.getItem(getUserKey());
    const storedMenus = localStorage.getItem(getMenusKey());

    const token = ref<string | null>(storedToken);
    const refreshTokenValue = ref<string | null>(storedRefreshToken);
    const expiresAt = ref<string | null>(storedExpiresAt);
    const refreshTokenExpiresAt = ref<string | null>(storedRefreshTokenExpiresAt);
    const user = ref<AuthUser | null>(storedUser ? JSON.parse(storedUser) : null);
    const rawMenus = ref<ApiMenuItem[]>(storedMenus ? JSON.parse(storedMenus) : []);

    const sidebarMenus = computed<SidebarMenuItem[]>(() => transformMenusForSidebar(rawMenus.value, getPanel()));
    const isAuthenticated = computed(() => !!token.value);
    const allowedRoutes = computed(() => extractAllowedRoutes(rawMenus.value, getPanel()));

    let syncInterval: ReturnType<typeof setInterval> | null = null;
    let refreshTimer: ReturnType<typeof setTimeout> | null = null;
    let isRefreshing = false;

    /**
     * Menyimpan semua token data ke state dan localStorage.
     */
    const persistTokenData = (data: {
        access_token: string;
        refresh_token: string;
        expires_at: string;
        refresh_token_expires_at: string;
    }) => {
        token.value = data.access_token;
        refreshTokenValue.value = data.refresh_token;
        expiresAt.value = data.expires_at;
        refreshTokenExpiresAt.value = data.refresh_token_expires_at;

        localStorage.setItem(getTokenKey(), data.access_token);
        localStorage.setItem(getRefreshTokenKey(), data.refresh_token);
        localStorage.setItem(getExpiresAtKey(), data.expires_at);
        localStorage.setItem(getRefreshTokenExpiresAtKey(), data.refresh_token_expires_at);
    };

    /**
     * Hapus semua token data dari state dan localStorage.
     */
    const clearTokenData = () => {
        token.value = null;
        refreshTokenValue.value = null;
        expiresAt.value = null;
        refreshTokenExpiresAt.value = null;

        localStorage.removeItem(getTokenKey());
        localStorage.removeItem(getRefreshTokenKey());
        localStorage.removeItem(getExpiresAtKey());
        localStorage.removeItem(getRefreshTokenExpiresAtKey());
    };

    const login = async (credentials: { username: string; password: string; device_name?: string }) => {
        const panel = getPanel();
        try {
            const payload = {
                ...credentials,
                device_name: credentials.device_name || 'browser'
            };

            // Use raw axios to avoid interceptor loops during login
            const response = await axios.post(`/api/v1/${panel}/auth/login`, payload, {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                withCredentials: true,
            });

            if (response.data?.success) {
                const data = response.data.data;

                persistTokenData({
                    access_token: data.access_token,
                    refresh_token: data.refresh_token,
                    expires_at: data.expires_at,
                    refresh_token_expires_at: data.refresh_token_expires_at,
                });

                user.value = data.user;
                rawMenus.value = data.menus || [];

                localStorage.setItem(getUserKey(), JSON.stringify(data.user));
                localStorage.setItem(getMenusKey(), JSON.stringify(data.menus || []));

                startTokenRefreshTimer();

                return { success: true, message: response.data.message };
            }
            return { success: false, message: response.data?.message || 'Login gagal.' };
        } catch (error: any) {
            return {
                success: false,
                message: error.response?.data?.message || 'Terjadi kesalahan jaringan.'
            };
        }
    };

    const fetchUser = async () => {
        const panel = getPanel();
        if (!token.value) return false;

        try {
            // Lazy-import api to avoid circular dependency at module init
            const api = (await import('@/shared/services/api')).default;
            const response = await api.get(`/${panel}/auth/me`);
            if (response.data?.success) {
                user.value = response.data.data;
                localStorage.setItem(getUserKey(), JSON.stringify(response.data.data));
                return true;
            }
            return false;
        } catch (error) {
            console.error('Failed to fetch user profile:', error);
            logout(false);
            return false;
        }
    };

    const updateUser = (data: Partial<AuthUser>) => {
        if (user.value) {
            user.value = { ...user.value, ...data };
        } else {
            user.value = data as AuthUser;
        }
        localStorage.setItem(getUserKey(), JSON.stringify(user.value));
    };

    const refreshMenus = async () => {
        const panel = getPanel();
        if (!token.value) return;

        try {
            const api = (await import('@/shared/services/api')).default;
            const response = await api.get(`/${panel}/menus`);
            if (response.data?.success) {
                rawMenus.value = response.data.data;
                localStorage.setItem(getMenusKey(), JSON.stringify(response.data.data));
            }
        } catch (error) {
            console.error('Failed to refresh menus:', error);
        }
    };

    /**
     * Refresh session menggunakan refresh token.
     * Returns true jika berhasil, false jika gagal.
     */
    const refreshSession = async (): Promise<boolean> => {
        const panel = getPanel();
        const currentRefreshToken = refreshTokenValue.value;

        if (!currentRefreshToken) {
            console.warn('No refresh token available.');
            return false;
        }

        // Cek apakah refresh token sudah expired
        if (refreshTokenExpiresAt.value) {
            const rtExpiry = new Date(refreshTokenExpiresAt.value).getTime();
            if (rtExpiry <= Date.now()) {
                console.warn('Refresh token has expired.');
                return false;
            }
        }

        if (isRefreshing) {
            return false;
        }

        isRefreshing = true;

        try {
            // Use raw axios — refresh endpoint is public, no auth header needed
            const response = await axios.post(`/api/v1/${panel}/auth/refresh`, {
                refresh_token: currentRefreshToken,
            }, {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                withCredentials: true,
            });

            if (response.data?.success) {
                const data = response.data.data;

                persistTokenData({
                    access_token: data.access_token,
                    refresh_token: data.refresh_token,
                    expires_at: data.expires_at,
                    refresh_token_expires_at: data.refresh_token_expires_at,
                });

                startTokenRefreshTimer();
                console.info(`[Auth] Session refreshed successfully for ${panel}.`);
                return true;
            }

            return false;
        } catch (error: any) {
            console.error('Failed to refresh session:', error?.response?.data?.message || error);
            return false;
        } finally {
            isRefreshing = false;
        }
    };

    /**
     * Start proactive token refresh timer.
     * Fires 5 minutes before access_token expires.
     */
    const startTokenRefreshTimer = () => {
        stopTokenRefreshTimer();

        if (!expiresAt.value) return;

        const expiryTime = new Date(expiresAt.value).getTime();
        const now = Date.now();
        let delay = expiryTime - now - REFRESH_BUFFER_MS;

        if (delay < MIN_REFRESH_DELAY_MS) {
            // Token is already near-expired or expired, refresh immediately (with small delay)
            delay = MIN_REFRESH_DELAY_MS;
        }

        refreshTimer = setTimeout(async () => {
            const success = await refreshSession();
            if (!success) {
                // Refresh failed — token will expire and 401 interceptor will handle it
                console.warn('[Auth] Proactive token refresh failed. Session may expire soon.');
            }
        }, delay);
    };

    const stopTokenRefreshTimer = () => {
        if (refreshTimer) {
            clearTimeout(refreshTimer);
            refreshTimer = null;
        }
    };

    /**
     * Initialize session on app mount.
     * Validates stored tokens and starts refresh timer if valid.
     */
    const initializeSession = () => {
        if (!token.value || !refreshTokenValue.value) return;

        // Cek apakah refresh token masih valid
        if (refreshTokenExpiresAt.value) {
            const rtExpiry = new Date(refreshTokenExpiresAt.value).getTime();
            if (rtExpiry <= Date.now()) {
                // Refresh token expired — force logout
                logout(true);
                return;
            }
        }

        startTokenRefreshTimer();
    };

    const startMenuSync = () => {
        if (syncInterval) return;

        void refreshMenus();
        syncInterval = setInterval(refreshMenus, 5 * 60 * 1000);
    };

    const stopMenuSync = () => {
        if (syncInterval) {
            clearInterval(syncInterval);
            syncInterval = null;
        }
    };

    /**
     * Check apakah route path diizinkan berdasarkan menu list user.
     * Beberapa route selalu diizinkan (dashboard, profile, catch-all).
     */
    const isRouteAllowed = (path: string): boolean => {
        const panel = getPanel();

        // Route yang selalu diizinkan
        const alwaysAllowed = [
            `/${panel}/dashboard`,
            `/${panel}/profile`,
            `/${panel}/login`,
        ];

        // Cek exact match atau prefix match untuk always-allowed routes
        for (const allowed of alwaysAllowed) {
            if (path === allowed || path.startsWith(allowed + '/')) {
                return true;
            }
        }

        // Jika tidak ada menu sama sekali (belum load), izinkan semua
        if (rawMenus.value.length === 0) {
            return true;
        }

        // Cek terhadap daftar route dari menu API
        for (const route of allowedRoutes.value) {
            if (path === route || path.startsWith(route + '/')) {
                return true;
            }
        }

        return false;
    };

    const logout = async (redirect = true) => {
        const panel = getPanel();

        if (token.value) {
            try {
                const api = (await import('@/shared/services/api')).default;
                await api.post(`/${panel}/auth/logout`);
            } catch (error) {
                console.error('Logout API failed:', error);
            }
        }

        stopMenuSync();
        stopTokenRefreshTimer();

        user.value = null;
        rawMenus.value = [];
        clearTokenData();
        localStorage.removeItem(getUserKey());
        localStorage.removeItem(getMenusKey());
        sessionStorage.removeItem('profile_reminder_seen');

        if (redirect) {
            window.location.href = `/${panel}/login`;
        }
    };

    return {
        user,
        rawMenus,
        sidebarMenus,
        token,
        refreshTokenValue,
        expiresAt,
        refreshTokenExpiresAt,
        isAuthenticated,
        allowedRoutes,
        getPanel,
        getTokenKey,
        getRefreshTokenKey,
        login,
        fetchUser,
        updateUser,
        refreshMenus,
        refreshSession,
        startTokenRefreshTimer,
        stopTokenRefreshTimer,
        initializeSession,
        startMenuSync,
        stopMenuSync,
        isRouteAllowed,
        logout
    };
});
