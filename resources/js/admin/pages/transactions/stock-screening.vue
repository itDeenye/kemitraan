<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-4 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Screening Stok</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk melakukan screening ketersediaan stok pesanan
                    dari distributor.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="dataTable"
            url="/admin/transactions/orders"
            :headers="headers"
            :query-params="queryParams"
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
                        v-if="item.actions?.can_approve_stock_screening"
                        v-tooltip:top="'Screening Stok'"
                        icon="mdi-clipboard-check-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="info"
                        @click="openDetail(item.id)"
                    />
                    <v-btn
                        v-else
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

        <StockScreeningDialog ref="detailDialogRef" @updated="refreshData" />
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import StockScreeningDialog from "@/admin/components/transactions/StockScreeningDialog.vue";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime, formatPrice } = useFormatter();

const dataTable = ref<InstanceType<typeof BaseDataTable> | null>(null);
const detailDialogRef = ref<InstanceType<typeof StockScreeningDialog> | null>(
    null,
);

const queryParams = computed(() => ({
    "filter[buyer.type]": "distributor",
    "filter[status]": "waiting_stock_screening",
    "filter[can_approve_stock_screening]": 1,
}));

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
        title: "Waktu Pesanan",
        key: "ordered_at",
        type: "date",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
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
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Mitra",
    },
    {
        title: "Nominal (Rp)",
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
        filter: false,
    },
    {
        title: "Aksi",
        key: "actions",
        align: "center",
        sortable: false,
    },
];

const refreshData = () => {
    dataTable.value?.refresh();
};

const openDetail = (id: string | number) => {
    detailDialogRef.value?.open(id);
};
</script>

<style scoped>
.gap-2 {
    gap: 8px;
}
</style>
