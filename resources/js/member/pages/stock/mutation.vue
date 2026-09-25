<template>
    <div class="screen-body">
        <div class="search-box">
            <v-icon icon="mdi-magnify" size="small" />
            <input
                type="text"
                v-model="search"
                @input="handleSearch"
                placeholder="Cari produk atau catatan mutasi..."
                style="
                    background: transparent;
                    border: none;
                    outline: none;
                    width: 100%;
                    margin-left: 8px;
                "
            />
        </div>

        <div class="filter-chips">
            <span
                class="filter-chip"
                :class="{ active: typeFilter === 'Semua' }"
                @click="typeFilter = 'Semua'"
                >Semua</span
            >
            <span
                class="filter-chip"
                :class="{ active: typeFilter === 'in' }"
                @click="typeFilter = 'in'"
                >Stok Masuk</span
            >
            <span
                class="filter-chip"
                :class="{ active: typeFilter === 'out' }"
                @click="typeFilter = 'out'"
                >Stok Keluar</span
            >
        </div>

        <div class="section-heading items-end justify-end">
            <span>Total {{ pagination.total_data || 0 }} data</span>
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
            <p style="margin: 12px 0 0; color: var(--muted); font-size: 13px">
                Memuat riwayat mutasi...
            </p>
        </div>
        <div v-else-if="mutasiList.length > 0" class="card list-card pb-4">
            <div v-for="mutasi in mutasiList" :key="mutasi.id" class="list-row">
                <div class="row-icon">
                    <v-icon
                        :icon="
                            mutasi.type === 'in'
                                ? 'mdi-package-down'
                                : 'mdi-package-up'
                        "
                        :color="
                            mutasi.type === 'in'
                                ? 'var(--green)'
                                : 'var(--wine-soft)'
                        "
                        size="24"
                    />
                </div>
                <div class="row-main">
                    <strong>{{ mutasi.product?.name }}</strong>
                    <span>{{ mutasi.note || "-" }} </span>
                    <span>{{ formatDateTime(mutasi.datetime || "-") }}</span>
                </div>
                <div
                    class="row-side"
                    :style="{
                        color:
                            mutasi.type === 'in'
                                ? 'var(--green)'
                                : 'var(--wine-soft)',
                        fontWeight: '600',
                    }"
                >
                    {{ mutasi.type === "in" ? "+" : "−"
                    }}{{ mutasi.quantity }} pcs
                </div>
            </div>

            <!-- Load More -->
            <div
                v-if="pagination.current < pagination.total_page"
                class="px-4 mt-4"
            >
                <v-btn
                    block
                    variant="outlined"
                    color="primary"
                    @click="loadMore"
                    :loading="isLoadMore"
                    class="text-none font-weight-bold"
                >
                    Muat lainnya
                </v-btn>
            </div>
        </div>
        <div v-else class="empty-state">
            <v-icon icon="mdi-swap-horizontal" size="48"></v-icon>
            <h4>Tidak ada mutasi</h4>
            <p>Belum ada riwayat mutasi stok yang ditemukan.</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from "vue";
import { useRoute } from "vue-router";
import inventoryService from "@/member/services/inventory.service";
import type {
    StockMutation,
    DataTablePagination,
} from "@/member/types/inventory";

import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime } = useFormatter();

const route = useRoute();
const search = ref((route.query.q as string) || "");
const typeFilter = ref("Semua");

const isLoading = ref(true);
const isLoadMore = ref(false);
const mutasiList = ref<StockMutation[]>([]);
const currentPage = ref(1);
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
        fetchMutations(false);
    }, 500);
};

watch([typeFilter], () => {
    currentPage.value = 1;
    fetchMutations(false);
});

const loadMore = () => {
    if (currentPage.value < pagination.value.total_page) {
        currentPage.value++;
        fetchMutations(true);
    }
};

async function fetchMutations(loadMoreData = false) {
    if (loadMoreData) {
        isLoadMore.value = true;
    } else {
        isLoading.value = true;
    }

    try {
        const params: Record<string, any> = {
            page: currentPage.value,
            limit: 10,
            search: search.value || undefined,
        };

        if (typeFilter.value !== "Semua") {
            params["filter[type]"] = typeFilter.value;
        }

        // Read from history state if navigating from stock list
        const filterProductId = history.state?.filterProductId;
        if (filterProductId) {
            params["filter[product_id]"] = filterProductId;
        }

        const response = await inventoryService.getMutations(params);
        if (loadMoreData) {
            mutasiList.value.push(...response.results);
        } else {
            mutasiList.value = response.results;
        }
        pagination.value = response.pagination;
    } catch (error) {
        console.error("Failed to load mutations:", error);
    } finally {
        isLoading.value = false;
        isLoadMore.value = false;
    }
}

onMounted(() => {
    fetchMutations();
});
</script>
