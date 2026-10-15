<template>
    <div class="screen-body approval-page">
        <div v-if="loading" class="approval-page__state card">
            <v-progress-circular indeterminate color="primary" size="34" />
            <span>Memuat detail pendaftaran...</span>
        </div>

        <div v-else-if="loadError" class="approval-page__state card">
            <v-icon icon="mdi-alert-circle-outline" color="error" size="42" />
            <strong>Detail pendaftaran gagal dimuat</strong>
            <span>{{ loadError }}</span>
            <v-btn
                color="primary"
                variant="tonal"
                class="text-none mt-2"
                prepend-icon="mdi-refresh"
                @click="fetchDetail"
            >
                Coba Lagi
            </v-btn>
        </div>

        <template v-else-if="detail">
            <section class="approval-hero">
                <button
                    type="button"
                    class="approval-hero__avatar"
                    :class="{
                        'approval-hero__avatar--clickable': detail.image_url,
                    }"
                    :disabled="!detail.image_url"
                    aria-label="Lihat foto profil"
                    @click="openMediaPreview(registrationMedia[0])"
                >
                    <v-img
                        v-if="detail.image_url"
                        :src="detail.image_url"
                        :cover="true"
                    />
                    <v-icon v-else icon="mdi-account-outline" size="34" />
                </button>

                <div class="approval-hero__identity">
                    <h1>{{ detail.applicant.name }}</h1>
                    <p>
                        {{ detail.applicant.email || "Email tidak tersedia" }}
                    </p>
                    <div class="approval-hero__badges">
                        <BaseBadge
                            type="level"
                            :value="detail.level.code"
                            inline
                        />
                        <span class="approval-pending-badge">
                            {{ detail.distributor_approval.status.label }}
                        </span>
                    </div>
                </div>

                <div class="approval-hero__submitted">
                    <span>Tanggal Pendaftaran</span>
                    <strong>{{ formatDateTime(detail.submitted_at) }}</strong>
                </div>
            </section>

            <div class="approval-columns">
                <section class="approval-section">
                    <h2>
                        <v-icon icon="mdi-account-outline" size="19" />
                        Informasi Calon Mitra
                    </h2>
                    <div class="approval-info-card">
                        <div class="approval-info-row">
                            <span>Nama</span>
                            <strong>{{ detail.applicant.name }}</strong>
                        </div>
                        <div class="approval-info-row">
                            <span>Nomor Telepon</span>
                            <strong>{{ detail.applicant.mobile_phone }}</strong>
                        </div>
                        <div class="approval-info-row">
                            <span>Jenis Kelamin</span>
                            <strong>{{
                                detail.applicant.gender || "-"
                            }}</strong>
                        </div>
                        <div class="approval-info-row">
                            <span>Tanggal Lahir</span>
                            <strong>{{
                                formatDate(detail.applicant.birth_date)
                            }}</strong>
                        </div>
                        <div class="approval-info-row">
                            <span>{{
                                detail.identity.type || "Identitas"
                            }}</span>
                            <strong>{{ detail.identity.number || "-" }}</strong>
                        </div>
                    </div>
                </section>

                <section class="approval-section">
                    <h2>
                        <v-icon icon="mdi-source-branch" size="19" />
                        Informasi Jaringan
                    </h2>
                    <div class="approval-info-card">
                        <div class="approval-info-row">
                            <span>Level Didaftarkan</span>
                            <strong>{{ detail.level.name }}</strong>
                        </div>
                        <div class="approval-info-row">
                            <span>Nama Upline (Pendaftar)</span>
                            <strong>{{ detail.sponsor?.name || "-" }}</strong>
                        </div>
                        <div class="approval-info-row">
                            <span>Kode Mitra Upline</span>
                            <strong>{{ detail.sponsor?.code || "-" }}</strong>
                        </div>
                    </div>
                </section>
            </div>

            <section class="approval-section">
                <h2>
                    <v-icon icon="mdi-file-image-outline" size="19" />
                    Dokumen Pendaftaran
                </h2>
                <div class="approval-document-grid">
                    <button
                        v-for="media in registrationMedia"
                        :key="media.key"
                        type="button"
                        class="approval-document-card"
                        :class="{
                            'approval-document-card--clickable': media.url,
                        }"
                        :disabled="!media.url"
                        @click="openMediaPreview(media)"
                    >
                        <div class="approval-document-card__preview">
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
                                            size="24"
                                        />
                                    </div>
                                </template>
                                <template #error>
                                    <div class="approval-media-fallback">
                                        <v-icon
                                            icon="mdi-image-broken-variant"
                                            size="30"
                                        />
                                        <span>Gambar gagal dimuat</span>
                                    </div>
                                </template>
                            </v-img>
                            <div v-else class="approval-media-fallback">
                                <v-icon :icon="media.icon" size="30" />
                                <span>Belum diunggah</span>
                            </div>
                        </div>
                        <div class="approval-document-card__copy">
                            <strong>{{ media.title }}</strong>
                            <span>
                                {{
                                    media.url
                                        ? "Ketuk untuk memperbesar"
                                        : "Tidak ada gambar"
                                }}
                            </span>
                        </div>
                    </button>
                </div>
            </section>

            <div class="approval-columns">
                <section class="approval-section">
                    <h2>
                        <v-icon icon="mdi-map-marker-outline" size="19" />
                        Alamat Lengkap
                    </h2>
                    <div class="approval-info-card approval-copy-card">
                        <strong>{{ detail.address.address }}</strong>
                        <span>{{ addressRegion }}</span>
                    </div>
                </section>

                <section class="approval-section">
                    <h2>
                        <v-icon icon="mdi-bank-outline" size="19" />
                        Rekening Bank
                    </h2>
                    <div v-if="detail.bank" class="approval-info-card">
                        <div class="approval-info-row">
                            <span>Bank</span>
                            <strong>{{ detail.bank.name || "-" }}</strong>
                        </div>
                        <div class="approval-info-row">
                            <span>Nomor Rekening</span>
                            <strong>{{ detail.bank.account_number }}</strong>
                        </div>
                        <div class="approval-info-row">
                            <span>Nama Pemilik Rekening</span>
                            <strong>{{ detail.bank.account_name }}</strong>
                        </div>
                    </div>
                    <div v-else class="approval-info-card approval-copy-card">
                        <span>Data rekening tidak tersedia.</span>
                    </div>
                </section>
            </div>

            <div
                v-if="canProcess"
                class="approval-action-bar payment-actions"
            >
                <button
                    type="button"
                    class="reject-payment-button"
                    @click="requestAction('reject')"
                >
                    Tolak Pendaftaran
                </button>
                <button
                    type="button"
                    class="approve-payment-button"
                    @click="requestAction('approve')"
                >
                    Setujui Pendaftaran
                </button>
            </div>
        </template>

        <RegistrationApprovalActionDialog
            ref="approvalActionRef"
            @processed="handleProcessed"
        />
    </div>

    <v-dialog v-model="mediaPreviewDialog" max-width="900">
        <v-card class="approval-media-dialog rounded-xl overflow-hidden">
            <v-card-title class="approval-media-dialog__header">
                <div class="d-flex align-center ga-2 min-w-0">
                    <v-icon icon="mdi-image-outline" color="primary" />
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
                    aria-label="Tutup preview"
                    @click="mediaPreviewDialog = false"
                />
            </v-card-title>
            <v-divider />
            <v-card-text class="approval-media-dialog__body">
                <v-img
                    v-if="selectedMedia?.url"
                    :src="selectedMedia.url"
                    :alt="selectedMedia.title"
                    max-height="76vh"
                    :cover="false"
                />
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import RegistrationApprovalActionDialog from "@/member/components/network/RegistrationApprovalActionDialog.vue";
import networkService from "@/member/services/network.service";
import type { RegistrationDetail } from "@/member/types/network";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useAuthStore } from "@/shared/stores/auth";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { decodeRouteId } from "@/shared/utils/route-id";

