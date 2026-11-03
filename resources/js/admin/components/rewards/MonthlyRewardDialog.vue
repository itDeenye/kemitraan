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
                        >Detail Reward Bulanan</span
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
                        class="rounded-lg px-4 py-2 mb-4 text-center border border-primary"
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
                                >Total Poin</span
                            >
                            <span class="font-weight-medium">{{
                                formatPrice(reward.total_points)
                            }}</span>
                        </div>
                        <v-divider class="my-2"></v-divider>
                        <div
                            class="d-flex align-center justify-space-between mb-2"
                        >
                            <span class="text-body-2 text-medium-emphasis"
                                >Nilai Poin</span
                            >
                            <span class="font-weight-medium"
                                >Rp{{ formatPrice(reward.point_value) }}</span
                            >
                        </div>
                        <v-divider class="my-2"></v-divider>
                        <div
                            class="d-flex align-center justify-space-between mb-2"
                        >
                            <span class="text-body-2 text-medium-emphasis"
                                >Status</span
                            >
                            <BaseBadge
                                :text="
                                    reward.is_processed
                                        ? 'Sudah Ditransfer'
                                        : 'Menunggu'
                                "
                                :color="
                                    reward.is_processed ? 'success' : 'warning'
                                "
                                inline
                            />
                        </div>
                        <template v-if="reward.is_processed">
                            <v-divider class="my-2"></v-divider>
                            <div
                                class="d-flex align-center justify-space-between"
                            >
                                <span class="text-body-2 text-medium-emphasis"
                                    >Waktu Proses</span
                                >
                                <span class="text-caption font-weight-medium">{{
                                    reward.processed_at
                                        ? formatDateTime(reward.processed_at)
                                        : "-"
                                }}</span>
                            </div>
                        </template>
                    </div>

                    <!-- Informasi Rekening Bank -->
                    <div
                        class="rounded-lg px-4 py-3 mb-4 bg-grey-lighten-4 border"
                    >
                        <div
                            class="d-flex align-center justify-space-between mb-2"
                        >
                            <div
                                class="d-flex align-center ga-2 font-weight-bold text-caption text-uppercase text-medium-emphasis"
                            >
                                <v-icon size="16">mdi-bank</v-icon>
                                <span>Rekening Bank</span>
                            </div>
                            <v-btn
                                v-if="
                                    reward.bank?.account_number &&
                                    reward.bank.account_number !== '-'
                                "
                                size="x-small"
                                variant="text"
                                color="primary"
                                class="text-caption font-weight-medium px-1"
                                prepend-icon="mdi-content-copy"
                                @click="
                                    copyAccountNumber(
                                        reward.bank.account_number,
                                    )
                                "
                            >
                                Salin
                            </v-btn>
                        </div>
                        <template
                            v-if="
                                reward.bank &&
                                reward.bank.account_number &&
                                reward.bank.account_number !== '-'
                            "
                        >
                            <div
                                class="d-flex align-center justify-space-between mb-1"
                            >
                                <span class="text-body-2 text-medium-emphasis"
                                    >Bank</span
                                >
                                <span class="font-weight-medium text-body-2">{{
                                    reward.bank.name || reward.bank.code
                                }}</span>
                            </div>
                            <div
                                class="d-flex align-center justify-space-between mb-1"
                            >
                                <span class="text-body-2 text-medium-emphasis"
                                    >Nomor Rekening</span
                                >
                                <span
                                    class="font-weight-bold text-body-2 font-mono"
                                    >{{ reward.bank.account_number }}</span
                                >
                            </div>
                            <div
                                class="d-flex align-center justify-space-between"
                            >
                                <span class="text-body-2 text-medium-emphasis"
                                    >Atas Nama</span
                                >
                                <span class="font-weight-medium text-body-2">{{
                                    reward.bank.account_name
                                }}</span>
                            </div>
                        </template>
                        <div
                            v-else
                            class="text-caption text-medium-emphasis py-1 text-center"
                        >
                            Rekening bank belum diatur oleh mitra
                        </div>
                    </div>

                    <div
                        class="bg-primary-lighten-5 border-primary border rounded-lg pa-4 text-center"
                    >
                        <div
                            class="text-caption text-primary font-weight-medium mb-1"
                        >
                            Total Reward Didapat
                        </div>
                        <div class="text-h4 font-weight-bold text-primary">
                            Rp{{ formatPrice(reward.reward_value) }}
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
import type { MonthlyReward } from "@/admin/types/reward";
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
const reward = ref<MonthlyReward | null>(null);

const open = async (id: number) => {
    dialog.value = true;
    loading.value = true;
    reward.value = null;

    try {
        reward.value = await rewardService.getMonthlyDetail(id);
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

const copyAccountNumber = async (num: string) => {
    try {
        await navigator.clipboard.writeText(num);
        snackbar.showMessage("Nomor rekening berhasil disalin", "success");
    } catch {
        snackbar.showMessage("Gagal menyalin nomor rekening", "error");
    }
};

defineExpose({
    open,
    close,
});
</script>
