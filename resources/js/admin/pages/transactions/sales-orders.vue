<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-4 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Pesanan Penjualan</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk memantau dan mengelola semua transaksi
                    penjualan.
                </p>
            </div>
        </div>

        <v-row class="mb-4">
            <v-col cols="12" sm="6" md="2">
                <v-card
                    variant="outlined"
                    class="rounded-lg h-100 pa-4 d-flex flex-column bg-surface"
                >
                    <div class="text-caption text-medium-emphasis mb-1">
                        Total Pesanan
                    </div>
                    <v-skeleton-loader
                        v-if="loadingSummary"
                        type="text"
                        width="60"
                    ></v-skeleton-loader>
                    <div v-else class="text-h5 font-weight-bold">
                        {{ summary.total }}
                    </div>
                </v-card>
            </v-col>
            <v-col cols="12" sm="6" md="2">
                <v-card
                    variant="outlined"
                    class="rounded-lg h-100 pa-4 d-flex flex-column bg-surface"
                >
                    <div class="text-caption text-medium-emphasis mb-1">
                        Menunggu Pembayaran
                    </div>
                    <v-skeleton-loader
                        v-if="loadingSummary"
                        type="text"
                        width="60"
                    ></v-skeleton-loader>
                    <div v-else class="text-h5 font-weight-bold">
                        {{ summary.waiting_payment }}
                    </div>
                </v-card>
            </v-col>
            <v-col cols="12" sm="6" md="2">
                <v-card
                    variant="outlined"
                    class="rounded-lg h-100 pa-4 d-flex flex-column bg-surface"
                >
                    <div class="text-caption text-medium-emphasis mb-1">
                        Menunggu Verifikasi
                    </div>
                    <v-skeleton-loader
                        v-if="loadingSummary"
                        type="text"
                        width="60"
                    ></v-skeleton-loader>
                    <div v-else class="text-h5 font-weight-bold text-info">
                        {{ summary.waiting_payment_approval }}
                    </div>
                </v-card>
            </v-col>
            <v-col cols="12" sm="6" md="2">
                <v-card
                    variant="outlined"
                    class="rounded-lg h-100 pa-4 d-flex flex-column bg-surface"
                >
                    <div class="text-caption text-medium-emphasis mb-1">
                        Dikemas
                    </div>
                    <v-skeleton-loader
                        v-if="loadingSummary"
                        type="text"
                        width="60"
                    ></v-skeleton-loader>
                    <div v-else class="text-h5 font-weight-bold text-warning">
                        {{ summary.processing }}
                    </div>
                </v-card>
            </v-col>
            <v-col cols="12" sm="6" md="2">
                <v-card
                    variant="outlined"
                    class="rounded-lg h-100 pa-4 d-flex flex-column bg-surface"
                >
                    <div class="text-caption text-medium-emphasis mb-1">
                        Dikirim
                    </div>
                    <v-skeleton-loader
                        v-if="loadingSummary"
                        type="text"
                        width="60"
                    ></v-skeleton-loader>
                    <div v-else class="text-h5 font-weight-bold text-info">
                        {{ summary.delivery }}
                    </div>
                </v-card>
            </v-col>
            <v-col cols="12" sm="6" md="2">
                <v-card
                    variant="outlined"
                    class="rounded-lg h-100 pa-4 d-flex flex-column bg-surface"
                >
                    <div class="text-caption text-medium-emphasis mb-1">
                        Selesai
                    </div>
                    <v-skeleton-loader
                        v-if="loadingSummary"
                        type="text"
                        width="60"
                    ></v-skeleton-loader>
                    <div v-else class="text-h5 font-weight-bold text-success">
                        {{ summary.completed }}
                    </div>
                </v-card>
            </v-col>
        </v-row>

        <BaseDataTable
            ref="dataTable"
            url="/admin/transactions/orders"
            :headers="headers"
            :query-params="queryParams"
            @update:filters="handleFilterChange"
        >
            <template #item.buyer.name="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="font-weight-medium text-body-2">
                        {{ item.buyer?.name }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ item.buyer?.code || "-" }}
                    </div>
                    <div>
                        <v-chip
                            size="x-small"
                            variant="tonal"
                            color="primary"
                            class="mt-1 font-weight-medium text-capitalize"
                        >
                            {{ item.buyer?.type || "Unknown" }}
                        </v-chip>
                    </div>
                </div>
            </template>
            <template #item.order_type.code="{ item }">
                <v-chip
                    :color="
                        item.order_type?.code === 'preorder'
                            ? 'warning'
                            : 'info'
                    "
                    size="small"
                    variant="tonal"
                    class="font-weight-medium"
                >
                    {{ item.order_type?.label || "Reguler" }}
                </v-chip>
            </template>
            <template #item.code="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-bold">
                        {{ item.code }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ formatDateTime(item.ordered_at) }}
                    </div>
                </div>
            </template>
            <template #item.shipping_method="{ item, column }">
                <BaseBadge
                    type="shipping_method"
                    :value="item.shipping_method"
                    :align="column.align"
                />
            </template>
            <template #item.totals.grand_total="{ item }">
                <div class="text-body-2 font-weight-bold">
                    {{ formatPrice(item.totals?.grand_total || 0) }}
                </div>
            </template>
            <template #item.status="{ item, column }">
                <BaseBadge
                    type="transaction_status"
                    :value="item.status"
                    :align="column.align"
                />
            </template>
            <template #item.actions="{ item }">
                <div class="d-flex ga-2 justify-center">
                    <v-btn
                        v-tooltip:top="'Detail Pesanan'"
                        icon="mdi-eye-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="primary"
                        @click="openDetail(item.id)"
                    />
                </div>
            </template>
        </BaseDataTable>

        <OrderDetailDialog ref="detailDialogRef" />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import OrderDetailDialog from "@/admin/components/transactions/OrderDetailDialog.vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import transactionOrderService from "@/admin/services/transaction-order.service";
