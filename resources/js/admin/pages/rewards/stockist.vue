<template>
    <div class="mb-6 d-flex flex-column">
        <h1 class="text-h4 font-weight-bold mb-1">Reward Stokis</h1>
        <p class="text-body-2 text-medium-emphasis">
            Halaman untuk memantau persentase reward (2,5%) khusus Distributor
            Utama berstatus Stokis.
        </p>
    </div>

    <BaseDataTable
        ref="stockistTable"
        url="/admin/rewards/stockists"
        :headers="headers"
        :query-params="extraParams"
    >
        <template #header-actions>
            <div class="d-flex align-center ga-3">
                <v-select
                    v-model="selectedMonth"
                    :items="months"
                    item-title="label"
                    item-value="value"
                    variant="outlined"
                    density="compact"
                    hide-details
                    class="bg-surface rounded-lg"
                    label="Bulan"
                ></v-select>
                <v-select
                    v-model="selectedYear"
                    :items="years"
                    variant="outlined"
                    density="compact"
                    hide-details
                    class="bg-surface rounded-lg"
                    label="Tahun"
                ></v-select>
            </div>
        </template>

        <template #item.period="{ item }">
            {{ getMonthName(item.month) }} {{ item.year }}
        </template>

        <template #item.member.name="{ item }">
            <div class="d-flex flex-column py-2">
                <div class="font-weight-medium text-body-2">
                    {{ item.member?.name || "-" }}
                </div>
                <div class="text-caption text-medium-emphasis">
                    {{ item.member?.code || "-" }}
                </div>
            </div>
        </template>

        <template #item.total_spending="{ item }">
            <span class="font-weight-medium">{{
                formatPrice(item.total_spending)
            }}</span>
        </template>

        <template #item.voucher_value="{ item }">
            <span class="font-weight-bold">{{
                formatPrice(item.voucher_value)
            }}</span>
        </template>

        <template #item.used_value="{ item }">
            {{ formatPrice(item.used_value) }}
        </template>

        <template #item.remaining_value="{ item }">
            <span class="font-weight-bold">{{
                formatPrice(item.remaining_value)
            }}</span>
        </template>

        <template #item.expiry_date="{ item }">
            {{ item.expiry_date ? formatDateTime(item.expiry_date) : "-" }}
        </template>

        <template #item.status="{ item }">
            <BaseBadge
                type="stockist_reward_status"
                :value="item.status"
                inline
            />
        </template>

        <template #item.actions="{ item }">
            <div class="d-flex ga-2 justify-center">
                <v-btn
                    v-tooltip:top="'Detail Reward'"
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

    <StockistRewardDialog ref="detailDialog" />
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import type { StockistReward } from "@/admin/types/reward";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import StockistRewardDialog from "@/admin/components/rewards/StockistRewardDialog.vue";
import { useDateFilter } from "@/shared/composables/useDateFilter";

const { formatPrice, formatDateTime } = useFormatter();

const { selectedYear, selectedMonth, years, months, getMonthName } =
    useDateFilter();

const stockistTable = ref<InstanceType<typeof BaseDataTable> | null>(null);

const extraParams = computed(() => ({
    "filter[year]": selectedYear.value,
    "filter[month]": selectedMonth.value,
}));

const headers = [
    {
        title: "Periode",
        key: "period",
        align: "start",
        sortable: false,
    },
    {
        title: "Mitra Stokis",
        key: "member.name",
        type: "text",
        align: "start",
        sortable: false,
        filter: true,
        filterLabel: "Nama Mitra Stokis",
        placeholder: "Masukkan Nama Mitra Stokis",
    },
    {
        title: "Kode Mitra",
        key: "member.code",
        type: "text",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
        filterLabel: "Kode Mitra Stokis",
        placeholder: "Masukkan Kode Mitra Stokis",
    },
    {
        title: "Total Belanja (Rp)",
        key: "total_spending",
        align: "right",
        sortable: true,
    },
    {
        title: "Nilai Voucher (Rp)",
        key: "voucher_value",
        align: "right",
        sortable: true,
    },
    {
        title: "Terpakai (Rp)",
        key: "used_value",
        align: "right",
        sortable: false,
    },
    {
        title: "Sisa (Rp)",
        key: "remaining_value",
        align: "right",
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
            { value: "available", label: "Tersedia" },
            { value: "used", label: "Terpakai" },
            { value: "expired", label: "Kadaluarsa" },
        ],
        placeholder: "Pilih Status",
    },
    {
        title: "Aksi",
        key: "actions",
        sortable: false,
        align: "center",
    },
];

const detailDialog = ref<InstanceType<typeof StockistRewardDialog> | null>(
    null,
);

const viewDetail = (item: StockistReward) => {
    detailDialog.value?.open(item.id);
};
</script>
