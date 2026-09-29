<template>
    <div class="screen-body">
        <div class="welcome-row">
            <div>
                <span class="soft-label">{{ currentDate }}</span>
                <h2>
                    {{ greeting }},
                    {{ authStore.user?.member?.name || "Member" }}!
                </h2>
            </div>
        </div>

        <div class="flex justify-end items-end mb-5 mt-6">
            <input
                type="month"
                v-model="selectedMonthYear"
                @change="fetchDashboard"
                style="
                    background: transparent;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 6px 12px;
                    font-size: 13px;
                    color: var(--text);
                    outline: none;
                    font-family: inherit;
                "
            />
        </div>

        <div class="card hero-balance">
            <div class="balance-top">
                <div>
                    <div class="soft-label" style="color: #f1cbd3">Profit</div>
                    <div class="money">
                        <span
                            v-if="isLoading"
                            class="skeleton-text skeleton-dark skeleton-money"
                        ></span>
                        <span v-else
                            >Rp{{
                                formatPrice(
                                    Math.max(
                                        0,
                                        dashboardData?.summary?.gross_profit ||
                                            0,
                                    ),
                                )
                            }}</span
                        >
                    </div>
                </div>
                <v-icon icon="mdi-chart-line" size="28" color="white" />
            </div>
            <div class="balance-bottom">
                <span
                    >{{ authStore.user?.member?.level?.name || "Mitra" }} ·
                    {{ authStore.user?.member?.code || "ID" }}</span
                >
            </div>
        </div>

        <div class="stat-grid stat-grid-two my-4">
            <div class="card stat-box" style="margin-top: 0">
                <span class="soft-label">Nilai Penjualan Produk</span>
                <strong
                    v-if="isLoading"
                    class="skeleton-text skeleton-stat"
                ></strong>
                <strong v-else>
                    Rp{{
                        formatPrice(
                            dashboardData?.summary?.sales?.product_value || 0,
                        )
                    }}
                </strong>
            </div>
            <div class="card stat-box" style="margin-top: 0">
                <span class="soft-label">Nilai Pembelian Produk</span>
                <strong
                    v-if="isLoading"
                    class="skeleton-text skeleton-stat"
                ></strong>
                <strong v-else>
                    Rp{{
                        formatPrice(
                            dashboardData?.summary?.purchases?.product_value ||
                                0,
                        )
                    }}
                </strong>
            </div>
        </div>

        <div class="section-heading mt-6">
            <h3>Akses cepat</h3>
            <button
                class="section-link"
                type="button"
                @click="router.push('/member/menus')"
            >
                Semua menu &rarr;
            </button>
        </div>

        <div class="quick-grid mb-8">
            <button
                v-if="isDistributorOrAgent"
                class="quick-item flow-link"
                type="button"
                @click="router.push('/member/network')"
            >
                <div class="quick-icon">
                    <v-icon icon="mdi-account-network-outline" size="20" />
                </div>
                Jaringan Mitra
            </button>
            <button
                class="quick-item flow-link"
                type="button"
                @click="router.push('/member/transactions/orders')"
            >
                <div class="quick-icon">
                    <v-icon icon="mdi-cart-outline" size="20" />
                </div>
                Pesanan Beli
            </button>
            <button
                v-if="isDistributorOrAgent"
                class="quick-item flow-link"
                type="button"
                @click="router.push('/member/transactions/sales')"
            >
                <div class="quick-icon">
                    <v-icon icon="mdi-storefront-outline" size="20" />
                </div>
                Pesanan Jual
            </button>
            <button
                class="quick-item flow-link"
                type="button"
                @click="router.push('/member/transactions/sales/create')"
            >
                <div class="quick-icon">
                    <v-icon icon="mdi-receipt-text-outline" size="20" />
                </div>
                POS
            </button>
        </div>

        <div v-if="isDistributorOrAgent" class="mb-8">
            <div class="section-heading">
                <h3>Downline terbaru</h3>
                <button
                    class="section-link"
                    type="button"
                    @click="router.push('/member/network')"
                >
                    Lihat jaringan &rarr;
                </button>
            </div>
            <div
                v-if="isLoading"
                class="card list-card"
                style="padding: 24px; text-align: center"
            >
                <v-progress-circular
                    indeterminate
                    color="primary"
                    size="24"
                ></v-progress-circular>
                <p
                    style="
                        margin: 12px 0 0;
                        color: var(--muted);
                        font-size: 13px;
                    "
                >
                    Memuat data downline...
                </p>
            </div>
            <div
                class="card list-card"
                v-else-if="dashboardData?.latest_relationships?.results?.length"
            >
                <div
                    class="list-row"
                    v-for="rel in dashboardData.latest_relationships.results"
                    :key="rel.id"
                >
                    <div
                        class="row-icon rounded-lg overflow-hidden flex-shrink-0"
                        style="
                            width: 40px;
                            height: 40px;
                            background: var(--surface-2);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        "
                    >
                        <v-img
                            v-if="rel.image"
                            :src="rel.image"
                            width="40"
                            height="40"
                            cover
                            class="rounded-lg"
                        />
                        <v-icon v-else icon="mdi-account-outline" size="20" />
                    </div>
                    <div class="row-main overflow-hidden min-w-0">
                        <strong
                            class="block mb-0.5 text-[13px] sm:text-sm text-truncate"
                            >{{ rel.name }}</strong
                        >
                        <div
                            class="text-[11px] sm:text-xs text-[var(--muted)] flex flex-wrap items-center gap-x-1 sm:gap-x-1.5"
                        >
                            <span>{{ rel.code }}</span>
                            <span class="hidden sm:inline">&middot;</span>
                            <BaseBadge
                                type="level"
                                :value="rel.level?.code"
                                chip-class="!h-auto !py-[2px] !px-1.5 sm:!px-2 !text-[9px] sm:!text-[10px]"
                                inline
                            />
                        </div>
                    </div>
                    <div class="row-side">
                        <BaseBadge type="status" :value="rel.status" inline />
                    </div>
                </div>
            </div>
            <div class="empty-state card py-8" v-else>
                <v-icon
                    icon="mdi-account-group-outline"
                    size="40"
                    color="var(--rose-2)"
                    class="mb-3"
                />
                <h3 class="text-[13px] font-weight-bold mb-1">
                    Belum ada downline
                </h3>
            </div>
        </div>

        <div v-if="!isDistributorOrAgent" class="mb-8">
            <div class="section-heading">
                <h3>Pelanggan terbaru</h3>
                <a class="section-link" href="#" @click.prevent>Lihat semua</a>
            </div>
            <div
                v-if="isLoading"
                class="card list-card"
                style="padding: 24px; text-align: center"
            >
                <v-progress-circular
                    indeterminate
                    color="primary"
                    size="24"
                ></v-progress-circular>
                <p
                    style="
                        margin: 12px 0 0;
                        color: var(--muted);
                        font-size: 13px;
                    "
                >
                    Memuat data pelanggan...
                </p>
            </div>
            <div
                class="card list-card"
                v-else-if="dashboardData?.latest_relationships?.results?.length"
            >
                <div
                    class="list-row"
                    v-for="rel in dashboardData.latest_relationships.results"
                    :key="rel.id"
                >
                    <div class="row-icon">
                        <v-icon icon="mdi-account-outline" size="20" />
                    </div>
                    <div class="row-main overflow-hidden min-w-0">
                        <strong
                            class="block mb-0.5 text-[13px] sm:text-sm text-truncate"
                            >{{ rel.name }}</strong
                        >
                        <div
                            class="text-[11px] sm:text-xs text-[var(--muted)] flex flex-wrap items-center gap-x-1 gap-y-0.5 sm:gap-x-1.5"
                        >
                            <div class="flex items-center gap-1 flex-shrink-0">
                                <span>{{
                                    rel.whatsapp || rel.phone || "-"
                                }}</span>
                            </div>
                            <template v-if="rel.address">
                                <span class="hidden sm:inline">&middot;</span>
                                <span
                                    class="text-truncate flex-1 min-w-[60px]"
                                    >{{ rel.address }}</span
                                >
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            <div class="empty-state card py-8" v-else>
                <v-icon
                    icon="mdi-account-outline"
                    size="40"
                    color="var(--rose-2)"
                    class="mb-3"
                />
                <h3 class="text-[13px] font-weight-bold mb-1">
                    Belum ada pelanggan
                </h3>
            </div>
        </div>

        <v-dialog
            v-model="showReminderModal"
            max-width="calc(100vw - 32px)"
            width="400"
            persistent
            transition="dialog-bottom-transition"
        >
            <div
                class="relative overflow-hidden rounded-[32px] bg-white shadow-2xl"
            >
                <!-- Decorative background elements -->
                <div
                    class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-gradient-to-br from-rose-100/40 to-transparent blur-2xl"
                ></div>
                <div
                    class="absolute -left-16 -top-16 h-40 w-40 rounded-full bg-gradient-to-br from-red-100/40 to-transparent blur-2xl"
                ></div>

                <div
                    class="relative px-6 pb-7 pt-8 text-center flex flex-col items-center"
                >
                    <!-- Modern Icon Container -->
                    <div
                        class="relative mb-6 flex h-[84px] w-[84px] items-center justify-center"
                    >
                        <div
                            class="absolute inset-0 animate-ping rounded-full bg-rose-100 opacity-30"
                            style="animation-duration: 3s"
                        ></div>
                        <div
                            class="absolute inset-2 rounded-[24px] bg-rose-50 rotate-6 transition-transform"
                        ></div>
                        <div
                            class="absolute inset-2 rounded-[24px] bg-red-50 -rotate-3 transition-transform"
                        ></div>
                        <div
                            class="relative flex h-[64px] w-[64px] items-center justify-center rounded-[20px] bg-gradient-to-br from-rose-500 to-red-600 shadow-lg shadow-red-200"
                        >
                            <v-icon
                                icon="mdi-shield-alert-outline"
                                size="32"
                                color="white"
                            />
                        </div>
                    </div>

                    <h2
                        class="mb-2 text-[22px] font-extrabold tracking-tight text-gray-900"
                    >
                        Amankan Akun Anda
                    </h2>
                    <p
                        class="mb-7 text-[14.5px] leading-relaxed text-gray-500 max-w-[280px]"
                    >
                        Tingkatkan keamanan dan kenyamanan bertransaksi dengan
                        melengkapi data berikut:
                    </p>

                    <div class="w-full mb-8 flex flex-col gap-3.5">
                        <button
                            v-if="
                                authStore.user &&
                                authStore.user.has_bank_account === false
                            "
                            type="button"
                            @click="goToProfileBank"
                            class="group relative flex w-full items-center gap-4 overflow-hidden rounded-[20px] border border-gray-100 bg-white p-4 text-left shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-red-100 hover:shadow-md hover:shadow-red-50"
                        >
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-red-50/50 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                            ></div>
                            <div
                                class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-[14px] bg-red-50 text-red-500 transition-colors duration-300 group-hover:bg-red-500 group-hover:text-white"
                            >
                                <v-icon icon="mdi-bank-outline" size="24" />
                            </div>
                            <div class="relative flex-1 min-w-0">
                                <strong
                                    class="mb-0.5 block truncate text-[14.5px] font-bold text-gray-900 group-hover:text-red-600 transition-colors"
                                    >Rekening Bank</strong
                                >
                                <span
                                    class="block truncate text-[13px] text-gray-500"
                                    >Belum diatur</span
                                >
                            </div>
                            <div
                                class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-50 text-gray-400 transition-all duration-300 group-hover:bg-red-50 group-hover:text-red-500"
                            >
                                <v-icon icon="mdi-arrow-right" size="18" />
                            </div>
                        </button>

                        <button
                            v-if="authStore.user?.is_default_password === true"
                            type="button"
                            @click="goToProfileSecurity"
                            class="group relative flex w-full items-center gap-4 overflow-hidden rounded-[20px] border border-gray-100 bg-white p-4 text-left shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-red-100 hover:shadow-md hover:shadow-red-50"
                        >
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-red-50/50 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                            ></div>
                            <div
                                class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-[14px] bg-red-50 text-red-500 transition-colors duration-300 group-hover:bg-red-500 group-hover:text-white"
                            >
                                <v-icon icon="mdi-lock-outline" size="24" />
                            </div>
                            <div class="relative flex-1 min-w-0">
                                <strong
                                    class="mb-0.5 block truncate text-[14.5px] font-bold text-gray-900 group-hover:text-red-600 transition-colors"
                                    >Password Default</strong
                                >
                                <span
                                    class="block truncate text-[13px] text-gray-500"
                                    >Ubah agar lebih aman</span
                                >
                            </div>
                            <div
                                class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-50 text-gray-400 transition-all duration-300 group-hover:bg-red-50 group-hover:text-red-500"
                            >
                                <v-icon icon="mdi-arrow-right" size="18" />
                            </div>
                        </button>
                    </div>

                    <button
                        type="button"
                        class="w-full rounded-xl py-3.5 text-[14px] font-bold text-gray-400 bg-gray-300 transition-colors hover:bg-gray-500"
                        @click="dismissReminder"
                    >
                        Ingatkan Saya Nanti
                    </button>
                </div>
            </div>
        </v-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/shared/stores/auth";
