<template>
    <div class="screen-body" style="padding-bottom: 80px">
        <div class="card summary-card mb-4">
            <div class="summary-line">
                <div class="d-flex items-center gap-2">
                    <v-icon size="24">mdi-information-outline</v-icon>
                    <strong>Informasi Retur</strong>
                </div>
            </div>
            <div class="summary-line ml-5">
                <span>• Berdasarkan kode penerimaan barang.</span>
            </div>
            <div class="summary-line ml-5">
                <span>• Maksimal 3 hari sejak barang diterima.</span>
            </div>
            <div class="summary-line ml-5">
                <span>• Alasan dan foto bukti wajib disertakan.</span>
            </div>
            <div class="summary-line ml-5">
                <span>• Stok ditahan sejak pengajuan retur dikirim.</span>
            </div>
        </div>

        <div class="return-tabs mt-4">
            <button
                class="return-tab"
                :class="{ active: activeTab === 'eligible' }"
                @click="switchTab('eligible')"
            >
                <span>Ajukan Retur</span>
                <span class="tab-count">{{ actionSummary.eligible }}</span>
            </button>
            <button
                class="return-tab"
                :class="{ active: activeTab === 'action' }"
                @click="switchTab('action')"
            >
                <span>Perlu Aksi</span>
                <span class="tab-count action">{{ actionSummary.action_required }}</span>
            </button>
            <button
                class="return-tab"
                :class="{ active: activeTab === 'history' }"
                @click="switchTab('history')"
            >
                <span>Riwayat</span>
                <span class="tab-count muted">{{ actionSummary.history }}</span>
            </button>
        </div>

        <div v-show="activeTab === 'eligible'" class="tab-content mt-4">
            <div class="section-heading items-end justify-end mt-4">
                <span>{{ paginationEligible.total_data }} Penerimaan</span>
            </div>

            <div
                v-if="isLoadingEligible"
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
                    Memuat data penerimaan...
                </p>
            </div>

            <div
                v-else-if="eligibleReceipts.length > 0"
                class="flex flex-col gap-3"
            >
                <div
                    v-for="receipt in eligibleReceipts"
                    :key="receipt.receive_id"
                    class="card overflow-hidden flow-link"
                    @click="goToReturn(receipt)"
                >
                    <div style="padding: 16px 20px">
                        <div
                            class="flex items-center"
                            style="margin-bottom: 16px"
                        >
                            <span
                                class="row-icon w-[32px] h-[32px] shrink-0 mr-3"
                            >
                                <v-icon
                                    icon="mdi-package-variant-closed"
                                    size="20"
                                />
                            </span>
                            <div class="flex-1 min-w-0">
                                <strong class="block truncate">{{
                                    receipt.receive_number
                                }}</strong>
                                <span
                                    class="block text-xs font-normal text-[var(--muted)] truncate"
                                    >{{ receipt.transaction.code }}</span
                                >
                            </div>
                            <div
                                class="flex flex-col items-end shrink-0 gap-1 ml-auto"
                            >
                                <span class="text-xs text-[var(--muted)]">{{
                                    formatDate(receipt.received_at)
                                }}</span>
                                <v-btn
                                    size="x-small"
                                    color="primary"
                                    variant="tonal"
                                    class="rounded-lg text-none font-weight-bold"
                                    :loading="
                                        openingReceiptId === receipt.receive_id
                                    "
                                    @click.stop="goToReturn(receipt)"
                                >
                                    Ajukan Retur
                                </v-btn>
                            </div>
                        </div>

                        <div
                            style="
                                border-top: 1px dashed var(--border);
                                padding-top: 16px;
                            "
                            class="flex flex-col gap-1.5 text-xs"
                        >
                            <div class="flex items-center gap-1">
                                <span class="text-[var(--muted)]"
                                    >Batas Retur:</span
                                >
                                <span class="font-bold text-error">{{
                                    formatDate(receipt.return_deadline)
                                }}</span>
                            </div>
                            <div
                                v-if="receipt.items && receipt.items.length > 0"
                                class="flex items-start gap-1"
                            >
                                <span class="text-[var(--muted)] shrink-0"
                                    >Produk:</span
                                >
                                <strong
                                    class="text-[var(--ink)] line-clamp-2"
                                    >{{
                                        receipt.items
                                            .map((i) => i.product_name)
                                            .join(", ")
                                    }}</strong
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="card list-card"
                style="padding: 24px; text-align: center"
            >
                <v-icon
                    icon="mdi-check-circle-outline"
                    size="32"
                    color="success"
                    class="mb-2"
                ></v-icon>
                <p style="margin: 0; color: var(--muted); font-size: 13px">
                    Tidak ada penerimaan barang yang dapat diretur saat ini.
                </p>
            </div>

            <div
                v-if="paginationEligible.next !== 0 && !isLoadingEligible"
                class="pagination-row mt-4"
            >
                <button
                    class="pagination-btn !w-full"
                    :disabled="loadingMoreEligible"
                    @click="loadMoreEligible"
                >
                    <v-progress-circular
                        v-if="loadingMoreEligible"
                        indeterminate
                        size="16"
                        color="var(--wine)"
                        class="mr-2"
                    />
                    {{ loadingMoreEligible ? "Memuat..." : "Muat lainnya" }}
                </button>
            </div>
        </div>

        <div v-show="activeTab === 'action'" class="tab-content mt-4">
            <div class="section-heading items-end justify-end mt-4">
                <span>{{ paginationAction.total_data }} Perlu Aksi</span>
            </div>

            <div
                v-if="isLoadingAction"
                class="card list-card"
                style="padding: 24px; text-align: center"
            >
                <v-progress-circular indeterminate color="primary" size="24" />
                <p style="margin: 12px 0 0; color: var(--muted); font-size: 13px">
                    Memuat aksi retur...
                </p>
            </div>

            <div v-else-if="actionReturns.length > 0" class="flex flex-col gap-3">
                <div
                    v-for="ret in actionReturns"
                    :key="ret.id"
                    class="card overflow-hidden flow-link"
                    @click="openReturnDetail(ret)"
                >
                    <div style="padding: 16px 20px">
                        <div class="flex items-center gap-3">
                            <span class="row-icon w-[32px] h-[32px] shrink-0">
                                <v-icon icon="mdi-keyboard-return" size="20" />
                            </span>
                            <div class="flex-1 min-w-0">
                                <strong class="block truncate">{{ ret.code }}</strong>
                                <span class="block text-xs text-[var(--muted)] truncate">
                                    {{ ret.transaction?.code || "-" }}
                                </span>
                            </div>
                            <BaseBadge
                                type="return_status"
                                :value="ret.status?.code"
                                inline
                                chip-class="font-semibold text-[10px]"
                            />
                        </div>
                        <div class="action-callout">
                            <div>
                                <span>Aksi yang perlu dilakukan</span>
                                <strong>{{ returnActionLabel(ret) }}</strong>
                            </div>
                            <v-icon icon="mdi-chevron-right" size="20" />
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="card list-card" style="padding: 24px; text-align: center">
                <v-icon icon="mdi-check-circle-outline" size="32" color="success" class="mb-2" />
                <p style="margin: 0; color: var(--muted); font-size: 13px">
                    Tidak ada retur yang perlu ditindaklanjuti saat ini.
                </p>
            </div>

            <div
                v-if="paginationAction.next !== 0 && !isLoadingAction"
                class="pagination-row mt-4"
            >
                <button
                    class="pagination-btn !w-full"
                    :disabled="loadingMoreAction"
                    @click="loadMoreAction"
                >
                    <v-progress-circular
                        v-if="loadingMoreAction"
                        indeterminate
                        size="16"
                        color="var(--wine)"
                        class="mr-2"
                    />
                    {{ loadingMoreAction ? "Memuat..." : "Muat lainnya" }}
                </button>
            </div>
        </div>

        <div v-show="activeTab === 'history'" class="tab-content mt-4">
            <div class="section-heading items-end justify-end mt-4">
                <span>{{ paginationHistory.total_data }} Data</span>
            </div>

            <div
                v-if="isLoadingHistory"
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
                    Memuat riwayat retur...
                </p>
            </div>

            <div
                v-else-if="returnHistory.length > 0"
                class="flex flex-col gap-3"
            >
                <div
                    v-for="ret in returnHistory"
                    :key="ret.id"
                    class="card overflow-hidden"
                >
                    <div
                        style="padding: 16px 20px"
                        class="flow-link"
                        @click="
                            router.push(
                                `/member/stock/returns/${encodeRouteId(ret.id)}`,
                            )
                        "
                    >
                        <div
                            class="flex items-center"
                            style="margin-bottom: 16px"
                        >
                            <span
                                class="row-icon w-[32px] h-[32px] shrink-0 mr-3"
                            >
                                <v-icon icon="mdi-keyboard-return" size="20" />
                            </span>
                            <div class="flex-1 min-w-0">
                                <strong class="block truncate">{{
                                    ret.code
                                }}</strong>
                                <span
                                    class="block text-xs font-normal text-[var(--muted)] truncate"
                                >
                                    {{ ret.transaction?.code || "-" }}</span
                                >
                            </div>
                            <div
                                class="flex flex-col items-end shrink-0 gap-1 ml-auto"
                            >
                                <span class="text-xs text-[var(--muted)]">{{
                                    formatDate(ret.created_at)
                                }}</span>
                                <BaseBadge
                                    type="return_status"
                                    :value="ret.status?.code"
                                    inline
                                    chip-class="font-semibold text-[10px]"
                                />
                            </div>
                        </div>

                        <div
                            style="
                                border-top: 1px dashed var(--border);
                                padding-top: 16px;
                            "
                            class="flex flex-col gap-1.5 text-xs"
                        >
                            <div class="flex items-center gap-1">
                                <span class="text-[var(--muted)]"
                                    >Total Retur:</span
                                >
                                <span class="font-bold text-[var(--ink)]"
                                    >{{
                                        ret.summary?.total_quantity || 0
                                    }}
                                    pcs</span
                                >
                            </div>
                            <div
                                v-if="ret.items && ret.items.length > 0"
                                class="flex items-start gap-1"
                            >
                                <span class="text-[var(--muted)] shrink-0"
                                    >Produk:</span
                                >
                                <strong
                                    class="text-[var(--ink)] line-clamp-2"
                                    >{{
                                        ret.items
                                            .map(
                                                (i) =>
                                                    i.product_name ||
                                                    i.product?.name,
                                            )
                                            .filter(Boolean)
                                            .join(", ") || "-"
                                    }}</strong
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="card list-card"
                style="padding: 24px; text-align: center"
            >
                <v-icon
                    icon="mdi-history"
                    size="32"
                    color="grey-lighten-1"
                    class="mb-2"
                ></v-icon>
                <p style="margin: 0; color: var(--muted); font-size: 13px">
                    Belum ada riwayat retur.
                </p>
            </div>

            <div
                v-if="paginationHistory.next !== 0 && !isLoadingHistory"
                class="pagination-row mt-4"
            >
                <button
                    class="pagination-btn !w-full"
                    :disabled="loadingMoreHistory"
                    @click="loadMoreHistory"
                >
                    <v-progress-circular
                        v-if="loadingMoreHistory"
                        indeterminate
                        size="16"
                        color="var(--wine)"
                        class="mr-2"
                    />
                    {{ loadingMoreHistory ? "Memuat..." : "Muat lainnya" }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import returnService from "@/member/services/return.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { encodeRouteId } from "@/shared/utils/route-id";
import type { ReturnActionSummary, ReturnItem } from "@/member/types/return";
import type {
    DataTablePagination,
    EligibleReceipt,
} from "@/member/types/inventory";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const router = useRouter();
const route = useRoute();
const snackbarStore = useSnackbarStore();
type ReturnTab = "eligible" | "action" | "history";

const activeTab = ref<ReturnTab>("eligible");
const { formatDate } = useFormatter();
const isLoadingEligible = ref(true);
const isLoadingAction = ref(false);
const isLoadingHistory = ref(false);
const loadingMoreEligible = ref(false);
const loadingMoreAction = ref(false);
const loadingMoreHistory = ref(false);
const openingReceiptId = ref<number | null>(null);
const eligibleReceipts = ref<EligibleReceipt[]>([]);
const actionReturns = ref<ReturnItem[]>([]);
const returnHistory = ref<ReturnItem[]>([]);
const actionSummary = ref<ReturnActionSummary>({
    eligible: 0,
    action_required: 0,
    history: 0,
    total_actions: 0,
});

const paginationEligible = ref<DataTablePagination>({
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

const paginationHistory = ref<DataTablePagination>({
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

const paginationAction = ref<DataTablePagination>({
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

const switchTab = (tab: ReturnTab) => {
    activeTab.value = tab;
    if (tab === "eligible" && eligibleReceipts.value.length === 0) {
        fetchEligible();
    }
    if (tab === "action" && actionReturns.value.length === 0) {
        fetchActions();
    }
    if (tab === "history" && returnHistory.value.length === 0) {
        fetchHistory();
    }
};

const fetchEligible = async (page: number = 1) => {
    isLoadingEligible.value = true;
    try {
        const res = await returnService.getEligibleReceipts({ limit: 10, page });
        eligibleReceipts.value = res.results;
        paginationEligible.value = res.pagination;
    } catch (e) {
        snackbarStore.showMessage("Gagal memuat penerimaan barang", "error");
    } finally {
        isLoadingEligible.value = false;
    }
};

const fetchSummary = async () => {
    try {
        actionSummary.value = await returnService.getActionSummary();
    } catch (e) {
        console.error("Failed to load return action summary:", e);
    }
};

const fetchActions = async (page: number = 1) => {
    isLoadingAction.value = true;
    try {
        const res = await returnService.getReturns({
            limit: 10,
            page,
            filter: { action_required: true },
        });
        actionReturns.value = res.results;
        paginationAction.value = res.pagination;
    } catch (e) {
        snackbarStore.showMessage("Gagal memuat aksi retur", "error");
    } finally {
        isLoadingAction.value = false;
    }
};

const fetchHistory = async (page: number = 1) => {
    isLoadingHistory.value = true;
    try {
        const res = await returnService.getReturns({ limit: 10, page });
        returnHistory.value = res.results;
        paginationHistory.value = res.pagination;
    } catch (e) {
        snackbarStore.showMessage("Gagal memuat riwayat retur", "error");
    } finally {
        isLoadingHistory.value = false;
    }
};

const loadMoreEligible = async () => {
    if (loadingMoreEligible.value || paginationEligible.value.next === 0)
        return;
    loadingMoreEligible.value = true;
    try {
        const res = await returnService.getEligibleReceipts({
            limit: 10,
            page: paginationEligible.value.next,
        });
        eligibleReceipts.value = [...eligibleReceipts.value, ...res.results];
        paginationEligible.value = res.pagination;
    } catch (e) {
        snackbarStore.showMessage("Gagal memuat penerimaan barang", "error");
    } finally {
        loadingMoreEligible.value = false;
    }
};

const loadMoreHistory = async () => {
    if (loadingMoreHistory.value || paginationHistory.value.next === 0) return;
    loadingMoreHistory.value = true;
    try {
        const res = await returnService.getReturns({
            limit: 10,
            page: paginationHistory.value.next,
        });
        returnHistory.value = [...returnHistory.value, ...res.results];
        paginationHistory.value = res.pagination;
    } catch (e) {
        snackbarStore.showMessage("Gagal memuat riwayat retur", "error");
    } finally {
        loadingMoreHistory.value = false;
    }
};

const loadMoreAction = async () => {
    if (loadingMoreAction.value || paginationAction.value.next === 0) return;
    loadingMoreAction.value = true;
    try {
        const res = await returnService.getReturns({
            limit: 10,
            page: paginationAction.value.next,
            filter: { action_required: true },
        });
        actionReturns.value = [...actionReturns.value, ...res.results];
        paginationAction.value = res.pagination;
    } catch (e) {
        snackbarStore.showMessage("Gagal memuat aksi retur", "error");
    } finally {
        loadingMoreAction.value = false;
    }
};

const returnActionLabel = (item: ReturnItem) => {
    if (item.actions?.can_ship_return) return "Pilih jadwal dan kirim retur";
    if (item.actions?.can_confirm_replacement) return "Konfirmasi barang pengganti";
    if (item.actions?.can_show_pickup_code) return "Tunjukkan PIN pickup kepada admin";

    return "Lihat detail retur";
};

const openReturnDetail = (item: ReturnItem) => {
    router.push(`/member/stock/returns/${encodeRouteId(item.id)}`);
};

const goToReturn = async (receipt: EligibleReceipt) => {
    if (openingReceiptId.value !== null) return;

    openingReceiptId.value = receipt.receive_id;
    try {
        const existing = await returnService.findExistingByReceipt(
            receipt.receive_id,
            receipt.receive_number,
        );

        if (existing) {
            sessionStorage.removeItem("return_eligible_receipt");
            await router.push(
                `/member/stock/returns/${encodeRouteId(existing.id)}`,
            );
            return;
        }

        sessionStorage.setItem(
            "return_eligible_receipt",
            JSON.stringify(receipt),
        );
        await router.push({
            path: "/member/stock/returns/create",
        });
    } catch (e) {
        snackbarStore.showMessage("Gagal memeriksa status retur", "error");
    } finally {
        openingReceiptId.value = null;
    }
};

onMounted(() => {
    fetchSummary();
    if (route.query.tab === "action") {
        activeTab.value = "action";
        fetchActions();
    } else if (route.query.tab === "history") {
        activeTab.value = "history";
        fetchHistory();
    } else {
        fetchEligible();
    }
});
</script>

<style scoped>
.flow-link {
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}
.flow-link:hover {
    border-color: var(--primary) !important;
}
.return-tabs {
    display: flex;
    gap: 4px;
    padding: 4px;
    border: 1px solid #ebcdd2;
    border-radius: 12px;
    background: #fff4f5;
}
.return-tab {
    display: inline-flex;
    flex: 1;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    border-radius: 9px;
    color: #8e2436;
    font-size: 13px;
    font-weight: 700;
    transition: all 0.2s ease-in-out;
}
.return-tab:hover {
    background: #fde5e9;
}
.return-tab.active {
    background: var(--wine);
    color: #fff;
    box-shadow: 0 2px 6px rgba(148, 9, 35, 0.28);
}
.tab-count {
    display: inline-flex;
    min-width: 20px;
    height: 20px;
    align-items: center;
    justify-content: center;
    margin-left: 6px;
    padding: 0 6px;
    border-radius: 999px;
    background: #fff1dc;
    color: #b95d00;
    font-size: 11px;
}
.return-tab.active .tab-count {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}
.tab-count.action {
    background: #fde8eb;
    color: var(--primary);
}
.tab-count.muted {
    background: #e8eaf0;
    color: #5f6470;
}
.action-callout {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px dashed var(--border);
    color: var(--primary);
}
.action-callout div {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.action-callout span {
    color: var(--muted);
    font-size: 11px;
}
.action-callout strong {
    font-size: 13px;
}
</style>
