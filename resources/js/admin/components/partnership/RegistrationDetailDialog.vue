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

                            <template
                                v-if="
                                    detail.submitted_by ||
                                    showNetworkDistributor ||
                                    detail.sponsor
                                "
                            >
                                <h3
                                    class="text-subtitle-1 font-weight-bold mb-3 mt-4 d-flex align-center ga-2 text-grey-darken-3"
                                >
                                    <v-icon size="22" color="primary"
                                        >mdi-account-group-outline</v-icon
                                    >
                                    Informasi Jaringan
                                </h3>
                                <v-card
                                    variant="flat"
                                    class="rounded-lg border border-opacity-25"
                                >
                                    <v-list
                                        density="compact"
                                        class="bg-transparent pa-2"
                                    >
                                        <v-list-item v-if="detail.submitted_by">
                                            <v-list-item-title
                                                class="text-body-2 text-medium-emphasis"
                                                >Didaftarkan
                                                oleh</v-list-item-title
                                            >
                                            <template #append>
                                                <div class="text-right">
                                                    <div
                                                        class="font-weight-bold text-body-2"
                                                    >
                                                        {{
                                                            detail.submitted_by
                                                                ?.name || "-"
                                                        }}
                                                    </div>
                                                    <div
                                                        class="text-caption text-medium-emphasis"
                                                    >
                                                        {{
                                                            detail.submitted_by
                                                                ?.code || "-"
                                                        }}
                                                        <span
                                                            v-if="
                                                                detail
                                                                    .submitted_by
                                                                    ?.level_name
                                                            "
                                                        >
                                                            ·
                                                            {{
                                                                detail
                                                                    .submitted_by
                                                                    .level_name
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </template>
                                        </v-list-item>
                                        <v-divider
                                            v-if="
                                                detail.submitted_by &&
                                                (showNetworkDistributor ||
                                                    detail.sponsor)
                                            "
                                            class="my-1 border-opacity-50"
                                        ></v-divider>
                                        <v-list-item
                                            v-if="showNetworkDistributor"
                                        >
                                            <v-list-item-title
                                                class="text-body-2 text-medium-emphasis"
                                                >Distributor
                                                Jaringan</v-list-item-title
                                            >
                                            <template #append>
                                                <div class="text-right">
                                                    <div
                                                        class="font-weight-bold text-body-2"
                                                    >
                                                        {{
                                                            detail
                                                                .network_distributor
                                                                ?.name || "-"
                                                        }}
                                                    </div>
                                                    <div
                                                        class="text-caption text-medium-emphasis"
                                                    >
                                                        {{
                                                            detail
                                                                .network_distributor
                                                                ?.code || "-"
                                                        }}
                                                    </div>
                                                </div>
                                            </template>
                                        </v-list-item>
                                        <v-divider
                                            v-if="
                                                showNetworkDistributor &&
                                                detail.sponsor
                                            "
                                            class="my-1 border-opacity-50"
                                        ></v-divider>
                                        <v-list-item v-if="detail.sponsor">
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

                        <v-col cols="12" v-if="hasRegistrationMedia">
                            <h3
                                class="text-subtitle-1 font-weight-bold mb-3 mt-4 d-flex align-center ga-2 text-grey-darken-3"
                            >
                                <v-icon size="22" color="primary"
                                    >mdi-file-image-outline</v-icon
                                >
                                Dokumen Pendaftaran
                            </h3>
                            <v-row dense>
                                <v-col
                                    v-for="media in registrationMedia"
                                    :key="media.key"
                                    cols="12"
                                    sm="6"
                                    md="4"
                                >
                                    <v-card
                                        variant="flat"
                                        class="registration-media-card rounded-lg border border-opacity-25 h-100"
                                        :class="{
                                            'registration-media-card--clickable':
                                                media.url,
                                        }"
                                        :role="media.url ? 'button' : undefined"
                                        :tabindex="media.url ? 0 : undefined"
                                        @click="openMediaPreview(media)"
                                        @keyup.enter="openMediaPreview(media)"
                                    >
                                        <div class="registration-media-preview">
                                            <v-img
                                                v-if="media.url"
                                                :src="media.url"
                                                :alt="media.title"
                                                :cover="false"
                                            >
                                                <template #placeholder>
                                                    <div
                                                        class="d-flex fill-height align-center justify-center"
                                                    >
                                                        <v-progress-circular
                                                            indeterminate
                                                            color="primary"
                                                            size="28"
                                                            width="3"
                                                        />
                                                    </div>
                                                </template>
                                                <template #error>
                                                    <div
                                                        class="registration-media-fallback"
                                                    >
                                                        <v-icon
                                                            icon="mdi-image-broken-variant"
                                                            size="36"
                                                        />
                                                        <span
                                                            >Gambar gagal
                                                            dimuat</span
                                                        >
                                                    </div>
                                                </template>
                                            </v-img>
                                            <div
                                                v-else
                                                class="registration-media-fallback"
                                            >
                                                <v-icon
                                                    :icon="media.icon"
                                                    size="36"
                                                />
                                                <span>Belum diunggah</span>
                                            </div>
                                            <div
                                                v-if="media.url"
                                                class="registration-media-overlay"
                                            >
                                                <v-icon
                                                    icon="mdi-magnify-plus-outline"
                                                    color="white"
                                                    size="28"
                                                />
                                            </div>
                                        </div>
                                        <v-card-text class="pa-3">
                                            <div
                                                class="font-weight-bold text-body-2"
                                            >
                                                {{ media.title }}
                                            </div>
                                            <div
                                                class="text-caption text-medium-emphasis mt-1"
                                            >
                                                {{
                                                    media.url
                                                        ? "Klik untuk memperbesar"
                                                        : "Tidak ada gambar"
                                                }}
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>
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

    <v-dialog v-model="mediaPreviewDialog" max-width="900">
        <v-card class="rounded-xl overflow-hidden">
            <v-card-title
                class="pa-4 d-flex align-center justify-space-between border-b"
            >
                <div class="d-flex align-center ga-2 min-w-0">
                    <v-icon color="primary" size="22">mdi-image-outline</v-icon>
                    <span
                        class="text-subtitle-1 font-weight-bold text-truncate"
                    >
                        {{ selectedMedia?.title }}
                    </span>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    density="comfortable"
                    @click="mediaPreviewDialog = false"
                />
            </v-card-title>
            <v-card-text class="media-preview-dialog pa-3 pa-sm-5">
                <v-img
                    v-if="selectedMedia?.url"
                    :src="selectedMedia.url"
                    :alt="selectedMedia.title"
                    max-height="72vh"
                    :cover="false"
                >
                    <template #placeholder>
                        <div
                            class="d-flex fill-height align-center justify-center"
                        >
                            <v-progress-circular
                                indeterminate
                                color="primary"
                            />
                        </div>
                    </template>
                    <template #error>
                        <div class="registration-media-fallback py-12">
                            <v-icon icon="mdi-image-broken-variant" size="48" />
                            <span>Gambar gagal dimuat</span>
                        </div>
                    </template>
                </v-img>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import api from "@/shared/services/api";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const { formatDate, formatDateTime, formatLocation } = useFormatter();
const snackbar = useSnackbarStore();

const dialog = ref(false);
const loading = ref(false);
const detail = ref<any>(null);
const mediaPreviewDialog = ref(false);
const selectedMedia = ref<RegistrationMedia | null>(null);

interface RegistrationMedia {
    key: "profile" | "identity" | "payment-proof";
    title: string;
    url: string | null;
    icon: string;
}

const registrationMedia = computed<RegistrationMedia[]>(() => [
    {
        key: "profile",
        title: "Foto Profil",
        url: detail.value?.image_url || null,
        icon: "mdi-account-circle-outline",
    },
    {
        key: "identity",
        title: "Dokumen Identitas",
        url: detail.value?.identity?.image_url || null,
        icon: "mdi-card-account-details-outline",
    },
    {
        key: "payment-proof",
        title: "Bukti Transfer",
        url: detail.value?.payment_proof_image_url || null,
        icon: "mdi-receipt-text-check-outline",
    },
]);
const hasRegistrationMedia = computed(() =>
    registrationMedia.value.some((media) => media.url),
);
const showNetworkDistributor = computed(
    () =>
        detail.value?.network_distributor &&
        detail.value.network_distributor.id !== detail.value.submitted_by?.id,
);

const openMediaPreview = (media: RegistrationMedia) => {
    if (!media.url) {
        return;
    }

    selectedMedia.value = media;
    mediaPreviewDialog.value = true;
};

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
    mediaPreviewDialog.value = false;
    selectedMedia.value = null;
};