interface RegistrationMedia {
    key: "profile" | "identity" | "payment-proof";
    title: string;
    url: string | null;
    icon: string;
}

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const snackbar = useSnackbarStore();
const { formatDate, formatDateTime } = useFormatter();
const registrationId = Number(
    decodeRouteId((route.params as Record<string, unknown>).id),
);

const loading = ref(true);
const loadError = ref("");
const detail = ref<RegistrationDetail | null>(null);
const mediaPreviewDialog = ref(false);
const selectedMedia = ref<RegistrationMedia | null>(null);
const approvalActionRef = ref<InstanceType<
    typeof RegistrationApprovalActionDialog
> | null>(null);

const isDistributor = computed(
    () => authStore.user?.member?.level?.code === "DST",
);

const canProcess = computed(
    () =>
        detail.value?.status.code === "requested" &&
        detail.value.distributor_approval.status.code === "pending",
);

const addressRegion = computed(() => {
    if (!detail.value?.address) {
        return "-";
    }

    const address = detail.value.address;
    const region = [
        address.subdistrict_name,
        address.district_name,
        address.city_name,
        address.province_name,
    ].filter(Boolean);

    return (
        `${region.join(", ")}${address.postal_code ? ` ${address.postal_code}` : ""}` ||
        "-"
    );
});

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
        url: detail.value?.identity.image_url || null,
        icon: "mdi-card-account-details-outline",
    },
    {
        key: "payment-proof",
        title: "Bukti Transfer",
        url: detail.value?.payment_proof_image_url || null,
        icon: "mdi-receipt-text-check-outline",
    },
]);

