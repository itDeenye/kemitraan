<template>
    <div class="screen-body">
        <div class="card profile-card">
            <v-avatar
                color="primary"
                size="66"
                class="mx-auto mb-3 text-white font-weight-bold text-h5"
            >
                <img
                    v-if="profileData?.member?.image"
                    :src="profileData.member.image"
                    :alt="profileData.member.name"
                    style="width: 100%; height: 100%; object-fit: cover"
                />
                <span v-else>{{
                    profileData?.member?.name?.charAt(0)?.toUpperCase() || "U"
                }}</span>
            </v-avatar>
            <h2>{{ profileData?.member?.name || "Memuat..." }}</h2>
            <p>{{ profileData?.member?.email }}</p>
            <p>{{ profileData?.member?.code }}</p>
            <span class="badge gold">{{
                profileData?.member?.level?.name || "Mitra"
            }}</span>
            <button
                class="secondary-button block"
                style="width: 100%; margin-top: 12px"
                @click="router.push('/member/profile/edit')"
            >
                <v-icon icon="mdi-pencil-outline" size="14" />
                Edit Profil
            </button>
        </div>

        <template v-if="profileData">
            <div class="menu-group-title">Dokumen Mitra</div>
            <div class="profile-document-grid">
                <button
                    v-for="media in profileDocuments"
                    :key="media.key"
                    type="button"
                    class="profile-document-card"
                    :class="{
                        'profile-document-card--clickable': media.url,
                    }"
                    :disabled="!media.url"
                    :aria-label="
                        media.url
                            ? `Lihat ${media.title}`
                            : `${media.title} belum tersedia`
                    "
                    @click="openMediaPreview(media)"
                >
                    <div class="profile-document-card__preview">
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
                                        size="22"
                                        width="2"
                                    />
                                </div>
                            </template>
                            <template #error>
                                <div class="profile-document-fallback">
                                    <v-icon
                                        icon="mdi-image-broken-variant"
                                        size="26"
                                    />
                                    <span>Gagal dimuat</span>
                                </div>
                            </template>
                        </v-img>
                        <div v-else class="profile-document-fallback">
                            <v-icon :icon="media.icon" size="26" />
                            <span>Belum tersedia</span>
                        </div>
                    </div>
                    <div class="profile-document-card__content">
                        <strong>{{ media.title }}</strong>
                        <span>{{ media.description }}</span>
                        <small v-if="media.url">
                            <v-icon icon="mdi-magnify-plus-outline" size="13" />
                            Ketuk untuk melihat
                        </small>
                    </div>
                </button>
            </div>
        </template>

        <div class="menu-group-title">Digital Card Mitra</div>
        <DigitalMemberCard :member="profileData?.member" />

        <div class="menu-group-title">Pengaturan akun</div>
        <div class="card menu-list">
            <div
                class="flow-link"
                @click="router.push('/member/profile/address')"
            >
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-truck-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Alamat</strong>
                        <span>Kelola alamat pengiriman</span>
                    </div>
                    <div class="row-side">
                        <v-icon
                            class="navigation-chevron"
                            icon="mdi-chevron-right"
                            size="24"
                        />
                    </div>
                </div>
            </div>
            <div class="flow-link" @click="router.push('/member/profile/bank')">
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-bank-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Rekening</strong>
                        <span>Kelola rekening pencairan</span>
                    </div>
                    <div class="row-side">
                        <v-icon
                            class="navigation-chevron"
                            icon="mdi-chevron-right"
                            size="24"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="menu-group-title">Manajemen Kemitraan</div>
        <div class="card menu-list">
            <div
                class="flow-link"
                @click="router.push('/member/profile/partnership')"
            >
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-account-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Kemitraan</strong>
                        <span>Kode, level, dan status mitra</span>
                    </div>
                    <div class="row-side">
                        <v-icon
                            class="navigation-chevron"
                            icon="mdi-chevron-right"
                            size="24"
                        />
                    </div>
                </div>
            </div>
            <div
                v-if="isDistributorOrAgent"
                class="flow-link"
                @click="router.push('/member/network')"
            >
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-account-network-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Jaringan Mitra</strong>
                        <span>Downline dan pendaftaran mitra</span>
                    </div>
                    <div class="row-side">
                        <v-icon
                            class="navigation-chevron"
                            icon="mdi-chevron-right"
                            size="24"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="menu-group-title">Lainnya</div>
        <div class="card menu-list">
            <div
                class="flow-link"
                @click="router.push('/member/profile/security')"
            >
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-lock-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Keamanan Akun</strong>
                        <span>Ubah kata sandi</span>
                    </div>
                    <div class="row-side">
                        <v-icon
                            class="navigation-chevron"
                            icon="mdi-chevron-right"
                            size="24"
                        />
                    </div>
                </div>
            </div>
            <div class="flow-link" @click="handleLogout">
                <div class="list-row">
                    <div class="row-icon logout-icon">
                        <v-icon icon="mdi-logout" size="16" />
                    </div>
                    <div class="row-main">
                        <strong class="logout-text">Keluar</strong>
                        <span>Keluar dari aplikasi DNY</span>
                    </div>
                    <div class="row-side">
                        <v-icon
                            class="navigation-chevron"
                            icon="mdi-chevron-right"
                            size="24"
                        />
                    </div>
                </div>
            </div>
        </div>

        <MobileConfirm ref="confirmDialogRef" />

        <v-dialog
            v-model="mediaPreviewDialog"
            max-width="calc(100vw - 84px)"
            max-height="92vh"
        >
            <div
                class="profile-image-preview"
                :class="{
                    'profile-image-preview--state':
                        previewImageLoading || previewImageError,
                    'profile-image-preview--identity':
                        selectedMedia?.key === 'identity',
                }"
            >
                <img
                    v-show="!previewImageLoading && !previewImageError"
                    v-if="selectedMedia?.url"
                    :src="selectedMedia.url"
                    :alt="selectedMedia.title"
                    class="profile-image-preview__image"
                    @load="previewImageLoading = false"
                    @error="handlePreviewImageError"
                />
                <div
                    v-if="previewImageLoading"
                    class="profile-image-preview__state"
                >
                    <v-progress-circular indeterminate color="primary" />
                    <span>Memuat gambar...</span>
                </div>
                <div
                    v-else-if="previewImageError"
                    class="profile-image-preview__state"
                >
                    <v-icon icon="mdi-image-broken-variant" size="42" />
                    <span>Gambar gagal dimuat</span>
                </div>
                <v-btn
                    icon="mdi-close"
                    color="white"
                    variant="text"
                    density="comfortable"
                    class="profile-image-preview__close position-absolute"
                    aria-label="Tutup preview"
                    @click="mediaPreviewDialog = false"
                />
            </div>
        </v-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/shared/stores/auth";
