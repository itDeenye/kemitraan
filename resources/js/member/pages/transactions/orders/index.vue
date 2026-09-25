<template>
    <div class="screen-body">
        <button
            class="transition-all text-white shadow-[0_1px_2px_rgba(220,38,38,0.2)] active:opacity-90 block w-full text-center rounded-lg"
            style="
                background-color: rgb(var(--v-theme-primary));
                border: 1px solid rgb(var(--v-theme-primary));
                padding: 10px 16px;
                font-size: 13px;
                font-weight: 600;
                margin-bottom: 12px;
            "
            @click="router.push('/member/transactions/orders/create')"
        >
            <v-icon icon="mdi-plus" size="16" style="margin-right: 4px" /> Buat
            Pesanan Pembelian
        </button>

        <div class="filter-chips">
            <span
                v-for="filter in [
                    'Semua',
                    'Menunggu Pembayaran',
                    'Menunggu Verifikasi Pembayaran',
                    'Dikemas',
                    'Dikirim',
                    'Selesai',
                ]"
                :key="filter"
                class="filter-chip"
                :class="{ active: currentFilter === filter }"
                @click="currentFilter = filter"
            >
                {{ filter }}
            </span>
        </div>

        <div class="search-box">
            <v-icon icon="mdi-magnify" size="small" />
            <input
                type="text"
                v-model="searchQuery"
                placeholder="Cari kode transaksi…"
                class="bg-transparent border-none outline-none w-full ml-2"
            />
        </div>

        <div class="section-heading items-end justify-end mt-4">
            <span>{{ pagination.total_data }} transaksi</span>
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
        <div class="flex flex-col gap-3" v-else-if="orders.length > 0">
            <div
                v-for="order in orders"
                :key="order.id"
                class="card overflow-hidden"
                style="margin-bottom: 16px"
            >
                <div style="padding: 16px 20px">
                    <div
                        class="flex items-start gap-2"
                        style="margin-bottom: 12px"
                    >
                        <span class="row-icon w-[25px] h-[25px] shrink-0">
                            <v-icon icon="mdi-storefront-outline" size="24" />
                        </span>
                        <div class="flex-1 min-w-0">
                            <strong
                                class="text-[13px] block truncate leading-tight"
                                >{{
                                    order.seller?.name || "Distributor Utama"
                                }}</strong
                            >
                            <span
                                class="text-[11px] font-normal text-[var(--muted)] block truncate"
                                >{{ order.code }}</span
                            >
                        </div>
                        <div class="flex flex-col items-end shrink-0 gap-1">
                            <span
                                class="text-[11px] text-[var(--muted)] whitespace-nowrap"
                                >{{
                                    formatDateTime(
                                        order.ordered_at || order.created_at,
                                    )
                                }}</span
                            >
                            <BaseBadge
                                type="transaction_status"
                                :value="order.status?.code"
                                inline
                                chip-class="font-semibold"
                            />
                        </div>
                    </div>

                    <div>
                        <div class="flex gap-3 mb-3">
                            <div
                                class="w-[52px] h-[52px] rounded-lg bg-[#f0f0f0] overflow-hidden flex items-center justify-center shrink-0"
                            >
                                <v-img
                                    v-if="
                                        order.product_preview?.image ||
                                        order.image
                                    "
                                    :src="
                                        order.product_preview?.image ||
                                        order.image
                                    "
                                    cover
                                ></v-img>
                                <v-icon
                                    v-else
                                    icon="mdi-image-outline"
                                    color="#ccc"
                                    size="24"
                                ></v-icon>
                            </div>
                            <div
                                class="flex-1 min-w-0 flex flex-col justify-center"
                            >
                                <strong
                                    class="text-[13px] leading-tight truncate"
                                    >{{
                                        order.product_preview?.name ||
                                        order.summary?.product_count + " Produk"
                                    }}</strong
                                >
                                <span
                                    v-if="order.product_preview"
                                    class="text-[11px] text-[#757575] mt-0.5 truncate"
                                >
                                    {{
                                        order.product_preview?.code
                                            ? order.product_preview?.code +
                                              " · "
                                            : ""
                                    }}
                                    x{{ order.product_preview?.quantity }}
                                </span>
                                <span
                                    v-else
                                    class="text-[11px] text-[#757575] mt-0.5"
                                >
                                    Total
                                    {{ order.summary?.total_quantity || 1 }} pcs
                                </span>
                            </div>
                            <div
                                class="text-[13px] font-semibold text-[var(--ink)] flex items-center shrink-0 whitespace-nowrap"
                            >
                                Rp{{
                                    formatPrice(
                                        order.product_preview?.subtotal ||
                                            order.summary?.grand_total ||
                                            0,
                                    )
                                }}
                            </div>
                        </div>

                        <div
                            v-if="
                                (order.product_preview?.other_product_count ||
                                    0) > 0
                            "
                        >
                            <div
                                class="text-xs text-[#a01526] font-medium my-2"
                            >
                                +
                                {{ order.product_preview?.other_product_count }}
                                produk lainnya
                            </div>
                        </div>
                    </div>

                    <div
                        :class="[
                            'rounded-md text-[13px]',
                            getOrderNoteClass(order),
                        ]"
                        style="padding: 12px 16px; margin: 12px 0 16px 0"
                        v-if="getOrderNote(order)"
                    >
                        {{ getOrderNote(order) }}
                    </div>

                    <div
                        class="flex flex-col border-t border-dashed border-[var(--line)]"
                        style="padding-top: 14px; gap: 3px"
                    >
                        <div
                            class="flex justify-between items-center text-[12px] text-[var(--muted)]"
                        >
                            <span>Total Harga Produk</span>
                            <span class="text-[var(--ink)]"
                                >Rp{{
                                    formatPrice(
                                        order.summary?.product_total || 0,
                                    )
                                }}</span
                            >
                        </div>
                        <div
                            v-if="(order.summary?.shipping_cost || 0) > 0"
                            class="flex justify-between items-center text-[12px] text-[var(--muted)]"
                        >
                            <span>Ongkos Kirim</span>
                            <span class="text-[var(--ink)]"
                                >Rp{{
                                    formatPrice(
                                        order.summary?.shipping_cost || 0,
                                    )
                                }}</span
                            >
                        </div>
                        <div
                            v-if="
                                (order.summary?.shipping_cost_insurance || 0) >
                                0
                            "
                            class="flex justify-between items-center text-[12px] text-[var(--muted)]"
                        >
                            <span>Asuransi Pengiriman</span>
                            <span class="text-[var(--ink)]"
                                >Rp{{
                                    formatPrice(
                                        order.summary
                                            ?.shipping_cost_insurance || 0,
                                    )
                                }}</span
                            >
                        </div>
                        <div
                            v-if="(order.summary?.payment_charge || 0) > 0"
                            class="flex justify-between items-center text-[12px] text-[var(--muted)]"
                        >
                            <span>Biaya Layanan</span>
                            <span class="text-[var(--ink)]"
                                >Rp{{
                                    formatPrice(
                                        order.summary?.payment_charge || 0,
                                    )
                                }}</span
                            >
                        </div>
                        <div
                            v-if="(order.summary?.total_discount || 0) > 0"
                            class="flex justify-between items-center text-[12px] text-[var(--muted)]"
                        >
                            <span>Diskon</span>
                            <span class="text-[var(--danger)]"
                                >-Rp{{
                                    formatPrice(
                                        order.summary?.total_discount || 0,
                                    )
                                }}</span
                            >
                        </div>
                        <div
                            v-if="(order.summary?.voucher_discount || 0) > 0"
                            class="flex justify-between items-center text-[12px] text-[var(--muted)]"
                        >
                            <span>Potongan Voucher</span>
                            <span class="text-[var(--danger)]"
                                >-Rp{{
                                    formatPrice(
                                        order.summary?.voucher_discount || 0,
                                    )
                                }}</span
                            >
                        </div>

                        <div
                            class="flex justify-between items-center mt-2 pt-2 border-t border-[var(--line)]"
                        >
                            <span
                                class="text-[12px] font-medium text-[var(--ink)]"
                                >Total Pembayaran</span
                            >
                            <strong class="text-[14px] text-[var(--ink)]"
                                >Rp{{
                                    formatPrice(order.summary?.grand_total || 0)
                                }}</strong
                            >
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div
                    class="flex justify-end flex-wrap"
                    style="
                        gap: 8px;
                        padding: 12px 20px;
                        background-color: #fcfcfc;
                        border-top: 1px solid var(--line);
                    "
                >
                    <button
                        v-if="order.actions?.can_cancel"
                        class="font-semibold transition-all bg-white text-[var(--ink)] shadow-[0_1px_2px_rgba(0,0,0,0.05)] active:bg-[#f5f5f5]"
                        style="
                            padding: 6px 12px;
                            font-size: 11px;
                            border-radius: 6px;
                            border: 1px solid #e0e0e0;
                        "
                        type="button"
                        @click="cancelOrder(order)"
                    >
                        Batalkan
                    </button>
                    <button
                        v-if="order.actions?.can_upload_payment"
                        class="font-semibold transition-all text-white shadow-[0_1px_2px_rgba(220,38,38,0.2)] active:opacity-90"
                        style="
                            background-color: rgb(var(--v-theme-primary));
                            border: 1px solid rgb(var(--v-theme-primary));
                            padding: 6px 12px;
                            font-size: 11px;
                            border-radius: 6px;
                        "
                        type="button"
                        @click="
                            router.push(
                                `/member/transactions/orders/payment?id=${encodeRouteId(order.id)}`,
                            )
                        "
                    >
                        Upload Bukti
                    </button>
                    <button
                        class="font-semibold transition-all bg-white text-[var(--ink)] shadow-[0_1px_2px_rgba(0,0,0,0.05)] active:bg-[#f5f5f5]"
                        style="
                            padding: 6px 12px;
                            font-size: 11px;
                            border-radius: 6px;
                            border: 1px solid #e0e0e0;
                        "
                        type="button"
                        @click="
                            router.push(
                                `/member/transactions/orders/${encodeRouteId(order.id)}`,
                            )
                        "
                    >
                        Lihat Detail
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="empty-state">
            <v-icon icon="mdi-cart-off" size="48"></v-icon>
            <h4>Tidak ada pesanan</h4>
            <p>Belum ada pesanan pembelian yang ditemukan.</p>
        </div>

        <div
            v-if="pagination.next !== 0 && !isLoading"
            class="pagination-row mt-4"
        >
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

        <MobileConfirm ref="confirmDialog" />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import purchaseService from "@/member/services/purchase.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import MobileConfirm from "@/member/components/MobileConfirm.vue";