import type { TransactionOrderSummary } from "@/admin/types/transaction-order";

const { formatDateTime, formatPrice } = useFormatter();

const dataTable = ref<InstanceType<typeof BaseDataTable> | null>(null);
const detailDialogRef = ref<InstanceType<typeof OrderDetailDialog> | null>(
    null,
);

const dateFrom = ref("");
const dateTo = ref("");

const loadingSummary = ref(true);

const summary = ref<TransactionOrderSummary>({
    total: 0,
    waiting_payment: 0,
    waiting_payment_approval: 0,
    processing: 0,
    delivery: 0,
    completed: 0,
    cancelled: 0,
});

const queryParams = computed(() => ({
    "filter[buyer.type]": "distributor",
}));

const handleFilterChange = (filters: Record<string, string>) => {
    let hasDateFilter = false;
    for (const [key, value] of Object.entries(filters)) {
        if (key.startsWith("ordered_at[")) {
            hasDateFilter = true;
            if (value.includes("::")) {
                const [start, end] = value.split("::");
                dateFrom.value = start;
                dateTo.value = end;
            } else {
                dateFrom.value = value;
                dateTo.value = value;
            }
            break;
        }
    }

    if (!hasDateFilter) {
        dateFrom.value = "";
        dateTo.value = "";
    }

    fetchSummary();
};

const headers = [
    {
        title: "Kode Transaksi",
        key: "code",
        type: "text",
        align: "start",
        sortable: false,
        search: true,
        filter: true,
        placeholder: "Masukkan Kode Transaksi",
    },
    {
        title: "Tipe Pesanan",
        key: "order_type.code",
        filterKey: "is_preorder",
        align: "center",
        type: "select",
        sortable: false,
        filter: true,
        options: [
            { label: "Reguler", value: 0 },
            { label: "Pre-Order", value: 1 },
        ],
        placeholder: "Pilih Tipe",
    },
    {
        title: "Tanggal Transaksi",
        key: "ordered_at",
        type: "date",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
    },
    {
        title: "Mitra",
        key: "buyer.name",
        type: "text",
        align: "start",
        sortable: false,
        filter: true,
        filterLabel: "Nama Mitra",
        placeholder: "Masukkan Nama Mitra",
    },
    {
        title: "Kode Mitra",
        key: "buyer.code",
        type: "text",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Mitra",
    },
    {
        title: "Metode Pengiriman",
        key: "shipping_method",
        type: "select",
        align: "center",
        sortable: false,
        filter: true,
        options: [
            { label: "Kurir Ekspres", value: "courier_express" },
            { label: "Ambil Sendiri", value: "pickup" },
        ],
        placeholder: "Pilih Metode",
    },
    {
        title: "Total Transaksi (Rp)",
        key: "totals.grand_total",
        align: "end",
        sortable: false,
    },
    {
        title: "Status",
        key: "status",
        type: "select",
        align: "center",
        sortable: false,
        filter: true,
        options: [
            {
                value: "waiting_stock_screening",
                label: "Menunggu Screening Stok",
            },
            { value: "waiting_payment", label: "Menunggu Pembayaran" },
            {
                value: "waiting_payment_approval",
                label: "Menunggu Verifikasi Pembayaran",
            },
            { value: "processing", label: "Dikemas" },
            { value: "shipped", label: "Dikirim" },
            { value: "reship_required", label: "Perlu Dikirim Ulang" },
            { value: "ready_to_pickup", label: "Siap Diambil" },
            { value: "received", label: "Siap Diterima" },
            { value: "completed", label: "Selesai" },
            { value: "cancelled", label: "Dibatalkan" },
            { value: "rejected", label: "Ditolak" },
        ],
        placeholder: "Pilih Status",
    },
    {
        title: "Aksi",
        key: "actions",
        align: "center",
        sortable: false,
    },
];

const fetchSummary = async () => {
    loadingSummary.value = true;
    try {
        const data = await transactionOrderService.getSummary(
            dateFrom.value,
            dateTo.value,
        );
        if (data) {
            summary.value = data;
        }
    } catch (error) {
        console.error("Failed to fetch order summary:", error);
    } finally {
        loadingSummary.value = false;
    }
};

const openDetail = (id: string | number) => {
    detailDialogRef.value?.open(id);
};

onMounted(() => {
    fetchSummary();
});
</script>

<style scoped>
.gap-2 {
    gap: 8px;
}
</style>
