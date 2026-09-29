<template>
    <v-dialog v-model="dialog" max-width="500" scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28"
                        >mdi-information-outline</v-icon
                    >
                    <span class="text-h6 font-weight-bold"
                        >Detail Reward Stokis</span
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
                        <div
                            class="d-flex align-center justify-space-between mb-2"
                        >
                            <span class="text-body-2 text-medium-emphasis"
                                >Periode</span
                            >
                            <span class="font-weight-medium"
                                >{{ getMonthName(reward.month) }}
                                {{ reward.year }}</span
                            >
                        </div>
                        <v-divider class="my-2"></v-divider>
                        <div
                            class="d-flex align-center justify-space-between mb-2"
                        >
                            <span class="text-body-2 text-medium-emphasis"
                                >Total Belanja</span
                            >
                            <span class="font-weight-medium"
                                >Rp{{
                                    formatPrice(reward.total_spending)
                                }}</span
                            >
                        </div>
                        <v-divider class="my-2"></v-divider>
                        <div
                            class="d-flex align-center justify-space-between mb-2"
                        >
                            <span class="text-body-2 text-medium-emphasis"
                                >Status Voucher</span
                            >
                            <BaseBadge
                                type="stockist_reward_status"
                                :value="reward.status"
                                inline
                            />
                        </div>
                        <v-divider class="my-2"></v-divider>
                        <div class="d-flex align-center justify-space-between">
                            <span class="text-body-2 text-medium-emphasis"
                                >Tgl Kadaluarsa</span
                            >
                            <span class="text-caption font-weight-medium">{{
                                reward.expiry_date
                                    ? formatDateTime(reward.expiry_date)
                                    : "-"
                            }}</span>
                        </div>
                    </div>

                    <div
                        class="bg-primary-lighten-5 border-primary border rounded-lg pa-4 mb-4 text-center"
                    >
                        <div
                            class="text-caption text-primary font-weight-medium mb-1"
                        >
                            Total Nilai Voucher (2,5%)
                        </div>
                        <div class="text-h4 font-weight-bold text-primary">
                            Rp{{ formatPrice(reward.voucher_value) }}
                        </div>
                    </div>

                    <div class="d-flex ga-4">
                        <div
                            class="flex-1-1-100 bg-grey-lighten-4 rounded-lg pa-3 text-center border"
                        >
                            <div class="text-caption text-medium-emphasis mb-1">
                                Terpakai
                            </div>
                            <div class="text-body-1 font-weight-bold">
                                Rp{{ formatPrice(reward.used_value) }}
                            </div>
                        </div>
                        <div
                            class="flex-1-1-100 bg-grey-lighten-4 rounded-lg pa-3 text-center border"
                        >
                            <div class="text-caption text-medium-emphasis mb-1">
                                Sisa
                            </div>
                            <div
                                class="text-body-1 font-weight-bold text-success"
                            >
                                Rp{{ formatPrice(reward.remaining_value) }}
                            </div>
                        </div>
                    </div>
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
import type { StockistReward } from "@/admin/types/reward";
import rewardService from "@/admin/services/reward.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useDateFilter } from "@/shared/composables/useDateFilter";

const { formatPrice, formatDateTime } = useFormatter();
const snackbar = useSnackbarStore();

const { getMonthName } = useDateFilter();

const dialog = ref(false);
const loading = ref(false);
const reward = ref<StockistReward | null>(null);

const open = async (id: number) => {
    dialog.value = true;
    loading.value = true;
    reward.value = null;

    try {
        reward.value = await rewardService.getStockistDetail(id);
    } catch (e: any) {
        console.error(e);
        snackbar.showMessage("Gagal mengambil detail reward", "error");
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