async function fetchDetail() {
    loading.value = true;
    loadError.value = "";

    if (!Number.isSafeInteger(registrationId) || registrationId <= 0) {
        loadError.value = "ID pendaftaran tidak valid.";
        loading.value = false;
        return;
    }

    try {
        detail.value =
            await networkService.getRegistrationApproval(registrationId);
    } catch (error: any) {
        loadError.value =
            error.response?.data?.message ||
            "Silakan kembali ke daftar approval dan coba lagi.";
    } finally {
        loading.value = false;
    }
}

function openMediaPreview(media: RegistrationMedia) {
    if (!media.url) {
        return;
    }

    selectedMedia.value = media;
    mediaPreviewDialog.value = true;
}

function requestAction(actionType: "approve" | "reject") {
    if (!detail.value) {
        return;
    }

    approvalActionRef.value?.open(
        detail.value.id,
        detail.value.applicant.name,
        actionType,
    );
}

function handleProcessed() {
    router.replace("/member/network?tab=approval");
}

onMounted(() => {
    if (!isDistributor.value) {
        snackbar.showMessage(
            "Halaman approval hanya dapat diakses Distributor.",
            "error",
        );
        router.replace("/member/network");
        return;
    }

    void fetchDetail();
});
</script>

<style scoped>
.approval-page__state {
    display: flex;
    min-height: 280px;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 10px;
    color: var(--muted);
    text-align: center;
}

.approval-page__state strong {
    color: var(--ink);
}

.approval-hero {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    gap: 12px;
    padding: 14px;
    border: 1px solid #eadde0;
    border-radius: 14px;
    background: #fff;
}

.approval-hero__avatar {
    display: grid;
    width: 64px;
    height: 64px;
    padding: 0;
    overflow: hidden;
    place-items: center;
    border: 2px solid #efd8dd;
    border-radius: 50%;
    color: var(--wine);
    background: var(--rose);
}

.approval-hero__avatar .v-img {
    width: 100%;
    height: 100%;
}

.approval-hero__avatar--clickable {
    cursor: pointer;
}

.approval-hero__identity {
    min-width: 0;
}

.approval-hero__identity h1 {
    margin: 0;
    overflow-wrap: anywhere;
    color: var(--ink);
    font-size: 17px;
    line-height: 1.3;
}