import type {
    PurchaseOrder,
    DataTablePagination,
} from "@/member/types/purchase";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { encodeRouteId } from "@/shared/utils/route-id";

const router = useRouter();
const { formatPrice, formatDateTime } = useFormatter();
const snackbar = useSnackbarStore();

const confirmDialog = ref<InstanceType<typeof MobileConfirm> | null>(null);

const currentFilter = ref("Semua");
const searchQuery = ref("");
const orders = ref<PurchaseOrder[]>([]);
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
        fetchOrders(1);
    }, 500);
});

watch(currentFilter, () => {
    fetchOrders(1);
});

const getStatusFilter = () => {
    switch (currentFilter.value) {
        case "Menunggu Pembayaran":
            return "waiting_payment";
        case "Menunggu Verifikasi Pembayaran":
            return "waiting_payment_approval";
        case "Dikemas":
            return "processing";
        case "Dikirim":
            return "shipped";
        case "Selesai":
            return "completed";
        default:
            return undefined;
    }
};

const fetchOrders = async (page = 1, append = false) => {
    if (append) loadingMore.value = true;
    else isLoading.value = true;

    try {
        const response = await purchaseService.getOrders({
            page,
            limit: 10,
            search: searchQuery.value || undefined,
            filter: {
                status: getStatusFilter(),
            },
        });
        if (response.success && response.data) {
            if (append) {
                orders.value = [...orders.value, ...response.data.results];
            } else {
                orders.value = response.data.results;
            }
            pagination.value = response.data.pagination;
        }
    } catch (error) {
        console.error("Failed to fetch orders:", error);
    } finally {
        isLoading.value = false;
        loadingMore.value = false;
    }
};

