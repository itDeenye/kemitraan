<template>
    <div class="app-bar">
        <div class="app-brand d-flex align-center gap-2 flex-1 min-w-0">
            <template v-if="isHome">
                <img
                    src="/logo-crop.png"
                    alt="DNY"
                    class="flex-shrink-0"
                    style="
                        height: 22px;
                        width: auto;
                        border-radius: 4px;
                        filter: brightness(0) invert(1);
                    "
                />
            </template>
            <template v-else>
                <button
                    class="icon-button mr-1 flex-shrink-0"
                    type="button"
                    @click="goBack"
                >
                    <v-icon icon="mdi-arrow-left" size="20" color="white" />
                </button>
            </template>
            <span
                class="text-[16px] sm:text-lg font-weight-bold text-white truncate"
                >{{ pageTitle }}</span
            >
        </div>

        <div class="app-actions d-flex align-center flex-shrink-0">
            <router-link
                to="/member/notifications"
                class="icon-button notification-button"
                style="text-decoration: none"
                aria-label="Buka notifikasi"
            >
                <v-icon icon="mdi-bell-outline" size="20" color="white" />
                <span v-if="unreadCount > 0" class="notification-count">
                    {{ unreadCount > 99 ? "99+" : unreadCount }}
                </span>
            </router-link>

            <v-menu location="bottom end" offset="8">
                <template v-slot:activator="{ props }">
                    <button
                        class="d-flex align-center gap-2 ml-2 transition-all active:scale-95"
                        style="
                            background: rgba(255, 255, 255, 0.05);
                            border-radius: 50px;
                            padding: 4px 10px 4px 4px;
                        "
                        type="button"
                        v-bind="props"
                    >
                        <v-avatar color="rgba(255,255,255,0.25)" size="32">
                            <img
                                v-if="profilePhotoUrl && !imageLoadError"
                                :src="profilePhotoUrl"
                                :alt="
                                    authStore.user?.member?.name ||
                                    authStore.user?.name ||
                                    'User'
                                "
                                style="
                                    width: 100%;
                                    height: 100%;
                                    object-fit: cover;
                                "
                                @error="imageLoadError = true"
                            />
                            <span
                                v-else
                                class="text-white text-[14px] font-weight-bold uppercase"
                                >{{
                                    authStore.user?.member?.name?.charAt(0) ||
                                    authStore.user?.name?.charAt(0) ||
                                    "U"
                                }}</span
                            >
                        </v-avatar>
                        <div
                            class="d-flex flex-column text-left justify-center"
                        >
                            <span
                                class="text-white text-[12px] font-weight-bold leading-tight mb-[2px] line-clamp-1"
                                style="max-width: 90px; padding-bottom: 2px"
                                >{{
                                    authStore.user?.member?.name ||
                                    authStore.user?.name ||
                                    "User"
                                }}</span
                            >
                            <div class="d-flex align-center">
                                <span
                                    v-if="authStore.user?.member?.level"
                                    class="text-[9px] font-weight-bold rounded-sm mr-1 d-flex align-center justify-center text-white"
                                    style="min-width: 14px; height: 14px"
                                    :class="`bg-${getBadgeData('level', authStore.user?.member?.level?.code).color}`"
                                    :title="authStore.user?.member?.level?.name"
                                >
                                    {{
                                        authStore.user?.member?.level?.name
                                            ?.charAt(0)
                                            ?.toUpperCase() || ""
                                    }}
                                </span>
                                <span
                                    class="text-white text-[10px] font-weight-medium leading-none"
                                    style="opacity: 0.7"
                                    >{{
                                        authStore.user?.member?.code ||
                                        authStore.user?.username ||
                                        "-"
                                    }}</span
                                >
                            </div>
                        </div>
                        <v-icon
                            icon="mdi-chevron-down"
                            size="18"
                            color="white"
                            class="ml-1 opacity-50"
                        />
                    </button>
                </template>
                <v-card
                    elevation="4"
                    rounded="lg"
                    class="border border-gray-100"
                    min-width="130"
                >
                    <div class="py-1 px-1.5">
                        <router-link
                            to="/member/profile"
                            class="flex items-center px-3 py-2 rounded-lg hover:bg-gray-50 transition-colors mb-0.5"
                            style="text-decoration: none"
                        >
                            <v-icon
                                icon="mdi-account-circle-outline"
                                size="20"
                                class="text-gray-500 mr-2"
                            />
                            <span
                                class="text-[13px] font-medium text-gray-700 flex-1 whitespace-nowrap"
                                >Profil</span
                            >
                            <v-icon
                                icon="mdi-chevron-right"
                                size="16"
                                class="text-gray-400 ml-1"
                            />
                        </router-link>

                        <button
                            type="button"
                            @click="handleLogout"
                            class="flex items-center px-3 py-2 rounded-lg hover:bg-red-50 transition-colors w-full text-left group"
                        >
                            <v-icon
                                icon="mdi-logout"
                                size="20"
                                class="text-red-500 mr-2 group-hover:text-red-600"
                            />
                            <span
                                class="text-[13px] font-medium text-red-600 group-hover:text-red-700 whitespace-nowrap"
                                >Keluar</span
                            >
                        </button>
                    </div>
                </v-card>
            </v-menu>
            <MobileConfirm ref="confirmDialogRef" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/shared/stores/auth";
