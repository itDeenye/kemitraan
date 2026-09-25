<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-4 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Retur Penjualan</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk mengelola pengajuan retur dari mitra.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="dataTable"
            url="/admin/transactions/returns"
            :headers="headers"
        >
            <template #item.code="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-bold">
                        {{ item.code }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ formatDateTime(item.created_at) }}
                    </div>
                </div>
            </template>
            <template #item.member.name="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="font-weight-medium text-body-2">
                        {{ item.member?.name }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ item.member?.code || "-" }}
                    </div>
                </div>
            </template>
            <template #item.transaction.code="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-bold">
                        {{ item.goods_receive?.number }}
                    </div>
                    <div
                        class="text-caption text-medium-emphasis font-weight-medium"
                    >
                        {{ item.transaction?.code || "-" }}
                    </div>
                </div>
            </template>
            <template #item.status="{ item, column }">
                <BaseBadge
                    type="return_status"
                    :value="item.status"
                    :align="column.align"
                />
            </template>
            <template #item.actions="{ item }">
                <div class="d-flex ga-2 justify-center">
                    <v-btn
                        v-tooltip:top="'Detail Retur'"
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

        <ReturnDetailDialog ref="detailDialogRef" @updated="refreshData" />
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import ReturnDetailDialog from "@/admin/components/transactions/ReturnDetailDialog.vue";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime } = useFormatter();

const dataTable = ref<InstanceType<typeof BaseDataTable> | null>(null);
const detailDialogRef = ref<InstanceType<typeof ReturnDetailDialog> | null>(
    null,
);

const headers = [
    {
        title: "Kode Retur",
        key: "code",
        type: "text",
        align: "start",
        sortable: false,
        search: true,
        filter: true,
        placeholder: "Masukkan Kode Retur",
    },
    {
        title: "Tanggal Pengajuan",
        key: "created_at",
        type: "date",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
    },
    {
        title: "Mitra",
        key: "member.name",
        type: "text",
        align: "start",
        sortable: false,
        filter: true,
        filterLabel: "Nama Mitra",
        placeholder: "Masukkan Nama Mitra",
    },
    {
        title: "Kode Mitra",
        key: "member.code",
        type: "text",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Mitra",
    },
    {
        title: "Transaksi / Penerimaan",
        key: "transaction.code",
        align: "start",
        type: "text",
        sortable: false,
        filter: true,
        filterLabel: "Kode Transaksi",
        placeholder: "Masukkan Kode Transaksi",
    },
    {
        title: "Kode Penerimaan",
        key: "goods_receive.number",
        align: "start",
        type: "text",
        visible: false,
        sortable: false,
        filter: true,
        filterLabel: "Kode Penerimaan",
        placeholder: "Masukkan Kode Penerimaan",
    },
    {
        title: "Status",
        key: "status",
        type: "select",
        align: "center",
        sortable: false,
        filter: true,
        options: [
            { value: "submitted", label: "Menunggu Keputusan Admin" },
            { value: "approved", label: "Disetujui" },
            {
                value: "waiting_member_shipment",
                label: "Menunggu Pengiriman Mitra",
            },
            { value: "return_in_transit", label: "Dikirim ke Perusahaan" },
            {
                value: "return_shipping_failed",
                label: "Pengiriman ke Perusahaan Gagal",
            },
            {
                value: "received_by_company",
                label: "Diterima Perusahaan",
            },
            {
                value: "replacement_in_transit",
                label: "Barang Pengganti Dikirim",
            },
            {
                value: "replacement_shipping_failed",
                label: "Pengiriman Barang Pengganti Gagal",
            },
            { value: "completed", label: "Selesai" },
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