.approval-hero__identity p {
    margin: 3px 0 0;
    overflow: hidden;
    color: var(--muted);
    font-size: 12px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.approval-hero__badges {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}

.approval-pending-badge {
    padding: 4px 9px;
    border-radius: 999px;
    color: #9a6700;
    background: #fff4ce;
    font-size: 10px;
    font-weight: 700;
}

.approval-hero__submitted {
    display: flex;
    grid-column: 1 / -1;
    align-items: flex-start;
    flex-direction: column;
    gap: 2px;
    padding-top: 10px;
    border-top: 1px solid #f3e9eb;
    color: var(--muted);
    font-size: 10px;
}

.approval-hero__submitted strong {
    color: var(--ink);
    font-size: 11px;
}

.approval-columns {
    display: grid;
    grid-template-columns: 1fr;
}

.approval-section {
    min-width: 0;
    margin-top: 18px;
}

.approval-section h2 {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 0 0 8px;
    color: #4b3f42;
    font-size: 13px;
}

.approval-section h2 .v-icon {
    color: var(--wine);
}

.approval-info-card {
    height: calc(100% - 29px);
    padding: 7px 13px;
    border: 1px solid #eadde0;
    border-radius: 12px;
    background: #fff;
}

.approval-info-row {
    display: grid;
    grid-template-columns: minmax(105px, 0.8fr) minmax(0, 1.2fr);
    gap: 10px;
    padding: 9px 0;
    border-bottom: 1px solid #f3e9eb;
    font-size: 11px;
}

.approval-info-row:last-child {
    border-bottom: 0;
}

.approval-info-row span,
.approval-copy-card span {
    color: var(--muted);
}

.approval-info-row strong {
    overflow-wrap: anywhere;
    color: var(--ink);
    text-align: right;
}

.approval-document-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 9px;
}

.approval-document-card {
    display: grid;
    grid-template-columns: 110px minmax(0, 1fr);
    min-width: 0;
    padding: 0;
    overflow: hidden;
    border: 1px solid #dfcfd3;
    border-radius: 12px;
    color: inherit;
    background: #fff;
    text-align: left;
}

.approval-document-card--clickable {
    cursor: pointer;
}

.approval-document-card__preview {
    display: grid;
    height: 100px;
    place-items: center;
    overflow: hidden;
    background: #f8edef;
}

.approval-document-card__preview .v-img {
    width: 100%;
    height: 100%;
}

.approval-document-card__copy {
    display: flex;
    justify-content: center;
    flex-direction: column;
    gap: 2px;
    padding: 10px 12px;
}

.approval-document-card__copy strong {
    font-size: 12px;
}

.approval-document-card__copy span,
.approval-media-fallback {
    color: var(--muted);
    font-size: 10px;
}

.approval-media-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 6px;
}

.approval-copy-card {
    display: flex;
    min-height: 94px;
    justify-content: center;
    flex-direction: column;
    gap: 5px;
    color: var(--ink);
    font-size: 11px;
    line-height: 1.55;
}

.approval-action-bar {
    margin-top: 20px;
}

.approval-media-dialog__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
}

.approval-media-dialog__body {
    display: flex;
    min-height: 260px;
    align-items: center;
    justify-content: center;
    padding: 12px;
    background: #f8edef;
}

.approval-media-dialog__body .v-img {
    width: 100%;
}

@media (min-width: 640px) {
    .approval-page {
        padding-right: 20px;
        padding-left: 20px;
    }

    .approval-hero {
        grid-template-columns: auto minmax(0, 1fr) auto;
        padding: 16px;
    }

    .approval-hero__avatar {
        width: 72px;
        height: 72px;
    }

    .approval-hero__identity h1 {
        font-size: 20px;
    }

    .approval-hero__submitted {
        grid-column: auto;
        align-items: flex-end;
        padding-top: 0;
        border-top: 0;
    }

    .approval-columns {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .approval-document-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .approval-document-card {
        display: block;
    }

    .approval-document-card__preview {
        height: 165px;
    }

    .approval-document-card--clickable {
        transition:
            border-color 0.2s ease,
            box-shadow 0.2s ease,
            transform 0.2s ease;
    }

    .approval-document-card--clickable:hover {
        border-color: #c98898;
        box-shadow: 0 8px 20px rgba(91, 29, 44, 0.1);
        transform: translateY(-2px);
    }

}
</style>