defineExpose({ open, close });
</script>

<style scoped>
.registration-media-card {
    overflow: hidden;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        transform 0.2s ease;
}

.registration-media-card--clickable {
    cursor: pointer;
}

.registration-media-card--clickable:hover,
.registration-media-card--clickable:focus-visible {
    border-color: rgb(var(--v-theme-primary)) !important;
    box-shadow: 0 8px 20px rgba(53, 22, 28, 0.12);
    outline: none;
    transform: translateY(-2px);
}

.registration-media-preview {
    position: relative;
    height: 180px;
    overflow: hidden;
    background: rgb(var(--v-theme-surface-variant));
}

.registration-media-preview .v-img {
    width: 100%;
    height: 100%;
}

.registration-media-fallback {
    display: flex;
    width: 100%;
    height: 100%;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 8px;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 12px;
}

.registration-media-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.38);
    opacity: 0;
    transition: opacity 0.2s ease;
}

.registration-media-card--clickable:hover .registration-media-overlay,
.registration-media-card--clickable:focus-visible .registration-media-overlay {
    opacity: 1;
}

.media-preview-dialog {
    min-height: 240px;
    background: rgb(var(--v-theme-surface-variant));
}

@media (max-width: 600px) {
    .registration-media-preview {
        height: 210px;
    }

    .registration-media-overlay {
        top: auto;
        left: auto;
        width: 42px;
        height: 42px;
        border-radius: 12px 0 0;
        opacity: 1;
    }
}
</style>
