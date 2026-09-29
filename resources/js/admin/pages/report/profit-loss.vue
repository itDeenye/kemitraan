<template>
    <div class="mb-6 d-flex flex-column">
        <h1 class="text-h4 font-weight-bold mb-1">Laporan Penjualan</h1>
        <p class="text-body-2 text-medium-emphasis">
            Halaman untuk melihat laporan keuntungan dan kerugian dari penjualan
            produk.
        </p>
    </div>

    <BaseDataTable
        ref="profitLossTable"
        url="/admin/reports/partnership-sales"
        :headers="headers"
        :query-params="extraParams"
    >
        <template #header-actions>
            <div class="d-flex align-center ga-3">
                <div
                    class="d-flex align-center ga-2 bg-surface pa-1 rounded-lg border"
                >
                    <span class="text-caption font-weight-medium px-2 pt-2"
                        >Dari:</span
                    >
                    <v-text-field
                        v-model="dateFrom"
                        type="date"
                        variant="plain"
                        density="compact"
                        hide-details
                        style="width: 130px"
                    ></v-text-field>
                </div>

                <div
                    class="d-flex align-center ga-2 bg-surface pa-1 rounded-lg border"
                >
                    <span class="text-caption font-weight-medium px-2 pt-2"
                        >Sampai:</span
                    >
                    <v-text-field
                        v-model="dateTo"
                        type="date"
                        variant="plain"
                        density="compact"
                        hide-details
                        style="width: 130px"
                    ></v-text-field>
                </div>
            </div>
        </template>

        <template #item.code="{ item }">
            <div class="d-flex flex-column py-2">
                <div class="text-body-2 font-weight-bold">
                    {{ item.code }}
                </div>
                <div class="text-caption text-medium-emphasis">
                    {{ formatDateTime(item.datetime) }}
                </div>
            </div>
        </template>

        <template #item.is_preorder="{ item }">
            <v-chip
                :color="item.is_preorder ? 'warning' : 'info'"
                size="small"
                variant="tonal"
                class="font-weight-medium"
            >
                {{ item.is_preorder ? "Pre-Order" : "Reguler" }}
            </v-chip>
        </template>

        <template #item.buyer.name="{ item }">
            <div class="d-flex flex-column py-2">
                <div class="font-weight-medium text-body-2">
                    {{ item.buyer?.name || "-" }}
                </div>
                <div class="text-caption text-medium-emphasis">
                    {{ item.buyer?.code || "-" }}
                </div>
            </div>
        </template>

        <template #item.total_price="{ item }">
            <span class="font-weight-medium">{{
                formatPrice(item.total_price)
            }}</span>
        </template>

        <template #item.status="{ item }">
            <BaseBadge type="transaction_status" :value="item.status" inline />
        </template>

        <template #item.actions="{ item }">
            <div class="d-flex ga-2 justify-center">
                <v-btn
                    v-tooltip:top="'Lihat Detail'"
                    icon="mdi-eye-outline"
                    variant="outlined"
                    size="small"
                    class="rounded"
                    color="primary"
                    @click="openDetail(item)"
                />
            </div>
        </template>
    </BaseDataTable>

    <ProfitLossDetailDialog ref="detailDialog" />
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useDateRange } from "@/shared/composables/useDateRange";
import ProfitLossDetailDialog from "../../components/report/ProfitLossDetailDialog.vue";

const { formatPrice, formatDateTime } = useFormatter();

const { dateFrom, dateTo } = useDateRange();

const profitLossTable = ref<InstanceType<typeof BaseDataTable> | null>(null);

const extraParams = computed(() => ({
    date_from: dateFrom.value,
    date_to: dateTo.value,
    // pagination_bool: false,
}));

const detailDialog = ref<InstanceType<typeof ProfitLossDetailDialog> | null>(
    null,
);

const openDetail = (item: any) => {
    detailDialog.value?.open(item.id);
};

const headers = [
    {
        title: "Kode Transaksi",
        key: "code",
        type: "text",
        align: "start",
        sortable: true,
        filter: true,
        placeholder: "Masukkan Kode Transaksi",
    },
    {
        title: "Tanggal Transaksi",
        key: "datetime",
        type: "date",
        align: "start",
        sortable: true,
        visible: false,
        filter: true,
    },
    {
        title: "Tipe",
        key: "is_preorder",
        align: "center",
        type: "select",
        sortable: false,
        filter: true,
        options: [
            { label: "Reguler", value: "0" },
            { label: "Pre-Order", value: "1" },
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
        visible: false,
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Mitra",
    },
    {
        title: "Total Transaksi (Rp)",
        key: "total_price",
        align: "right",
        sortable: true,
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
</script>
