<template>
    <v-dialog v-model="dialog" max-width="500" scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28">mdi-medal-outline</v-icon>
                    <span class="text-h6 font-weight-bold"
                        >Detail Poin Tahunan</span
                    >
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    @click="close"
                ></v-btn>
            </v-card-title>
            <v-divider></v-divider>

            <v-card-text class="px-6 py-4">
                <div v-if="loading" class="d-flex justify-center py-8">
                    <v-progress-circular
                        indeterminate
                        color="primary"
                    ></v-progress-circular>
                </div>
                <template v-else-if="reward">
                    <div class="d-flex align-center ga-4 mb-6">
                        <v-avatar color="primary" variant="tonal" size="56">
                            <span
                                class="text-h6 font-weight-bold text-primary"
                                >{{ reward.member.name.charAt(0) }}</span
                            >
                        </v-avatar>
                        <div>
                            <div class="text-h6 font-weight-bold mb-1">
                                {{ reward.member.name }}
                            </div>
                            <div class="d-flex align-center ga-2">
                                <BaseBadge
                                    type="level"
                                    :value="reward.member.level.name"
                                    inline
                                />
                                <span
                                    class="text-caption text-medium-emphasis"
                                    >{{ reward.member.code }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-lg px-4 py-2 mb-6 text-center border border-primary"
                    >
                        <div class="text-caption text-medium-emphasis mb-1">
                            Total Poin Tahun {{ reward.year }}
                        </div>
                        <div class="text-h4 font-weight-bold text-primary">
                            {{ formatPrice(reward.total_points) }}
                        </div>
                    </div>

                    <h3 class="text-subtitle-1 font-weight-bold mb-3">
                        Rincian Per Bulan
                    </h3>
                    <v-table density="compact" class="border rounded-lg">
                        <thead>
                            <tr>
                                <th class="text-left font-weight-bold">
                                    Bulan
                                </th>
                                <th class="text-right font-weight-bold">
                                    Poin
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!reward.months?.length">
                                <td
                                    colspan="2"
                                    class="text-center text-medium-emphasis pa-4"
                                >
                                    Belum ada histori poin
                                </td>
                            </tr>
                            <tr
                                v-for="month in reward.months"
                                :key="month.month"
                            >
                                <td>{{ getMonthName(month.month) }}</td>
                                <td class="text-right font-weight-medium">
                                    {{ formatPrice(month.total_points) }}
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                </template>
            </v-card-text>
            <v-divider></v-divider>

            <v-card-actions class="pa-6 pt-4 border-t bg-surface">
                <v-spacer></v-spacer>
                <v-btn
                    variant="flat"
                    color="primary"
                    class="text-none px-6"
                    @click="close"
                >
                    Tutup
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import type { AnnualReward } from "@/admin/types/reward";
import rewardService from "@/admin/services/reward.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import BaseBadge from "@/shared/components/BaseBadge.vue";

const { formatPrice } = useFormatter();
const snackbar = useSnackbarStore();

const dialog = ref(false);
const loading = ref(false);
const reward = ref<AnnualReward | null>(null);

const months = [
    { value: 1, label: "Januari" },
    { value: 2, label: "Februari" },
    { value: 3, label: "Maret" },
    { value: 4, label: "April" },
    { value: 5, label: "Mei" },
    { value: 6, label: "Juni" },
    { value: 7, label: "Juli" },
    { value: 8, label: "Agustus" },
    { value: 9, label: "September" },
    { value: 10, label: "Oktober" },
    { value: 11, label: "November" },
    { value: 12, label: "Desember" },
];

const getMonthName = (month: number) => {
    return months.find((m) => m.value === month)?.label || "";
};

const open = async (id: number) => {
    dialog.value = true;
    loading.value = true;
    reward.value = null;

    try {
        reward.value = await rewardService.getAnnualDetail(id);
    } catch (e: any) {
        console.error(e);
        snackbar.showMessage("Gagal mengambil detail reward tahunan", "error");
        dialog.value = false;
    } finally {
        loading.value = false;
    }
};

const close = () => {
    dialog.value = false;
};

defineExpose({
    open,
    close,
});
</script>
