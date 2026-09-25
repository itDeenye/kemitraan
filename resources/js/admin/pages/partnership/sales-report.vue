<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-4 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">
                    Laporan Penjualan Mitra
                </h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk melihat dan memantau penjualan yang dilakukan
                    mitra kepada mitra atau customer DNY Skincare.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="dataTable"
            url="/admin/reports/partnership-sales"
            :headers="headers"
        >
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
            <template #item.buyer.name="{ item }">
                <div class="d-flex flex-column">
                    <p class="text-body-2 font-weight-bold mb-0">
                        {{ item.buyer?.name }}
                    </p>
                    <p class="text-caption text-medium-emphasis mb-0">
                        {{ partySubtitle(item.buyer) }}
                    </p>
                </div>
            </template>
            <template #item.seller.name="{ item }">
                <div class="d-flex flex-column">
                    <p class="text-body-2 font-weight-bold mb-0">
                        {{ item.seller?.name }}
                    </p>
                    <p class="text-caption text-medium-emphasis mb-0">
                        {{ partySubtitle(item.seller) }}
                    </p>
                </div>
            </template>
            <template #item.is_preorder="{ item, column }">
                <BaseBadge
                    type="transaction_type"
                    :value="item.is_preorder"
                    :align="column.align"
                />
            </template>
            <template #item.total_price="{ item }">
                <div class="text-body-2 font-weight-bold">
                    {{ formatPrice(item.total_price) }}
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
                        v-tooltip:top="'Detail Laporan'"
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

        <OrderDetailDialog
            ref="detailDialogRef"
            :detail-service="partnershipSalesService"
        />
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import OrderDetailDialog from "@/admin/components/transactions/OrderDetailDialog.vue";
import partnershipSalesService from "@/admin/services/partnership-sales.service";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime, formatPrice } = useFormatter();

const dataTable = ref<InstanceType<typeof BaseDataTable> | null>(null);
const detailDialogRef = ref<InstanceType<typeof OrderDetailDialog> | null>(
    null,
);

const headers = [
    {
        title: "Kode Transaksi",
        key: "code",
        type: "text",
        align: "start",
        sortable: false,
        search: true,
        filter: true,
        filterOperator: "like",
        placeholder: "Masukkan Kode Transaksi",
    },
    {
        title: "Tanggal Transaksi",
        key: "datetime",
        type: "date",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
    },
    {
        title: "Penjual",
        key: "seller.name",
        type: "text",
        align: "start",
        sortable: false,
    },
    {
        title: "Nama Penjual",
        key: "seller_name",
        type: "text",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
        filterOperator: "like",
        placeholder: "Masukkan Nama Penjual",
    },
    {
        title: "Kode Penjual",
        key: "seller_code",
        type: "text",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
        filterOperator: "like",
        placeholder: "Masukkan Kode Penjual",
    },
    {
        title: "Peran Penjual",
        key: "seller_type",
        type: "select",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
        options: [
            { value: "distributor", label: "Distributor" },
            { value: "agent", label: "Agent" },
            { value: "reseller", label: "Reseller" },
        ],
        placeholder: "Pilih Peran Penjual",
    },
    {
        title: "Pembeli",
        key: "buyer.name",
        type: "text",
        align: "start",
        sortable: false,
    },
    {
        title: "Nama Pembeli",
        key: "buyer_name",
        type: "text",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
        filterOperator: "like",
        placeholder: "Masukkan Nama Pembeli",
    },
    {
        title: "Kode Pembeli",
        key: "buyer_code",
        type: "text",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
        filterOperator: "like",
        placeholder: "Masukkan Kode Pembeli",
    },
    {
        title: "Peran Pembeli",
        key: "buyer_type",
        type: "select",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
        options: [
            { value: "distributor", label: "Distributor" },
            { value: "agent", label: "Agent" },
            { value: "reseller", label: "Reseller" },
            { value: "customer", label: "Customer" },
        ],
        placeholder: "Pilih Peran Pembeli",
    },
    {
        title: "Tipe",
        key: "is_preorder",
        type: "select",
        align: "center",
        sortable: false,
        filter: true,
        options: [
            { value: 0, label: "Reguler" },
            { value: 1, label: "Pre-order" },
        ],
        placeholder: "Pilih Tipe",
    },
    {
        title: "Total Transaksi (Rp)",
        key: "total_price",
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
                label: "Menunggu Verifikasi",
            },
            { value: "processing", label: "Diproses" },
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

const refreshData = () => {
    dataTable.value?.refresh();
};

const openDetail = (id: string | number) => {
    detailDialogRef.value?.open(id);
};

const partySubtitle = (party: any) => {
    const labels: Record<string, string> = {
        warehouse: "Gudang Pusat",
        distributor: "Distributor",
        agent: "Agent",
        reseller: "Reseller",
        customer: "Pelanggan",
    };

    const role = labels[party?.type] || party?.type || "-";

    return party?.code ? `${role} · ${party.code}` : role;
};
</script>

<style scoped>
.gap-2 {
    gap: 8px;
}
</style>