import { useBadge } from "@/shared/composables/useBadge";
import MobileConfirm from "@/member/components/MobileConfirm.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useMemberNotificationBadge } from "@/member/composables/useMemberNotificationBadge";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { getBadgeData } = useBadge();
const snackbar = useSnackbarStore();
const confirmDialogRef = ref<InstanceType<typeof MobileConfirm> | null>(null);
const imageLoadError = ref(false);
const { unreadCount, refreshUnreadCount } = useMemberNotificationBadge();

onMounted(() => {
    refreshUnreadCount().catch((error) => {
        console.error("Failed to load notification badge:", error);
    });
});

const profilePhotoUrl = computed(() => {
    return authStore.user?.member?.image || authStore.user?.image || null;
});

watch(profilePhotoUrl, () => {
    imageLoadError.value = false;
});

async function handleLogout() {
    if (!confirmDialogRef.value) return;

    const isConfirmed = await confirmDialogRef.value.open({
        title: "Keluar Aplikasi",
        message: "Apakah Anda yakin ingin keluar dari aplikasi DNY?",
        confirmText: "Ya, Keluar",
        confirmColor: "error",
        icon: "mdi-logout",
    });

    if (isConfirmed) {
        confirmDialogRef.value.isLoading = true;
        try {
            await authStore.logout(false);
            snackbar.showMessage("Anda berhasil keluar dari sistem.");
            router.push("/member/login");
        } catch (error) {
            console.error("Logout failed:", error);
        } finally {
            confirmDialogRef.value.isLoading = false;
        }
    }
}

const mainTabs = [
    "/member",
    "/member/dashboard",
    "/member/stock",
    "/member/transactions",
    "/member/rewards",
    "/member/profile",
];

const isHome = computed(() => {
    // Hanya sembunyikan tombol back di halaman tab utama (bottom nav)
    return mainTabs.includes(route.path);
});

const goBack = () => {
    // Jika ada history sebelumnya di dalam Vue Router, kita kembali ke sana
    if (window.history.state && window.history.state.back) {
        router.back();
    } else {
        // Fallback jika tidak ada riwayat (misal user open link tab baru)
        router.push("/member/dashboard");
    }
};

const pageTitle = computed(() => {
    // Gunakan title yang sudah di-resolve secara terpusat oleh router (di index.ts)
    return route.meta?.title || "DNY SkinCare";
});
</script>

<style scoped>
.notification-button {
    position: relative;
    overflow: visible;
}

.notification-count {
    position: absolute;
    top: -4px;
    right: -6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 17px;
    height: 17px;
    padding: 0 4px;
    border: 2px solid var(--wine-dark);
    border-radius: 999px;
    background: var(--danger);
    color: #fff;
    box-shadow: 0 1px 4px rgba(36, 22, 25, 0.3);
    font-size: 9px;
    font-weight: 700;
    line-height: 1;
}
</style>