import dashboardService from "@/member/services/dashboard.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import BaseBadge from "@/shared/components/BaseBadge.vue";

const router = useRouter();
const authStore = useAuthStore();
const { formatPrice, formatFullDateTime } = useFormatter();
const snackbar = useSnackbarStore();

const now = ref(new Date());
let timer: ReturnType<typeof setInterval>;

// Format YYYY-MM untuk input type="month"
const selectedMonthYear = ref(
    `${now.value.getFullYear()}-${String(now.value.getMonth() + 1).padStart(2, "0")}`,
);
const dashboardData = ref<any>(null);
const isLoading = ref(false);

const showReminderModal = ref(false);

const checkProfileReminder = () => {
    if (authStore.user) {
        const hasNoBank = authStore.user.has_bank_account === false;
        const hasDefaultPassword = authStore.user.is_default_password === true;

        // Muncul jika has_bank_account false DAN is_default_password true
        if (hasNoBank && hasDefaultPassword) {
            const hasSeenReminder = sessionStorage.getItem(
                "profile_reminder_seen",
            );
            if (!hasSeenReminder) {
                showReminderModal.value = true;
            }
        }
    }
};

const dismissReminder = () => {
    sessionStorage.setItem("profile_reminder_seen", "true");
    showReminderModal.value = false;
};

