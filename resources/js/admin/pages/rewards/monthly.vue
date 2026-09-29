<template>
    <div class="mb-6 d-flex flex-column">
        <h1 class="text-h4 font-weight-bold mb-1">Reward Bulanan</h1>
        <p class="text-body-2 text-medium-emphasis">
            Halaman untuk memantau pencapaian poin dan memproses reward bulanan
            mitra.
        </p>
    </div>

    <BaseDataTable
        ref="monthlyTable"
        url="/admin/rewards/monthly"
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
            <span class="font-weight-medium">{{
                formatPrice(item.total_points)
            }}</span>
        </template>

        <template #item.point_value="{ item }">
            {{ formatPrice(item.point_value) }}
        </template>

        <template #item.reward_value="{ item }">
            <span class="font-weight-bold">{{
                formatPrice(item.reward_value)
            }}</span>
        </template>

        <template #item.is_processed="{ item }">
            <BaseBadge
                :text="item.is_processed ? 'Sudah Ditransfer' : 'Menunggu'"
                :color="item.is_processed ? 'success' : 'warning'"
                inline
            />
        </template>

        <template #item.processed_at="{ item }">
            {{ item.processed_at ? formatDateTime(item.processed_at) : "-" }}
        </template>

        <template #item.actions="{ item }">
            <div class="d-flex ga-2 justify-center">
                <v-btn
                    v-if="!item.is_processed"
                    v-tooltip:top="'Proses Reward'"
                    icon="mdi-check-circle-outline"
                    variant="outlined"
                    size="small"
                    class="rounded"
                    color="success"
                    @click="openProcessConfirm(item)"
                />
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

    <BaseConfirm ref="confirmDialog" />

    <MonthlyRewardDialog ref="detailDialog" />
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import type { MonthlyReward } from "@/admin/types/reward";
import rewardService from "@/admin/services/reward.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import BaseConfirm from "@/shared/components/BaseConfirm.vue";
import MonthlyRewardDialog from "@/admin/components/rewards/MonthlyRewardDialog.vue";
import { useDateFilter } from "@/shared/composables/useDateFilter";

const { formatPrice, formatDateTime } = useFormatter();
const snackbar = useSnackbarStore();

const { selectedYear, selectedMonth, years, months, getMonthName } =
    useDateFilter();

const monthlyTable = ref<InstanceType<typeof BaseDataTable> | null>(null);

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
        title: "Poin",
        key: "total_points",
        align: "right",
        sortable: true,
    },
    {
        title: "Nilai Poin (Rp)",
        key: "point_value",
        align: "right",
        sortable: false,
    },
    {
        title: "Total Reward (Rp)",
        key: "reward_value",
        align: "right",
        sortable: true,
    },
    {
        title: "Status",
        key: "is_processed",
        type: "select",
        align: "center",
        sortable: false,
        filter: true,
        options: [
            { value: "1", label: "Diproses" },
            { value: "0", label: "Menunggu" },
        ],
        placeholder: "Pilih Status",
    },
    {
        title: "Waktu Proses",
        key: "processed_at",
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

// Process logic
const confirmDialog = ref<InstanceType<typeof BaseConfirm> | null>(null);
const processing = ref(false);
const selectedItem = ref<MonthlyReward | null>(null);

const openProcessConfirm = async (item: MonthlyReward) => {
    selectedItem.value = item;
    const isConfirmed = await confirmDialog.value?.open({
        title: "Proses Reward",
        message: `Apakah Anda yakin ingin memproses reward bulanan untuk mitra ${item.member?.name} sebesar Rp${formatPrice(item.reward_value || 0)}?`,
        confirmText: "Proses",
        confirmColor: "success",
    });

    if (isConfirmed) {
        processReward();
    } else {
        selectedItem.value = null;
    }
};

const processReward = async () => {
    if (!selectedItem.value) return;

    processing.value = true;
    try {
        await rewardService.processMonthly(selectedItem.value.id);
        snackbar.showMessage("Reward bulanan berhasil diproses", "success");
        monthlyTable.value?.refresh();
    } catch (e: any) {
        console.error(e);
        snackbar.showMessage(
            e.response?.data?.message || "Gagal memproses reward",
            "error",
        );
    } finally {
        processing.value = false;
        selectedItem.value = null;
    }
};

const detailDialog = ref<InstanceType<typeof MonthlyRewardDialog> | null>(null);

const viewDetail = (item: MonthlyReward) => {
    detailDialog.value?.open(item.id);
};
</script>
