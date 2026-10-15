<template>
    <div class="screen-body">
        <button
            class="primary-button block adjustment-create"
            type="button"
            @click="openForm"
        >
            <v-icon icon="mdi-plus" size="14" /> Buat Penyesuaian Stok
        </button>

        <div class="search-box mt-4">
            <v-icon icon="mdi-magnify" size="small" />
            <input
                type="text"
                v-model="search"
                @input="handleSearch"
                placeholder="Cari produk atau catatan penyesuaian..."
                style="
                    background: transparent;
                    border: none;
                    outline: none;
                    width: 100%;
                    margin-left: 8px;
                "
            />
        </div>

        <div class="section-heading items-end justify-end">
            <span>{{ pagination.total_data || 0 }} Data</span>
        </div>

        <div v-if="isLoading" class="card adjustment-state">
            <v-progress-circular indeterminate color="primary" size="24" />
            <p>Memuat riwayat penyesuaian...</p>
        </div>

        <div v-else-if="adjustments.length > 0" class="card list-card">
            <button
                v-for="adjustment in adjustments"
                :key="adjustment.id"
                type="button"
                class="list-row flow-link adjustment-row"
                @click="openDetail(adjustment.id)"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-package-variant-minus" size="16" />
                </div>
                <div class="row-main min-w-0">
                    <strong class="d-block text-truncate">
                        {{ adjustment.code }}
                    </strong>
                    <span class="d-block text-truncate">
                        {{ formatDateTime(adjustment.happened_at) }} ·
                        {{ adjustment.note || "Tanpa keterangan" }}
                    </span>
                </div>
            </button>

            <div
                v-if="pagination.current < pagination.total_page"
                class="px-4 mt-4"
            >
                <v-btn
                    block
                    variant="outlined"
                    color="primary"
                    class="text-none font-weight-bold"
                    :loading="isLoadMore"
                    @click="loadMore"
                >
                    Muat lainnya
                </v-btn>
            </div>
        </div>

        <div v-else class="card adjustment-state">
            <v-icon
                icon="mdi-package-variant"
                size="32"
                color="grey-lighten-1"
            />
            <p>Belum ada penyesuaian stok</p>
        </div>

        <AdjustmentForm v-model="showForm" @success="handleSuccess" />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter, useRoute } from "vue-router";
import inventoryService from "@/member/services/inventory.service";
import { useAuthStore } from "@/shared/stores/auth";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useFormatter } from "@/shared/composables/useFormatter";
import AdjustmentForm from "@/member/components/stock/AdjustmentForm.vue";
import { encodeRouteId } from "@/shared/utils/route-id";
import type {
    DataTablePagination,
    StockAdjustmentItem,
} from "@/member/types/inventory";

const { formatDateTime } = useFormatter();

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const snackbar = useSnackbarStore();
const allowedLevels = ["AGT", "RSL"];
const search = ref((route.query.q as string) || "");
const showForm = ref(false);
const isLoading = ref(true);
const isLoadMore = ref(false);
const currentPage = ref(1);
const adjustments = ref<StockAdjustmentItem[]>([]);
const pagination = ref<DataTablePagination>({
    total_data: 0,
    total_page: 0,
    total_display: 0,
    first_page: true,
    last_page: true,
    prev: 0,
    current: 1,
    next: 0,
    detail: [],
    start: 0,
    end: 0,
});

let searchTimeout: any = null;
const handleSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        fetchAdjustments(false);
    }, 500);
};

const isAllowed = computed(() =>
    allowedLevels.includes(authStore.user?.member?.level?.code || ""),
);

function openForm() {
    showForm.value = true;
}

function openDetail(id: number) {
    router.push(`/member/stock/adjustment/${encodeRouteId(id)}`);
}

async function handleSuccess() {
    currentPage.value = 1;
    await fetchAdjustments();
}

async function fetchAdjustments(loadMoreData = false) {
    loadMoreData ? (isLoadMore.value = true) : (isLoading.value = true);

    try {
        const params: Record<string, any> = {
            page: currentPage.value,
            limit: 10,
            search: search.value || undefined,
        };

        const response = await inventoryService.getAdjustments(params);
        adjustments.value = loadMoreData
            ? [...adjustments.value, ...response.results]
            : response.results;
        pagination.value = response.pagination;
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Gagal memuat penyesuaian stok.",
            "error",
        );
    } finally {
        isLoading.value = false;
        isLoadMore.value = false;
    }
}

function loadMore() {
    if (currentPage.value >= pagination.value.total_page) return;
    currentPage.value += 1;
    void fetchAdjustments(true);
}

onMounted(() => {
    if (!isAllowed.value) {
        snackbar.showMessage(
            "Penyesuaian stok hanya untuk Agen Utama dan Reseller.",
            "error",
        );
        void router.replace("/member/stock");
        return;
    }

    void fetchAdjustments();
});
</script>

<style scoped>
.adjustment-state {
    padding: 28px 20px;
    text-align: center;
}
.adjustment-state p {
    margin: 10px 0 0;
    color: var(--muted);
    font-size: 13px;
}
.adjustment-row {
    width: 100%;
    border: 0;
    color: inherit;
    background: transparent;
    font: inherit;
    text-align: left;
    cursor: pointer;
}
.adjustment-row:hover {
    background: #fff8f9;
}
.adjustment-create {
    margin-top: 10px;
}
</style>