const goToProfileBank = () => {
    sessionStorage.setItem("profile_reminder_seen", "true");
    showReminderModal.value = false;
    router.push("/member/profile/bank");
};

const goToProfileSecurity = () => {
    sessionStorage.setItem("profile_reminder_seen", "true");
    showReminderModal.value = false;
    router.push("/member/profile/security");
};

const fetchDashboard = async () => {
    if (!selectedMonthYear.value) return;

    const [year, month] = selectedMonthYear.value.split("-");
    isLoading.value = true;
    try {
        const response = await dashboardService.getDashboard(
            parseInt(year, 10),
            parseInt(month, 10),
        );
        if (response.success && response.data) {
            dashboardData.value = response.data;
        } else {
            snackbar.showMessage(
                response.message || "Gagal memuat dashboard",
                "error",
            );
        }
    } catch (e) {
        snackbar.showMessage("Terjadi kesalahan sistem", "error");
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    timer = setInterval(() => {
        now.value = new Date();
    }, 1000);
    fetchDashboard();
    checkProfileReminder();
});

onUnmounted(() => {
    clearInterval(timer);
});

const greeting = computed(() => {
    const h = now.value.getHours();
    if (h < 11) return "Selamat Pagi";
    if (h < 15) return "Selamat Siang";
    if (h < 18) return "Selamat Sore";
    return "Selamat Malam";
});

const currentDate = computed(() => {
    return formatFullDateTime(now.value);
});

const userInitials = computed(() => {
    const name = authStore.user?.member?.name || "Member";
    const parts = name.split(" ");
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
});

const isDistributorOrAgent = computed(() => {
    const roleId = authStore.user?.role?.id;
    return roleId === 1 || roleId === 2; // Assuming 1=Distributor, 2=Agent
});
</script>
