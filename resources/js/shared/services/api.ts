import axios from 'axios'
import type { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios'

/**
 * Base Axios instance untuk komunikasi dengan API Laravel.
 * Semua panel menggunakan instance ini.
 */
const api: AxiosInstance = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
    withXSRFToken: true,
})

// --- Refresh Token Queue Pattern ---

/** Flag: apakah sedang proses refresh token */
let isRefreshingToken = false

/** Queue: request yang menunggu token baru setelah refresh */
let failedRequestQueue: Array<{
    resolve: (token: string) => void
    reject: (error: any) => void
}> = []

/**
 * Proses semua request yang tertunda setelah refresh selesai.
 */
function processQueue(error: any, newToken: string | null = null): void {
    for (const pending of failedRequestQueue) {
        if (error) {
            pending.reject(error)
        } else {
            pending.resolve(newToken!)
        }
    }
    failedRequestQueue = []
}

// Request interceptor
api.interceptors.request.use(
    (config) => {
        // Tentukan context panel dari URL
        const isClient = typeof window !== 'undefined';
        if (isClient) {
            const panel = window.location.pathname.startsWith('/admin') ? 'admin' : 'member';
            const token = localStorage.getItem(`${panel}_access_token`);
            
            if (token) {
                config.headers.Authorization = `Bearer ${token}`;
            }
        }
        return config
    },
    (error) => Promise.reject(error)
)

// Response interceptor
api.interceptors.response.use(
    (response) => response,
    async (error: AxiosError) => {
        const status = error.response?.status
        const isClient = typeof window !== 'undefined';
        const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean };

        if (status === 401 && isClient && originalRequest) {
            // Jangan retry untuk endpoint auth (login, refresh) — hindari infinite loop
            const requestUrl = originalRequest.url || '';
            const isAuthRequest = requestUrl.includes('auth/login')
                || requestUrl.includes('auth/refresh')
                || requestUrl.includes('auth/logout');

            if (isAuthRequest || originalRequest._retry) {
                // Untuk login request, biarkan error di-handle oleh komponen
                if (requestUrl.includes('auth/login')) {
                    return Promise.reject(error);
                }

                // Refresh gagal atau sudah retry — redirect ke login
                const panel = window.location.pathname.startsWith('/admin') ? 'admin' : 'member';
                localStorage.removeItem(`${panel}_access_token`);
                localStorage.removeItem(`${panel}_refresh_token`);
                localStorage.removeItem(`${panel}_expires_at`);
                localStorage.removeItem(`${panel}_refresh_token_expires_at`);
                sessionStorage.setItem('auth_expired', 'true');
                sessionStorage.setItem(`${panel}_intended_url`, window.location.pathname + window.location.search);
                window.location.href = `/${panel}/login`;
                return Promise.reject(error);
            }

            // Cek apakah ada refresh token yang tersedia
            const panel = window.location.pathname.startsWith('/admin') ? 'admin' : 'member';
            const refreshToken = localStorage.getItem(`${panel}_refresh_token`);

            if (!refreshToken) {
                // Tidak ada refresh token — langsung redirect ke login
                localStorage.removeItem(`${panel}_access_token`);
                sessionStorage.setItem('auth_expired', 'true');
                sessionStorage.setItem(`${panel}_intended_url`, window.location.pathname + window.location.search);
                window.location.href = `/${panel}/login`;
                return Promise.reject(error);
            }

            // Jika sudah ada proses refresh berlangsung, masukkan ke queue
            if (isRefreshingToken) {
                return new Promise((resolve, reject) => {
                    failedRequestQueue.push({
                        resolve: (newToken: string) => {
                            originalRequest._retry = true;
                            originalRequest.headers.Authorization = `Bearer ${newToken}`;
                            resolve(api(originalRequest));
                        },
                        reject: (err: any) => {
                            reject(err);
                        },
                    });
                });
            }

            // Mulai proses refresh
            isRefreshingToken = true;
            originalRequest._retry = true;

            try {
                // Use raw axios to avoid interceptor loop
                const response = await axios.post(`/api/v1/${panel}/auth/refresh`, {
                    refresh_token: refreshToken,
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
                    const newAccessToken = data.access_token;

                    // Update localStorage
                    localStorage.setItem(`${panel}_access_token`, newAccessToken);
                    localStorage.setItem(`${panel}_refresh_token`, data.refresh_token);
                    localStorage.setItem(`${panel}_expires_at`, data.expires_at);
                    localStorage.setItem(`${panel}_refresh_token_expires_at`, data.refresh_token_expires_at);

                    // Update Pinia store reactively (lazy import to avoid circular dep)
                    try {
                        const { useAuthStore } = await import('@/shared/stores/auth');
                        const authStore = useAuthStore();
                        authStore.token = newAccessToken;
                        authStore.refreshTokenValue = data.refresh_token;
                        authStore.expiresAt = data.expires_at;
                        authStore.refreshTokenExpiresAt = data.refresh_token_expires_at;
                        authStore.startTokenRefreshTimer();
                    } catch (storeError) {
                        // Store update failed — localStorage is still updated, so it's okay
                        console.warn('Could not update auth store reactively:', storeError);
                    }

                    // Process queued requests dengan token baru
                    processQueue(null, newAccessToken);

                    // Retry original request
                    originalRequest.headers.Authorization = `Bearer ${newAccessToken}`;
                    return api(originalRequest);
                }

                // Refresh response was not successful
                processQueue(error);
                return Promise.reject(error);
            } catch (refreshError) {
                // Refresh failed — redirect ke login
                processQueue(refreshError);

                localStorage.removeItem(`${panel}_access_token`);
                localStorage.removeItem(`${panel}_refresh_token`);
                localStorage.removeItem(`${panel}_expires_at`);
                localStorage.removeItem(`${panel}_refresh_token_expires_at`);
                sessionStorage.setItem('auth_expired', 'true');
                sessionStorage.setItem(`${panel}_intended_url`, window.location.pathname + window.location.search);
                window.location.href = `/${panel}/login`;

                return Promise.reject(refreshError);
            } finally {
                isRefreshingToken = false;
            }
        }

        if (status === 403) {
            // Handle forbidden
            console.error('Akses ditolak (403 Forbidden)');
        }

        if (status === 419 && isClient) {
            // CSRF token expired — refresh halaman
            window.location.reload()
        }

        return Promise.reject(error)
    }
)

export default api
