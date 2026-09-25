<template>
    <v-dialog v-model="isOpen" max-width="800" scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-avatar color="primary" variant="tonal" size="48">
                        <v-icon>mdi-cash-multiple</v-icon>
                    </v-avatar>
                    <div>
                        <div class="text-h6 font-weight-bold">
                            Detail Riwayat Transfer
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ historyDetail?.trx_code || "Memuat..." }}
                        </div>
                    </div>
                </div>
                <div class="d-flex align-center ga-2">
                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        size="small"
                        @click="close"
                    />
                </div>
            </v-card-title>

            <v-card-text class="pa-4" style="max-height: 70vh">
                <div v-if="isLoading" class="d-flex justify-center py-12">
                    <v-progress-circular
                        indeterminate
                        color="primary"
                        size="48"
                        width="4"
                    />
                </div>
                <div
                    v-else-if="!historyDetail"
                    class="text-center py-12 text-medium-emphasis"
                >
                    <v-icon size="48" class="mb-2"
                        >mdi-alert-circle-outline</v-icon
                    >
                    <div>Gagal memuat detail riwayat</div>
                </div>
                <div v-else class="d-flex flex-column ga-4">
                    <div
                        class="d-flex align-center justify-space-between flex-wrap ga-4"
                    >
                        <div class="d-flex align-center ga-4">
                            <div>
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    Status
                                </div>
                                <BaseBadge
                                    type="sharing_reward_status"
                                    :value="historyDetail.status"
                                />
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-caption text-medium-emphasis mb-1">
                                Waktu Transfer
                            </div>
                            <div class="text-body-2 font-weight-medium">
                                {{
                                    historyDetail.paid_datetime
                                        ? formatDateTime(
                                              historyDetail.paid_datetime,
                                          )
                                        : "-"
                                }}
                            </div>
                        </div>
                    </div>

                    <v-row>
                        <v-col cols="12" sm="6">
                            <v-card
                                variant="outlined"
                                class="rounded-lg h-100 pa-4 bg-surface"
                            >
                                <h3
                                    class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center ga-2 text-primary"
                                >
                                    <v-icon size="small"
                                        >mdi-account-outline</v-icon
                                    >
                                    Mitra
                                </h3>
                                <div
                                    class="text-body-2 font-weight-medium mb-1"
                                >
                                    {{ historyDetail.mitra_name || "-" }}
                                </div>
                            </v-card>
                        </v-col>
                        <v-col cols="12" sm="6">
                            <v-card
                                variant="outlined"
                                class="rounded-lg h-100 pa-4 bg-surface"
                            >
                                <h3
                                    class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center ga-2 text-info"
                                >
                                    <v-icon size="small"
                                        >mdi-file-document-outline</v-icon
                                    >
                                    Transaksi Terkait
                                </h3>
                                <div
                                    class="text-body-2 font-weight-medium mb-1"
                                >
                                    {{ historyDetail.trx_code || "-" }}
                                </div>
                            </v-card>
                        </v-col>
                    </v-row>

                    <v-card
                        variant="outlined"
                        class="rounded-lg pa-0 bg-surface"
                    >
                        <v-table density="comfortable">
                            <tbody>
                                <tr>
                                    <td class="text-medium-emphasis">
                                        Nilai Transaksi
                                    </td>
                                    <td class="text-right font-weight-medium">
                                        Rp{{
                                            formatPrice(historyDetail.trx_price)
                                        }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-medium-emphasis">
                                        Komisi Dicairkan
                                    </td>
                                    <td
                                        class="text-right font-weight-bold text-h6"
                                    >
                                        Rp{{
                                            formatPrice(historyDetail.amount)
                                        }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-medium-emphasis">
                                        Bukti Bayar
                                    </td>
                                    <td class="text-right h-100">
                                        <div class="d-flex justify-end py-2">
                                            <v-img
                                                v-if="historyDetail.receipt_url"
                                                :src="historyDetail.receipt_url"
                                                max-width="120"
                                                height="120"
                                                contain
                                                class="rounded cursor-pointer ml-auto"
                                                @click="
                                                    openImagePreview(
                                                        historyDetail.receipt_url,
                                                    )
                                                "
                                            >
                                                <template v-slot:placeholder>
                                                    <div
                                                        class="d-flex align-center justify-center fill-height"
                                                    >
                                                        <v-progress-circular
                                                            indeterminate
                                                            color="primary"
                                                            size="24"
                                                        ></v-progress-circular>
                                                    </div>
                                                </template>
                                            </v-img>
                                            <span
                                                v-else
                                                class="text-caption text-medium-emphasis"
                                                >-</span
                                            >
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>

                    <v-card
                        variant="outlined"
                        class="rounded-lg pa-4 bg-surface"
                    >
                        <h3 class="text-subtitle-2 font-weight-bold mb-2">
                            Catatan Transfer
                        </h3>
                        <p class="text-body-2 text-medium-emphasis mb-0">
                            {{ historyDetail.note || "Tidak ada catatan." }}
                        </p>
                    </v-card>
                </div>
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

    <v-dialog v-model="imagePreview" max-width="800">
        <v-card class="rounded-xl overflow-hidden bg-black">
            <v-toolbar
                color="transparent"
                class="position-absolute w-100"
                style="z-index: 1"
            >
                <v-spacer></v-spacer>
                <v-btn
                    icon="mdi-close"
                    variant="tonal"
                    color="white"
                    class="ma-2 bg-black opacity-70"
                    @click="imagePreview = false"
                ></v-btn>
            </v-toolbar>
            <v-img
                :src="previewImageUrl"
                class="bg-grey-darken-4"
                max-height="90vh"
            ></v-img>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import sharingProfitService from "@/admin/services/sharing-profit.service";
import type { SharingProfitHistory } from "@/admin/types/sharing-profit";
import BaseBadge from "@/shared/components/BaseBadge.vue";

const { formatDateTime, formatPrice } = useFormatter();
const snackbar = useSnackbarStore();

const isOpen = ref(false);
const isLoading = ref(false);
const historyDetail = ref<SharingProfitHistory | null>(null);

const imagePreview = ref(false);
const previewImageUrl = ref("");

const open = async (id: number) => {
    isOpen.value = true;
    isLoading.value = true;
    historyDetail.value = null;

    try {
        const detail = await sharingProfitService.getHistoryDetail(id);
        historyDetail.value = detail;
    } catch (error) {
        snackbar.showMessage("Gagal memuat detail riwayat.", "error");
        isOpen.value = false;
    } finally {
        isLoading.value = false;
    }
};

const close = () => {
    isOpen.value = false;
    historyDetail.value = null;
};

const openImagePreview = (url: string) => {
    previewImageUrl.value = url;
    imagePreview.value = true;
};

defineExpose({ open, close });
</script>
