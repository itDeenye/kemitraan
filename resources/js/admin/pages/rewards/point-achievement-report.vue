<template>
    <div class="mb-6 d-flex flex-column">
        <h1 class="text-h4 font-weight-bold mb-1">Laporan Pencapaian Poin</h1>
        <p class="text-body-2 text-medium-emphasis">
            Halaman untuk melihat rekapitulasi pencapaian poin mitra per
            periode.
        </p>
    </div>

    <BaseDataTable
        ref="pointAchievementTable"
        url="/admin/rewards/point-achievements"
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
                    <v-select
                        v-model="selectedMonthFrom"
                        :items="months"
                        item-title="label"
                        item-value="value"
                        variant="plain"
                        density="compact"
                        hide-details
                        class="mr-2"
                        style="width: 110px"
                    ></v-select>
                    <v-select
                        v-model="selectedYearFrom"
                        :items="years"
                        variant="plain"
                        density="compact"
                        hide-details
                        style="width: 80px"
                    ></v-select>
                </div>

                <div
                    class="d-flex align-center ga-2 bg-surface pa-1 rounded-lg border"
                >
                    <span class="text-caption font-weight-medium px-2 pt-2"
                        >Sampai:</span
                    >
                    <v-select
                        v-model="selectedMonthTo"
                        :items="months"
                        item-title="label"
                        item-value="value"
                        variant="plain"
                        density="compact"
                        hide-details
                        class="mr-2"
                        style="width: 110px"
                    ></v-select>
                    <v-select
                        v-model="selectedYearTo"
                        :items="years"
                        variant="plain"
                        density="compact"
                        hide-details
                        style="width: 80px"
                    ></v-select>
                </div>
            </div>
        </template>

        <template #item.period="{ item }">
            {{ getMonthName(item.period?.month) }} {{ item.period?.year }}
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

        <template #item.member.level.name="{ item }">
            <div class="d-flex flex-column py-2">
                <BaseBadge
                    type="level"
                    :value="item.member?.level.name"
                    class="mt-1"
                    size="x-small"
                    inline
                />
            </div>
        </template>

        <template #item.points="{ item }">
            <span class="font-weight-bold text-primary">{{
                formatPrice(item.points)
            }}</span>
        </template>

        <template #item.customer_count="{ item }">
            {{ formatPrice(item.customer_count) }}
        </template>

        <template #item.total_spending="{ item }">
            <span class="font-weight-medium">{{
                formatPrice(item.total_spending)
            }}</span>
        </template>

        <template #item.created_at="{ item }">
            {{ formatDateTime(item.created_at) }}
        </template>
    </BaseDataTable>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useDateFilter } from "@/shared/composables/useDateFilter";

const { formatPrice, formatDateTime } = useFormatter();

const {
    selectedYearFrom,
    selectedMonthFrom,
    selectedYearTo,
    selectedMonthTo,
    years,
    months,
    getMonthName,
} = useDateFilter();

const pointAchievementTable = ref<InstanceType<typeof BaseDataTable> | null>(
    null,
);

const extraParams = computed(() => ({
    year_from: selectedYearFrom.value,
    month_from: selectedMonthFrom.value,
    year_to: selectedYearTo.value,
    month_to: selectedMonthTo.value,
}));

const headers = [
    {
        title: "Periode",
        key: "period",
        align: "start",
        sortable: false,
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
        title: "Level",
        key: "member.level.name",
        type: "select",
        align: "center",
        sortable: false,
        filter: true,
        options: [
            { value: "Distributor", label: "Distributor" },
            { value: "Agent", label: "Agen" },
            { value: "Reseller", label: "Reseller" },
        ],
        placeholder: "Pilih Level",
    },
    {
        title: "Total Belanja (Rp)",
        key: "total_spending",
        align: "right",
        sortable: true,
    },
    {
        title: "Jumlah Pelanggan",
        key: "customer_count",
        align: "right",
        sortable: true,
    },
    {
        title: "Poin",
        key: "points",
        align: "right",
        sortable: true,
    },
    {
        title: "Waktu Pencapaian",
        key: "created_at",
        type: "date",
        align: "start",
        sortable: true,
        filter: false,
    },
];
</script>
