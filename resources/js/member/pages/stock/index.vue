<template>
    <div class="screen-body">
        <div class="quick-grid stock-actions" style="margin-bottom: 10px">
            <button
                v-if="isAgentOrReseller"
                class="quick-item flow-link"
                type="button"
                @click="router.push('/member/stock/adjustment')"
            >
                <div class="quick-icon">
                    <v-icon icon="mdi-pencil-outline" size="20" />
                </div>
                Penyesuaian
            </button>
            <button
                class="quick-item flow-link"
                type="button"
                @click="router.push('/member/stock/mutation')"
            >
                <div class="quick-icon">
                    <v-icon icon="mdi-swap-horizontal" size="20" />
                </div>
                Mutasi
            </button>
            <button
                v-if="isDistributor"
                class="quick-item flow-link"
                type="button"
                style="position: relative"
                @click="router.push('/member/stock/returns')"
            >
                <div class="quick-icon return-icon-with-badge">
                    <v-icon icon="mdi-keyboard-return" size="20" />
                    <span
                        v-if="returnActionCount > 0"
                        class="return-action-badge"
                    >
                        {{ returnActionCount > 99 ? "99+" : returnActionCount }}
                    </span>
                </div>
                Retur Barang
            </button>
        </div>

        <div class="search-box mb-4 mt-4">
            <v-icon icon="mdi-magnify" size="small" />
            <input
                type="text"
                v-model="searchQuery"
                @input="handleSearch"
                placeholder="Cari nama atau kode produk..."
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
            <span>Total {{ pagination.total_data || 0 }} produk</span>
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
                Memuat daftar stok...
            </p>
        </div>
        <div v-else-if="stocks.length > 0" class="card list-card pb-4">
            <div
                v-for="stock in stocks"
                :key="stock.id"
                class="list-row flow-link"
                @click="
                    router.push({
                        path: '/member/stock/mutation',
                        state: { filterProductId: stock.product.id },
                    })
                "
            >
                <div class="row-icon">
                    <v-icon icon="mdi-package-variant-closed" size="16" />
                </div>
                <div class="row-main" style="min-width: 0">
                    <strong>
                        {{
                            stock.product.name.length > 50
                                ? stock.product.name.substring(0, 50) + "..."
                                : stock.product.name
                        }}
                    </strong>
                    <span>{{ stock.product.code }}</span>
                </div>
                <div class="row-side">
                    <span v-if="stock.balance <= 5" class="badge orange">
                        {{ stock.balance }} {{ stock.product.unit || "pcs" }}
                    </span>
                    <span v-else class="badge gray">
                        {{ stock.balance }} {{ stock.product.unit || "pcs" }}
                    </span>
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
        <div
            v-else
            class="card list-card"
            style="padding: 24px; text-align: center"
        >
            <v-icon
                icon="mdi-package-variant"
                size="32"
                color="grey-lighten-1"
                class="mb-2"
            ></v-icon>
            <p style="margin: 0; color: var(--muted); font-size: 13px">
                Belum ada data stok
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/shared/stores/auth";
import inventoryService from "@/member/services/inventory.service";
import returnService from "@/member/services/return.service";
import type { StockItem, DataTablePagination } from "@/member/types/inventory";

const router = useRouter();
const authStore = useAuthStore();

const isLoading = ref(true);
const isLoadMore = ref(false);
const searchQuery = ref("");
const stocks = ref<StockItem[]>([]);
const currentPage = ref(1);
const returnActionCount = ref(0);
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

const isDistributor = computed(
    () => authStore.user?.member?.level?.code === "DST",
);
const isAgentOrReseller = computed(() =>
    ["AGT", "RSL"].includes(authStore.user?.member?.level?.code || ""),
);

let searchTimeout: any = null;
const handleSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        fetchStocks(false);
    }, 500);
};

const loadMore = () => {
    if (currentPage.value < pagination.value.total_page) {
        currentPage.value++;
        fetchStocks(true);
    }
};

async function fetchStocks(loadMoreData = false) {
    if (loadMoreData) {
        isLoadMore.value = true;
    } else {
        isLoading.value = true;
    }

    try {
        const response = await inventoryService.getCurrentStock({
            page: currentPage.value,
            limit: 10,
            search: searchQuery.value || undefined,
        });

        if (loadMoreData) {
            stocks.value.push(...response.results);
        } else {
            stocks.value = response.results;
        }

        pagination.value = response.pagination;
    } catch (error) {
        console.error("Failed to load stocks:", error);
    } finally {
        isLoading.value = false;
        isLoadMore.value = false;
    }
}

async function fetchReturnActionCount() {
    if (!isDistributor.value) return;

    try {
        const summary = await returnService.getActionSummary();
        returnActionCount.value =
            Number(summary.eligible || 0) + Number(summary.action_required || 0);
    } catch (error) {
        console.error("Failed to load return action count:", error);
    }
}

onMounted(() => {
    fetchStocks();
    fetchReturnActionCount();
});
</script>

<style scoped>
.return-icon-with-badge {
    position: relative;
}

.return-action-badge {
    position: absolute;
    top: -9px;
    right: -9px;
    display: inline-flex;
    min-width: 20px;
    height: 20px;
    align-items: center;
    justify-content: center;
    padding: 0 6px;
    border-radius: 999px;
    background: var(--wine);
    color: white;
    font-size: 10px;
    font-weight: 700;
}
</style>
