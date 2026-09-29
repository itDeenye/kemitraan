<template>
    <div class="mb-6">
        <h1 class="text-h4 font-weight-bold mb-1">Top Up STC</h1>
        <p class="text-body-2 text-medium-emphasis">
            Halaman untuk melihat riwayat top up saldo dan panduan mengisi saldo
            STC.
        </p>
    </div>

    <BaseDataTable
        ref="topupTable"
        url="/admin/stc/top-ups"
        :headers="headers"
        v-model:search="search"
    >
        <template #header-actions>
            <v-btn
                color="primary"
                prepend-icon="mdi-cash-plus"
                variant="outlined"
                class="rounded-lg bg-surface"
                @click="openTopUpDialog"
            >
                Top Up STC
            </v-btn>
        </template>

        <template #item.datetime="{ item }">
            {{ formatDateTime(item.datetime) }}
        </template>

        <template #item.bank_name="{ item }">
            {{ item.bank_name || "-" }}
        </template>

        <!-- <template #item.sender_name="{ item }">
            {{ item.sender_name || "-" }}
        </template> -->

        <template #item.amount="{ item }">
            <span class="font-weight-medium">
                {{ formatPrice(item.amount) }}
            </span>
        </template>

        <template #item.status="{ item }">
            <BaseBadge
                :text="
                    item.status === 'completed'
                        ? 'Berhasil'
                        : item.status === 'pending'
                          ? 'Menunggu'
                          : 'Dibatalkan'
                "
                :color="
                    item.status === 'completed'
                        ? 'success'
                        : item.status === 'pending'
                          ? 'warning'
                          : 'error'
                "
            />
        </template>

        <!-- <template #item.actions="{ item }">
            <div class="d-flex ga-2 justify-center">
                <v-btn
                    v-tooltip:top="'Detail Top Up'"
                    icon="mdi-eye-outline"
                    variant="outlined"
                    size="small"
                    class="rounded"
                    color="primary"
                    @click="viewDetail(item)"
                />
            </div>
        </template> -->
    </BaseDataTable>

    <TopUpInstructionDialog ref="topUpDialog" />
</template>

<script setup lang="ts">
import { ref } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import TopUpInstructionDialog from "@/admin/components/stc/TopUpInstructionDialog.vue";
import type { StcTopUp } from "@/admin/types/stc";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime, formatPrice } = useFormatter();

const search = ref("");
const topupTable = ref<InstanceType<typeof BaseDataTable> | null>(null);
const topUpDialog = ref<InstanceType<typeof TopUpInstructionDialog> | null>(
    null,
);

const headers = [
    {
        title: "Waktu Top Up",
        key: "datetime",
        type: "date",
        align: "start",
        sortable: true,
        filter: true,
    },
    {
        title: "Bank",
        key: "bank_name",
        align: "start",
        sortable: false,
    },
    {
        title: "Nominal (Rp)",
        key: "amount",
        sortable: false,
        align: "right",
    },
    // {
    //     title: "Nama Pengirim",
    //     key: "sender_name",
    //     type: "text",
    //     align: "start",
    //     sortable: false,
    //     filter: true,
    //     placeholder: "Masukkan Nama Pengirim",
    // },
    {
        title: "Status",
        key: "status",
        type: "select",
        align: "center",
        sortable: false,
        filter: true,
        options: [
            { value: "completed", label: "Berhasil" },
            { value: "pending", label: "Menunggu" },
            { value: "cancelled", label: "Dibatalkan" },
        ],
        placeholder: "Pilih Status",
    },
    // {
    //     title: "Aksi",
    //     key: "actions",
    //     sortable: false,
    //     align: "center",
    // },
];

const openTopUpDialog = () => {
    topUpDialog.value?.open();
};

const viewDetail = (item: StcTopUp) => {
    // jika udah ada api detail
    console.log("View topup detail:", item);
};
</script>
