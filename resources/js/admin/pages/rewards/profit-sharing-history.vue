<template>
    <div class="mb-6 d-flex flex-column">
        <h1 class="text-h4 font-weight-bold mb-1">Riwayat Sharing Profit</h1>
        <p class="text-body-2 text-medium-emphasis">
            Halaman untuk melihat riwayat pembagian sharing profit kepada mitra
            yang sudah ditransfer.
        </p>
    </div>

    <BaseDataTable
        ref="historyTable"
        url="/admin/rewards/sharing-profits/history"
        :headers="headers"
    >
        <template #item.trx_code="{ item }">
            <div class="d-flex flex-column py-2">
                <div class="text-body-2 font-weight-bold">
                    {{ item.trx_code }}
                </div>
                <div class="text-caption text-medium-emphasis">
                    {{ formatDateTime(item.paid_datetime) }}
                </div>
            </div>
        </template>
        <template #item.mitra_name="{ item }">
            <div class="font-weight-medium text-body-2 py-2">
                {{ item.mitra_name || "-" }}
            </div>
        </template>

        <template #item.trx_price="{ item }">
            {{ formatPrice(item.trx_price) }}
        </template>

        <template #item.amount="{ item }">
            <span class="font-weight-bold">{{ formatPrice(item.amount) }}</span>
        </template>

        <template #item.status="{ item }">
            <BaseBadge
                type="sharing_reward_status"
                :value="item.status"
                inline
            />
        </template>

        <template #item.paid_datetime="{ item }">
            {{ item.paid_datetime ? formatDateTime(item.paid_datetime) : "-" }}
        </template>

        <template #item.actions="{ item }">
            <div class="d-flex ga-2 justify-center">
                <v-btn
                    v-tooltip:top="'Detail Riwayat'"
                    icon="mdi-eye-outline"
                    variant="outlined"
                    size="small"
                    class="rounded"
                    color="primary"
                    @click="viewDetail(item)"
                />
            </div>
        </template>
    </BaseDataTable>

    <SharingProfitHistoryDialog ref="detailDialogRef" />
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import SharingProfitHistoryDialog from "@/admin/components/rewards/SharingProfitHistoryDialog.vue";

const { formatPrice, formatDateTime } = useFormatter();

const historyTable = ref<InstanceType<typeof BaseDataTable> | null>(null);
const detailDialogRef = ref<InstanceType<
    typeof SharingProfitHistoryDialog
> | null>(null);

const headers = [
    {
        title: "Waktu Transfer",
        key: "paid_datetime",
        type: "date",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
    },
    {
        title: "Kode Transaksi",
        key: "trx_code",
        type: "text",
        align: "start",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Transaksi",
    },
    {
        title: "Mitra",
        key: "mitra_name",
        type: "text",
        align: "start",
        sortable: false,
        filter: true,
        filterLabel: "Nama Mitra",
        placeholder: "Masukkan Nama Mitra",
    },
    {
        title: "Nilai Transaksi (Rp)",
        key: "trx_price",
        align: "right",
        sortable: false,
    },
    {
        title: "Komisi (Rp)",
        key: "amount",
        align: "right",
        sortable: false,
    },
    {
        title: "Status",
        key: "status",
        align: "center",
        sortable: false,
    },
    {
        title: "Aksi",
        key: "actions",
        align: "center",
        sortable: false,
    },
];

const viewDetail = (item: any) => {
    if (detailDialogRef.value) {
        detailDialogRef.value.open(item.id);
    }
};
</script>
