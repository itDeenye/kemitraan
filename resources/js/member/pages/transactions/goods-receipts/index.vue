<template>
    <div class="screen-body">
        <div class="search-box">
            <v-icon icon="mdi-magnify" size="small" />
            <input
                type="text"
                v-model="searchQuery"
                placeholder="Cari kode transaksi atau penerimaan..."
                style="
                    background: transparent;
                    border: none;
                    outline: none;
                    width: 100%;
                    margin-left: 8px;
                "
            />
        </div>

        <div class="section-heading items-end justify-end mt-4">
            <span>{{ pagination.total_data }} Data</span>
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
                Memuat data...
            </p>
        </div>
        <div class="card list-card" v-else-if="receipts.length > 0">
            <div
                v-for="receipt in receipts"
                :key="receipt.transaction?.id || receipt.id"
                class="list-row flow-link"
                @click="
                    router.push(
                        `/member/transactions/goods-receipts/${encodeRouteId(receipt.transaction?.id || receipt.id)}`,
                    )
                "
            >
                <div class="row-icon">
                    <v-icon icon="mdi-package-variant-closed" size="16" />
                </div>
                <div class="row-main min-w-0">
                    <strong class="d-block text-truncate">{{
                        receipt.receive_number ||
                        receipt.transaction?.code ||
                        receipt.code
                    }}</strong>
                    <span class="d-block">
                        <template
                            v-if="
                                receipt.status?.code === 'completed' ||
                                receipt.receive_status === 'completed'
                            "
                        >
                            Diterima
                            {{
                                receipt.received_at
                                    ? formatDateTime(receipt.received_at)
                                    : "-"
                            }}
                        </template>
                        <template v-else> Belum diterima </template
                        ><template v-if="receipt.receive_number">
                            ·
                            {{
                                receipt.transaction?.code || receipt.code
                            }}</template
                        ></span
                    >
                </div>
                <div class="row-side">
                    <BaseBadge
                        type="receive_status"
                        :value="receipt.status?.code || receipt.receive_status"
                        inline
                    />
                </div>
            </div>
        </div>
        <div
            v-else
            class="card list-card"
            style="padding: 24px; text-align: center"
        >
            <p style="margin: 0; color: var(--muted); font-size: 13px">
                Tidak ada penerimaan barang yang siap diterima.
            </p>
        </div>

        <div v-if="pagination.next !== 0" class="pagination-row mt-4">
            <button
                class="pagination-btn !w-full"
                :disabled="loadingMore"
                @click="loadMore"
            >
                <v-progress-circular
                    v-if="loadingMore"
                    indeterminate
                    size="16"
                    color="var(--wine)"
                    class="mr-2"
                />
                {{ loadingMore ? "Memuat..." : "Muat lainnya" }}
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from "vue";
import { useRouter } from "vue-router";
import purchaseService from "@/member/services/purchase.service";
import type { DataTablePagination } from "@/member/types/purchase";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import { encodeRouteId } from "@/shared/utils/route-id";

const { formatDateTime } = useFormatter();

const router = useRouter();
const searchQuery = ref("");
const receipts = ref<any[]>([]);
const isLoading = ref(true);
const loadingMore = ref(false);

const pagination = ref<DataTablePagination>({
    total_data: 0,
    total_page: 0,
    total_display: 0,
    first_page: false,
    last_page: false,
    prev: 0,
    current: 1,
    next: 0,
    detail: [],
    start: 0,
    end: 0,
});

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(searchQuery, (newVal, oldVal) => {
    if (newVal === oldVal) return;
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        fetchReceipts(1);
    }, 500);
});

const fetchReceipts = async (page = 1, append = false) => {
    if (append) loadingMore.value = true;
    else isLoading.value = true;
    try {
        const response = await purchaseService.getGoodsReceipts({
            page,
            limit: 10,
            search: searchQuery.value || undefined,
        });
        if (response.success && response.data) {
            if (append) {
                receipts.value = [...receipts.value, ...response.data.results];
            } else {
                receipts.value = response.data.results;
            }
            if (response.data.pagination) {
                pagination.value = response.data.pagination;
            }
        } else {
            if (!append) receipts.value = [];
        }
    } catch (error) {
        console.error("Failed to fetch goods receipts:", error);
    } finally {
        isLoading.value = false;
        loadingMore.value = false;
    }
};

const loadMore = () => {
    if (pagination.value.next !== 0) {
        fetchReceipts(pagination.value.next, true);
    }
};

onMounted(() => {
    fetchReceipts();
});
</script>
