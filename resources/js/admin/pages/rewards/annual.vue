<template>
    <div class="mb-6 d-flex flex-column">
        <h1 class="text-h4 font-weight-bold mb-1">Reward Tahunan</h1>
        <p class="text-body-2 text-medium-emphasis">
            Halaman untuk memantau akumulasi poin tahunan mitra.
        </p>
    </div>

    <BaseDataTable
        ref="annualTable"
        url="/admin/rewards/annual"
        :headers="headers"
        :query-params="extraParams"
    >
        <template #header-actions>
            <div class="d-flex align-center ga-3">
                <v-select
                    v-model="selectedYear"
                    :items="years"
                    variant="outlined"
                    density="compact"
                    hide-details
                    style="width: 120px"
                    class="bg-surface rounded-lg"
                    label="Tahun"
                ></v-select>
            </div>
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

        <template #item.total_points="{ item }">
            <span class="font-weight-bold">{{
                formatPrice(item.total_points)
            }}</span>
        </template>

        <template #item.updated_at="{ item }">
            {{ formatDateTime(item.updated_at) }}
        </template>

        <template #item.actions="{ item }">
            <div class="d-flex ga-2 justify-center">
                <v-btn
                    v-tooltip:top="'Detail Poin'"
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

    <AnnualRewardDialog ref="detailDialog" />
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import type { AnnualReward } from "@/admin/types/reward";
import rewardService from "@/admin/services/reward.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import AnnualRewardDialog from "@/admin/components/rewards/AnnualRewardDialog.vue";
import { useDateFilter } from "@/shared/composables/useDateFilter";

const { formatPrice, formatDateTime } = useFormatter();
const snackbar = useSnackbarStore();

const { selectedYear, years } = useDateFilter();

const annualTable = ref<InstanceType<typeof BaseDataTable> | null>(null);

const extraParams = computed(() => ({
    "filter[year]": selectedYear.value,
}));

const headers = [
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
        title: "Total Poin",
        key: "total_points",
        align: "right",
        sortable: true,
    },
    {
        title: "Terakhir Diperbarui",
        key: "updated_at",
        type: "date",
        align: "start",
        sortable: true,
        filter: true,
    },
    {
        title: "Aksi",
        key: "actions",
        sortable: false,
        align: "center",
    },
];

const detailDialog = ref<InstanceType<typeof AnnualRewardDialog> | null>(null);

const viewDetail = (item: AnnualReward) => {
    detailDialog.value?.open(item.id);
};
</script>
