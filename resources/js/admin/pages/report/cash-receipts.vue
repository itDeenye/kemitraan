<template>
    <div class="mb-6 d-flex flex-column">
        <h1 class="text-h4 font-weight-bold mb-1">Laporan Uang Masuk</h1>
        <p class="text-body-2 text-medium-emphasis">
            Halaman untuk melihat laporan uang masuk dari transaksi penjualan.
        </p>
    </div>

    <BaseDataTable
        ref="cashReceiptsTable"
        url="/admin/reports/cash-income"
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

        <template #item.transaction.code="{ item }">
            <div class="d-flex flex-column py-2">
                <div class="text-body-2 font-weight-bold">
                    {{ item.transaction.code }}
                </div>
                <div class="text-caption text-medium-emphasis">
                    {{ formatDateTime(item.transferred_at) }}
                </div>
            </div>
        </template>

        <template #item.buyer.name="{ item }">
            <div class="d-flex flex-column py-2">
                <div class="font-weight-medium text-body-2">
                    {{ item.buyer?.name }}
                </div>
                <div class="text-caption text-medium-emphasis">
                    {{ item.buyer?.code || "-" }}
                </div>
                <BaseBadge
                    type="level"
                    :value="item.buyer?.type"
                    class="mt-1"
                    size="x-small"
                />
            </div>
        </template>

        <template #item.payment.bank.account_name="{ item }">
            <div class="d-flex flex-column py-2">
                <div class="text-body-2 font-weight-bold">
                    {{ item.payment.bank.name || "-" }}
                </div>
                <div class="text-body-2 font-weight-medium">
                    {{ item.payment.bank.account_name || "-" }}
                </div>
                <div class="text-caption text-medium-emphasis">
                    {{ item.payment.bank.account_number || "-" }}
                </div>
            </div>
        </template>

        <template #item.amount="{ item }">
            <span class="font-weight-bold">{{ formatPrice(item.amount) }}</span>
        </template>

        <template #item.status="{ item }">
            <BaseBadge type="payment_status" :value="item.status" inline />
        </template>
    </BaseDataTable>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useDateRange } from "@/shared/composables/useDateRange";

const { formatPrice, formatDateTime } = useFormatter();

const { dateFrom, dateTo } = useDateRange();

const cashReceiptsTable = ref<InstanceType<typeof BaseDataTable> | null>(null);

const extraParams = computed(() => ({
    date_from: dateFrom.value,
    date_to: dateTo.value,
}));

const headers = [
    {
        title: "Kode Transaksi",
        key: "transaction.code",
        type: "text",
        align: "start",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Transaksi",
    },
    {
        title: "Tanggal Transfer",
        key: "transferred_at",
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
        title: "Pembayaran Ke",
        key: "payment.bank.account_name",
        align: "start",
        sortable: false,
    },
    {
        title: "Nominal (Rp)",
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
];
</script>
