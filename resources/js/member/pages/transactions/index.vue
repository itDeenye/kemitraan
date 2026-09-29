<template>
    <div class="screen-body">
        <div class="section-heading mt-4">
            <h3>Pilih transaksi</h3>
        </div>

        <div class="card menu-list">
            <div
                class="flow-link"
                @click="router.push('/member/transactions/orders')"
            >
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-cart-arrow-down" size="18" />
                    </div>
                    <div class="row-main">
                        <strong>Pesanan Pembelian</strong>
                        <span>Buat pesanan dan upload bukti bayar</span>
                    </div>
                    <div class="row-side">
                        <span
                            v-if="isLoadingSummary"
                            class="skeleton-text"
                            style="
                                width: 24px;
                                height: 20px;
                                margin: 0;
                                border-radius: 6px;
                            "
                        ></span>
                        <template v-else>
                            <span
                                class="badge orange"
                                v-if="summary?.purchases?.action_required"
                                >{{ summary.purchases.action_required }}</span
                            >
                            <span class="badge gray" v-else>0</span>
                        </template>
                    </div>
                </div>
            </div>
            <div
                class="flow-link"
                @click="router.push('/member/transactions/goods-receipts')"
            >
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon
                            icon="mdi-package-variant-closed-plus"
                            size="18"
                        />
                    </div>
                    <div class="row-main">
                        <strong>Penerimaan Barang</strong>
                        <span>Daftar dan detail penerimaan barang</span>
                    </div>
                    <div class="row-side">
                        <span
                            v-if="isLoadingSummary"
                            class="skeleton-text"
                            style="
                                width: 24px;
                                height: 20px;
                                margin: 0;
                                border-radius: 6px;
                            "
                        ></span>
                        <template v-else>
                            <span
                                class="badge orange"
                                v-if="summary?.goods_receipts?.ready_to_receive"
                                >{{
                                    summary.goods_receipts.ready_to_receive
                                }}</span
                            >
                            <span class="badge gray" v-else>0</span>
                        </template>
                    </div>
                </div>
            </div>
            <div
                class="flow-link"
                @click="router.push('/member/transactions/sales')"
            >
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-storefront-plus-outline" size="18" />
                    </div>
                    <div class="row-main">
                        <strong>Pesanan Penjualan</strong>
                        <span>Verifikasi bayar, proses, dan kirim</span>
                    </div>
                    <div class="row-side">
                        <span
                            v-if="isLoadingSummary"
                            class="skeleton-text"
                            style="
                                width: 24px;
                                height: 20px;
                                margin: 0;
                                border-radius: 6px;
                            "
                        ></span>
                        <template v-else>
                            <span
                                class="badge orange"
                                v-if="summary?.sales?.action_required"
                                >{{ summary.sales.action_required }}</span
                            >
                            <span class="badge gray" v-else>0</span>
                        </template>
                    </div>
                </div>
            </div>
            <div
                class="flow-link"
                @click="router.push('/member/transactions/sales/create')"
            >
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon
                            icon="mdi-receipt-text-send-outline"
                            size="18"
                        />
                    </div>
                    <div class="row-main">
                        <strong>POS</strong>
                        <span>Penjualan langsung kepada customer</span>
                    </div>
                    <div class="row-side">›</div>
                </div>
            </div>
        </div>

        <div class="section-heading mt-4">
            <h3>Transaksi terbaru</h3>
            <a
                @click="router.push('/member/transactions/orders')"
                style="cursor: pointer"
                >Lihat semua</a
            >
        </div>

        <div class="card list-card" v-if="orders.length > 0">
            <div
                v-for="order in orders"
                :key="order.id"
                class="list-row flow-link !flex-col sm:!flex-row !items-start sm:!items-center gap-2 sm:gap-0"
                @click="
                    router.push(
                        `/member/transactions/orders/${encodeRouteId(order.id)}`,
                    )
                "
            >
                <div class="flex items-center w-full min-w-0">
                    <div class="row-icon shrink-0 mr-2">
                        <v-icon icon="mdi-receipt-text-outline" size="16" />
                    </div>
                    <div class="row-main min-w-0 pr-2 flex-1">
                        <strong class="truncate block">{{
                            order.code || order.invoice
                        }}</strong>
                        <span class="block truncate"
                            >{{
                                formatDateTime(
                                    order.ordered_at || order.created_at || "",
                                )
                            }}
                            · Rp{{
                                formatPrice(
                                    order.summary?.grand_total ||
                                        order.total ||
                                        0,
                                )
                            }}</span
                        >
                    </div>
                </div>
                <div
                    class="row-side shrink-0 pl-11 sm:pl-0 w-full sm:w-auto flex sm:block"
                >
                    <BaseBadge
                        type="transaction_status"
                        :value="order.status?.code"
                        inline
                        chip-class="font-semibold"
                    />
                </div>
            </div>
        </div>
        <div v-else class="empty-state my-4">
            <template v-if="!isLoading">
                <v-icon icon="mdi-cart-off" size="48"></v-icon>
                <h4>Tidak ada transaksi</h4>
                <p>Belum ada transaksi terbaru.</p>
            </template>
            <v-progress-circular
                v-else
                indeterminate
                color="primary"
                size="24"
            ></v-progress-circular>
        </div>

        <button
            class="primary-button block mt-3"
            @click="router.push('/member/transactions/orders/create')"
        >
            <v-icon icon="mdi-plus" size="14" /> Buat Pesanan Pembelian
        </button>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import MemberLayout from "@/member/layouts/MemberLayout.vue";
import purchaseService from "@/member/services/purchase.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { encodeRouteId } from "@/shared/utils/route-id";
import type {
    PurchaseOrder,
    PurchaseOrderSummary,
} from "@/member/types/purchase";

const router = useRouter();
const { formatPrice, formatDateTime } = useFormatter();

const orders = ref<PurchaseOrder[]>([]);
const summary = ref<PurchaseOrderSummary | null>(null);
const isLoading = ref(true);
const isLoadingSummary = ref(true);

const fetchLatestOrders = async () => {
    isLoading.value = true;
    try {
        const response = await purchaseService.getOrders({ limit: 3 });
        if (response.success && response.data) {
            // Because API is returning empty right now, it will be empty array
            orders.value = response.data.results || [];
        }
    } catch (error) {
        console.error("Failed to fetch latest orders:", error);
    } finally {
        isLoading.value = false;
    }
};

const fetchSummary = async () => {
    isLoadingSummary.value = true;
    try {
        const response = await purchaseService.getOrderSummary();
        if (response.success && response.data) {
            summary.value = response.data;
        }
    } catch (error) {
        console.error("Failed to fetch summary:", error);
    } finally {
        isLoadingSummary.value = false;
    }
};

const alert = (msg: string) => {
    window.alert(msg);
};

onMounted(() => {
    fetchLatestOrders();
    fetchSummary();
});
</script>

<style scoped>
.menu-list {
    padding: 20 10;
}
.menu-list .flow-link {
    display: block;
    cursor: pointer;
}
.menu-list .list-row {
    border-bottom: 1px solid var(--border-light);
}
.menu-list .flow-link:last-child .list-row {
    border-bottom: none;
}
</style>
