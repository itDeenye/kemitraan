<template>
    <v-dialog v-model="dialog" max-width="800" scrollable>
        <v-card class="rounded-xl elevation-10">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28"
                        >mdi-file-document-outline</v-icon
                    >
                    <span class="text-h6 font-weight-bold text-primary">
                        Detail Pengajuan Registrasi
                    </span>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    color="medium-emphasis"
                    @click="close"
                    density="comfortable"
                ></v-btn>
            </v-card-title>

            <v-divider></v-divider>

            <v-card-text class="pa-6 pt-4" style="max-height: 75vh">
                <div v-if="loading" class="d-flex justify-center py-12">
                    <v-progress-circular
                        indeterminate
                        color="primary"
                        size="48"
                    ></v-progress-circular>
                </div>

                <div v-else-if="detail">
                    <div
                        class="d-flex align-center justify-space-between mb-6 flex-wrap ga-3"
                    >
                        <div class="d-flex align-center ga-2">
                            <span class="text-caption text-medium-emphasis mr-1"
                                >Pengajuan Level:</span
                            >
                            <BaseBadge
                                type="level"
                                :value="detail.level?.name"
                                inline
                            />
                            <div class="mx-2 border-r h-50"></div>
                            <span class="text-caption text-medium-emphasis mr-1"
                                >Status:</span
                            >
                            <BaseBadge
                                type="registration_status"
                                :value="detail.status?.code"
                                inline
                            />
                        </div>
                        <div
                            class="text-caption text-medium-emphasis text-right"
                        >
                            <div v-if="detail.submitted_at">
                                <v-icon size="14" class="mr-1"
                                    >mdi-calendar-clock</v-icon
                                >
                                Diajukan:
                                {{ formatDateTime(detail.submitted_at) }}
                            </div>
                            <div v-if="detail.processed_at" class="mt-1">
                                <v-icon size="14" class="mr-1"
                                    >mdi-account-check</v-icon
                                >
                                Diproses oleh
                                {{ detail.processed_by?.name || "-" }} pada
                                {{ formatDateTime(detail.processed_at) }}
                            </div>
                        </div>
                    </div>

                    <v-row>
                        <v-col cols="12" md="6">
                            <h3
                                class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3"
                            >
                                <v-icon size="22" color="primary"
                                    >mdi-account-circle-outline</v-icon
                                >
                                Data Calon Mitra
                            </h3>
                            <v-card
                                variant="flat"
                                class="rounded-lg border border-opacity-25"
                            >
                                <v-list
                                    density="compact"
                                    class="bg-transparent pa-2"
                                >
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Nama Lengkap</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-bold text-body-2"
                                                >{{
                                                    detail.applicant?.name ||
                                                    "-"
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                    <v-divider
                                        class="my-1 border-opacity-50"
                                    ></v-divider>
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Username</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-bold text-body-2"
                                                >{{
                                                    detail.applicant
                                                        ?.username || "-"
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                    <v-divider
                                        class="my-1 border-opacity-50"
                                    ></v-divider>
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Email</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-bold text-body-2"
                                                >{{
                                                    detail.applicant?.email ||
                                                    "-"
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                    <v-divider
                                        class="my-1 border-opacity-50"
                                    ></v-divider>
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Nomor Telepon</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-bold text-body-2"
                                                >{{
                                                    detail.applicant
                                                        ?.mobile_phone || "-"
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                    <v-divider
                                        class="my-1 border-opacity-50"
                                    ></v-divider>
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Jenis Kelamin</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-bold text-body-2"
                                                >{{
                                                    detail.applicant?.gender ||
                                                    "-"
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                    <v-divider
                                        class="my-1 border-opacity-50"
                                    ></v-divider>
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Tanggal Lahir</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-bold text-body-2"
                                                >{{
                                                    formatDate(
                                                        detail.applicant
                                                            ?.birth_date,
                                                    ) || "-"
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                </v-list>
                            </v-card>
                        </v-col>

                        <v-col cols="12" md="6">
                            <h3
                                class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3"
                            >
                                <v-icon size="22" color="primary"
                                    >mdi-card-account-details-outline</v-icon
                                >
                                Data Identitas
                            </h3>
                            <v-card
                                variant="flat"
                                class="rounded-lg border border-opacity-25"
                            >
                                <v-list
                                    density="compact"
                                    class="bg-transparent pa-2"
                                >
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Jenis Identitas</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-bold text-body-2"
                                                >{{
                                                    detail.identity?.type || "-"
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                    <v-divider
                                        class="my-1 border-opacity-50"
                                    ></v-divider>
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Nomor Identitas</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-bold text-body-2"
                                                >{{
                                                    detail.identity?.number ||
                                                    "-"
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                    <v-divider
                                        class="my-1 border-opacity-50"
                                    ></v-divider>
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >NIB</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-bold text-body-2"
                                                >{{
                                                    detail.identity?.nib || "-"
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                </v-list>
                            </v-card>

                            <template v-if="detail.sponsor">
                                <h3
                                    class="text-subtitle-1 font-weight-bold mb-3 mt-4 d-flex align-center ga-2 text-grey-darken-3"
                                >
                                    <v-icon size="22" color="primary"
                                        >mdi-account-group-outline</v-icon
                                    >
                                    Upline
                                </h3>
                                <v-card
                                    variant="flat"
                                    class="rounded-lg border border-opacity-25"
                                >
                                    <v-list
                                        density="compact"
                                        class="bg-transparent pa-2"
                                    >
                                        <v-list-item>
                                            <v-list-item-title
                                                class="text-body-2 text-medium-emphasis"
                                                >Mitra Upline</v-list-item-title
                                            >
                                            <template #append>
                                                <div class="text-right">
                                                    <div
                                                        class="font-weight-bold text-body-2"
                                                    >
                                                        {{
                                                            detail.sponsor
                                                                ?.name || "-"
                                                        }}
                                                    </div>
                                                    <div
                                                        class="text-caption text-medium-emphasis"
                                                    >
                                                        {{
                                                            detail.sponsor
                                                                ?.code || "-"
                                                        }}
                                                    </div>
                                                </div>
                                            </template>
                                        </v-list-item>
                                    </v-list>
                                </v-card>
                            </template>
                        </v-col>

                        <v-col cols="12" md="6" v-if="detail.address">
                            <h3
                                class="text-subtitle-1 font-weight-bold mb-3 mt-4 d-flex align-center ga-2 text-grey-darken-3"
                            >
                                <v-icon size="22" color="primary"
                                    >mdi-map-marker-outline</v-icon
                                >
                                Data Alamat
                            </h3>
                            <v-card
                                variant="flat"
                                class="rounded-lg border border-opacity-25"
                            >
                                <v-card-text class="pa-5 text-body-2">
                                    <p
                                        class="font-weight-medium mb-1 text-grey-darken-2"
                                    >
                                        Alamat Lengkap:
                                    </p>
                                    <p
                                        class="font-weight-bold mb-4"
                                        style="line-height: 1.5"
                                    >
                                        {{ formatLocation(detail.address) }}
                                    </p>
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col cols="12" md="6" v-if="detail.bank">
                            <h3
                                class="text-subtitle-1 font-weight-bold mb-3 mt-4 d-flex align-center ga-2 text-grey-darken-3"
                            >
                                <v-icon size="22" color="primary"
                                    >mdi-bank-outline</v-icon
                                >
                                Data Rekening
                            </h3>
                            <v-card
                                variant="flat"
                                class="rounded-lg border border-opacity-25"
                            >
                                <v-list
                                    density="compact"
                                    class="bg-transparent pa-2"
                                >
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Nama Bank</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-bold text-body-2"
                                                >{{
                                                    detail.bank?.name || "-"
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                    <v-divider
                                        class="my-1 border-opacity-50"
                                    ></v-divider>
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Nomor Rekening</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-bold text-body-2"
                                                >{{
                                                    detail.bank
                                                        ?.account_number || "-"
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                    <v-divider
                                        class="my-1 border-opacity-50"
                                    ></v-divider>
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Nama Pemilik
                                            Rekening</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-bold text-body-2"
                                                >{{
                                                    detail.bank?.account_name ||
                                                    "-"
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                </v-list>
                            </v-card>
                        </v-col>

                        <v-col cols="12" v-if="detail.note">
                            <v-alert
                                color="warning"
                                variant="tonal"
                                border="start"
                                density="comfortable"
                                class="mt-4"
                            >
                                <strong>Catatan:</strong><br />
                                {{ detail.note }}
                            </v-alert>
                        </v-col>
                    </v-row>
                </div>
            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions class="pa-6 pt-4 border-t">
                <v-spacer />
                <v-btn
                    variant="flat"
                    color="primary"
                    class="text-none px-8"
                    @click="close"
                    >Tutup</v-btn
                >
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import api from "@/shared/services/api";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const { formatDate, formatDateTime, formatLocation } = useFormatter();
const snackbar = useSnackbarStore();

const dialog = ref(false);
const loading = ref(false);
const detail = ref<any>(null);

const open = async (id: number) => {
    dialog.value = true;
    loading.value = true;
    detail.value = null;

    try {
        const { data } = await api.get(
            `/admin/partnership/registrations/${id}`,
        );
        if (data?.success) {
            detail.value = data.data;
        }
    } catch (error: any) {
        console.error("Gagal memuat detail pengajuan:", error);
        snackbar.showMessage(
            error.response?.data?.message ||
                "Gagal memuat detail pengajuan mitra",
            "error",
        );
    } finally {
        loading.value = false;
    }
};

const close = () => {
    dialog.value = false;
};

defineExpose({ open, close });
</script>