import profileService from "@/member/services/profile.service";
import type { MemberProfile } from "@/member/types/profile";
import MobileConfirm from "@/member/components/MobileConfirm.vue";
import DigitalMemberCard from "@/member/components/profile/DigitalMemberCard.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const router = useRouter();
const authStore = useAuthStore();
const snackbar = useSnackbarStore();
const profileData = ref<MemberProfile | null>(null);
const confirmDialogRef = ref<InstanceType<typeof MobileConfirm> | null>(null);
const mediaPreviewDialog = ref(false);
const selectedMedia = ref<ProfileMedia | null>(null);
const previewImageLoading = ref(false);
const previewImageError = ref(false);

interface ProfileMedia {
    key: "identity" | "payment-proof";
    title: string;
    description: string;
    url: string | null;
    icon: string;
}

const profileDocuments = computed<ProfileMedia[]>(() => [
    {
        key: "identity",
        title: "Gambar Identitas",
        description: profileData.value?.member.identity?.type
            ? `Dokumen ${profileData.value.member.identity.type}`
            : "Dokumen identitas",
        url: profileData.value?.member.identity?.image || null,
        icon: "mdi-card-account-details-outline",
    },
    {
        key: "payment-proof",
        title: "Bukti Pembayaran",
        description: "Bukti pembayaran pendaftaran",
        url: profileData.value?.member.payment_proof_image_url || null,
        icon: "mdi-receipt-text-check-outline",
    },
]);

const isDistributorOrAgent = computed(() => {
    const roleId = authStore.user?.role?.id;
    return roleId === 1 || roleId === 2;
});