const loadMore = () => {
    if (pagination.value.next !== 0) {
        fetchOrders(pagination.value.next, true);
    }
};

const getOrderNote = (order: any) => {
    if (order.actions?.waiting_for_previous_approval)
        return "Menunggu pembayaran PO tahap sebelumnya disetujui.";
    if (
        order.payment_status === "pending" &&
        order.status.code === "waiting_payment"
    )
        return "Selesaikan pembayaran agar pesanan segera diproses.";
    if (order.payment_status === "pending" && order.status.code === "cancelled")
        return "Pesanan ini telah dibatalkan.";

    const status = order.status;
    if (!status) return "";
    const lowerStatus =
        typeof status === "string"
            ? status.toLowerCase()
            : String(
                  (status as any)?.label || (status as any)?.code || "",
              ).toLowerCase();
    if (
        lowerStatus.includes("kemas") ||
        lowerStatus.includes("proses") ||
        lowerStatus.includes("setuju")
    )
        return "Penjual sedang menyiapkan pesanan Anda.";
    if (lowerStatus.includes("kirim"))
        return "Pesanan dalam perjalanan. Konfirmasi setelah barang tiba.";
    return "";
};

const getOrderNoteClass = (order: any) => {
    if (order.status?.code === "cancelled") return "bg-red-400/10 text-red-700";
    if (order.status?.code === "shipped" || order.status?.code === "processing")
        return "bg-blue-400/10 text-blue-700";
    return "bg-yellow-400/10 text-yellow-700";
};

const cancelOrder = async (order: any) => {
    if (!confirmDialog.value) return;
    const isConfirmed = await confirmDialog.value.open({
        title: "Batalkan Pesanan",
        message: `Apakah Anda yakin ingin membatalkan pesanan dengan kode <strong class="font-bold text-[#091426]">${order.code}</strong>?`,
        confirmText: "Ya, Batalkan",
        confirmColor: "error",
        icon: "mdi-alert-circle-outline",
        iconColor: "error",
    });
    if (!isConfirmed) return;

    try {
        await purchaseService.cancelOrder(order.id);
        snackbar.showMessage("Pesanan berhasil dibatalkan.", "success");
        fetchOrders();
    } catch (error) {
        console.error("Failed to cancel order:", error);
        snackbar.showMessage(
            "Terjadi kesalahan saat membatalkan pesanan.",
            "error",
        );
    }
};

onMounted(() => {
    fetchOrders();
});
</script>
