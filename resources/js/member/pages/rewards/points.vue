<template>
    <div class="screen-body">
        <div class="point-summary-grid">
            <div class="card point-summary">
                <div class="point-label">Poin Bulanan</div>
                <strong>{{ formatPrice(summary.monthly_points) }}</strong>
            </div>
            <div class="card point-summary">
                <div class="point-label">Poin Tahunan</div>
                <strong>{{ formatPrice(summary.annual_points) }}</strong>
            </div>
        </div>

        <div class="section-heading mt-6">
            <h3>Riwayat Poin</h3>
            <span>{{ summary.transaction_count }} Transaksi</span>
        </div>
        <div class="point-filters mt-3">
            <v-select v-model="selectedYear" :items="yearOptions" label="Tahun" variant="outlined" density="compact" hide-details @update:model-value="fetchHistory()" />
            <v-select v-model="selectedMonth" :items="monthOptions" label="Bulan" variant="outlined" density="compact" hide-details @update:model-value="fetchHistory()" />
        </div>
        <p class="point-caption">Poin dari pembayaran yang telah disetujui pada periode ini.</p>

        <div class="card list-card">
            <div v-if="isLoading" class="empty-state">
                <v-progress-circular indeterminate color="primary" size="24" />
                <p>Memuat riwayat poin...</p>
            </div>
            <template v-else-if="items.length">
                <router-link v-for="item in items" :key="item.id" class="list-row flow-link point-row" :to="`/member/transactions/orders/${encodeRouteId(item.transaction.id)}`">
                    <div class="row-icon"><v-icon icon="mdi-star-circle-outline" size="18" /></div>
                    <div class="row-main">
                        <strong>{{ item.transaction.code || "Transaksi Pembelian" }}</strong>
                        <span>{{ formatDateTime(item.approved_at) }}</span>
                        <div class="point-values">
                            <span>Bulanan <strong>{{ formatPrice(item.monthly_points) }}</strong></span>
                            <span>Tahunan <strong>{{ formatPrice(item.annual_points) }}</strong></span>
                        </div>
                    </div>
                    <v-icon icon="mdi-chevron-right" size="18" />
                </router-link>
            </template>
            <div v-else class="empty-state">
                <v-icon icon="mdi-history" size="32" color="grey-lighten-1" />
                <p>{{ loadFailed ? "Riwayat poin gagal dimuat." : "Belum ada poin pada periode ini." }}</p>
                <button v-if="loadFailed" type="button" class="point-retry" @click="fetchHistory()">Coba Lagi</button>
            </div>
        </div>
        <div v-if="!isLoading && currentPage < totalPages" class="mt-4 text-center">
            <v-btn variant="outlined" color="primary" :loading="isLoadMore" @click="fetchHistory(true)">Muat Lebih Banyak</v-btn>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import rewardService from "@/member/services/reward.service";
import type { PointHistoryItem } from "@/member/types/reward";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { encodeRouteId } from "@/shared/utils/route-id";

const route = useRoute();
const { formatPrice, formatDateTime, getMonthName } = useFormatter();
const snackbar = useSnackbarStore();
const today = new Date();
const requestedYear = Number(route.query.year);
const requestedMonth = Number(route.query.month);
const selectedYear = ref<number | "all">(Number.isInteger(requestedYear) && requestedYear >= 2020 && requestedYear <= 2100 ? requestedYear : today.getFullYear());
const selectedMonth = ref<number | "all">(Number.isInteger(requestedMonth) && requestedMonth >= 1 && requestedMonth <= 12 ? requestedMonth : today.getMonth() + 1);
const availableYears = ref<number[]>([]);
const items = ref<PointHistoryItem[]>([]);
const summary = ref({ monthly_points: 0, annual_points: 0, transaction_count: 0 });
const currentPage = ref(0);
const totalPages = ref(0);
const isLoading = ref(true);
const isLoadMore = ref(false);
const loadFailed = ref(false);
let requestSequence = 0;

const yearOptions = computed(() => [
    { title: "Semua Tahun", value: "all" },
    ...[...new Set([today.getFullYear(), ...availableYears.value, ...(selectedYear.value === "all" ? [] : [selectedYear.value])])]
        .sort((a, b) => b - a).map(year => ({ title: String(year), value: year })),
]);
const monthOptions = [
    { title: "Semua Bulan", value: "all" },
    ...Array.from({ length: 12 }, (_, index) => ({ title: getMonthName(index + 1), value: index + 1 })),
];

async function fetchHistory(loadMore = false) {
    if (loadMore && (isLoadMore.value || currentPage.value >= totalPages.value)) return;
    const requestId = ++requestSequence;
    const nextPage = loadMore ? currentPage.value + 1 : 1;
    isLoadMore.value = loadMore;
    if (!loadMore) {
        isLoading.value = true;
        items.value = [];
        summary.value = { monthly_points: 0, annual_points: 0, transaction_count: 0 };
        currentPage.value = 0;
        totalPages.value = 0;
    }
    loadFailed.value = false;
    try {
        const response = await rewardService.getPointHistory({
            year: selectedYear.value === "all" ? undefined : selectedYear.value,
            month: selectedMonth.value === "all" ? undefined : selectedMonth.value,
            page: nextPage,
            limit: 10,
        });
        if (requestId !== requestSequence) return;
        if (!response.success) throw new Error(response.message);
        const combined = loadMore ? [...items.value, ...response.data.results] : response.data.results;
        items.value = [...new Map(combined.map(item => [item.id, item])).values()];
        summary.value = response.summary;
        availableYears.value = response.available_years;
        currentPage.value = nextPage;
        totalPages.value = response.data.pagination.total_page;
    } catch {
        if (requestId !== requestSequence) return;
        loadFailed.value = true;
        snackbar.showMessage("Gagal memuat riwayat poin", "error");
    } finally {
        if (requestId === requestSequence) {
            isLoading.value = false;
            isLoadMore.value = false;
        }
    }
}

onMounted(() => fetchHistory());
</script>

<style scoped>
.point-summary-grid, .point-filters { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.point-summary { padding: 16px; }
.point-summary > strong { color: var(--wine); font-size: 24px; }
.point-label, .point-caption { color: var(--muted); font-size: 12px; }
.point-caption { margin: 12px 0; }
.point-row { align-items: flex-start; }
.point-row > .v-icon { margin-top: 10px; }
.row-main { min-width: 0; }
.row-main > strong { overflow-wrap: anywhere; }
.point-values { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-top: 6px; }
.point-values > span { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); font-size: 12px; }
.point-values strong { color: var(--wine); }
.point-retry { color: var(--wine); font-size: 13px; }
</style>