async function fetchProfile() {
    try {
        const response = await profileService.getProfile();
        if (response.success) {
            profileData.value = response.data;
        }
    } catch (error) {
        console.error("Failed to load profile:", error);
    }
}

function openMediaPreview(media: ProfileMedia) {
    if (!media.url) return;

    selectedMedia.value = media;
    previewImageLoading.value = true;
    previewImageError.value = false;
    mediaPreviewDialog.value = true;
}

function handlePreviewImageError() {
    previewImageLoading.value = false;
    previewImageError.value = true;
}

async function handleLogout() {
    if (!confirmDialogRef.value) return;

    const isConfirmed = await confirmDialogRef.value.open({
        title: "Keluar Aplikasi",
        message: "Apakah Anda yakin ingin keluar dari aplikasi DNY?",
        confirmText: "Ya, Keluar",
        confirmColor: "error",
        icon: "mdi-logout",
    });

    if (isConfirmed) {
        confirmDialogRef.value.isLoading = true;
        try {
            await authStore.logout(false);
            snackbar.showMessage("Anda berhasil keluar dari sistem.");
            router.push("/member/login");
        } catch (error) {
            console.error("Logout failed:", error);
        } finally {
            confirmDialogRef.value.isLoading = false;
        }
    }
}

onMounted(() => {
    fetchProfile();
});
</script>

<style scoped>
.profile-document-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 8px;
}

.profile-document-card {
    display: grid;
    grid-template-columns: 96px minmax(0, 1fr);
    min-width: 0;
    padding: 0;
    overflow: hidden;
    border: 1px solid #eadde0;
    border-radius: 12px;
    color: inherit;
    background: #fff;
    text-align: left;
}

.profile-document-card:disabled {
    opacity: 1;
}

.profile-document-card--clickable {
    cursor: pointer;
}

.profile-document-card__preview {
    display: grid;
    height: 84px;
    place-items: center;
    overflow: hidden;
    background: #f8edef;
}

.profile-document-card__preview .v-img {
    width: 100%;
    height: 100%;
}

.profile-document-card__content {
    display: flex;
    min-width: 0;
    justify-content: center;
    flex-direction: column;
    gap: 3px;
    padding: 9px 11px;
}

.profile-document-card__content strong {
    overflow-wrap: anywhere;
    color: var(--ink);
    font-size: 12px;
    line-height: 1.3;
}

.profile-document-card__content span {
    overflow-wrap: anywhere;
    color: var(--muted);
    font-size: 10px;
    line-height: 1.35;
}

.profile-document-card__content small {
    display: flex;
    align-items: center;
    gap: 3px;
    margin-top: 2px;
    color: var(--wine);
    font-size: 9px;
    font-weight: 700;
}

.profile-document-fallback {
    display: flex;
    width: 100%;
    height: 100%;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 4px;
    color: var(--muted);
    font-size: 9px;
    text-align: center;
}

.profile-image-preview {
    position: relative;
    width: fit-content;
    max-width: min(92vw, 640px);
    max-height: 92vh;
    margin: 0 auto;
}

.profile-image-preview--state {
    width: min(260px, 78vw);
    min-height: 220px;
    border-radius: 12px;
    background: #fff;
}

.profile-image-preview__image {
    display: block;
    width: auto;
    height: auto;
    max-width: min(92vw, 640px);
    max-height: 86vh;
    border-radius: 10px;
    object-fit: contain;
}

.profile-image-preview__close {
    top: 8px;
    right: 8px;
    z-index: 2;
    background: rgba(0, 0, 0, 0.42);
    backdrop-filter: blur(3px);
}

.profile-image-preview__state {
    display: flex;
    width: 100%;
    min-height: inherit;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 8px;
    color: var(--muted);
    font-size: 12px;
}

@media (min-width: 600px) {
    .profile-image-preview--identity .profile-image-preview__image {
        width: min(82vw, 560px);
    }

    .profile-document-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .profile-document-card--clickable {
        transition:
            border-color 0.2s ease,
            box-shadow 0.2s ease,
            transform 0.2s ease;
    }

    .profile-document-card--clickable:hover,
    .profile-document-card--clickable:focus-visible {
        border-color: #c98898;
        box-shadow: 0 7px 18px rgba(91, 29, 44, 0.1);
        outline: none;
        transform: translateY(-1px);
    }
}
</style>
