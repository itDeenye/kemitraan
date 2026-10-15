<template>
    <v-dialog v-model="dialog" max-width="1100" scrollable>
        <v-card class="rounded-xl elevation-10">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28"
                        >mdi-card-account-details-outline</v-icon
                    >
                    <span class="text-h6 font-weight-bold text-primary">
                        Detail Mitra
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

            <v-card-text class="pa-6 pt-2" style="max-height: 75vh">
                <div v-if="loading" class="d-flex justify-center py-12">
                    <v-progress-circular
                        indeterminate
                        color="primary"
                        size="48"
                    ></v-progress-circular>
                </div>

                <div v-else-if="member">
                    <div class="member-summary mt-2 mb-6">
                        <div
                            v-if="profileMedia?.url"
                            class="member-summary__avatar member-summary__avatar--clickable"
                            role="button"
                            tabindex="0"
                            aria-label="Perbesar foto profil"
                            @click="openMediaPreview(profileMedia)"
                            @keyup.enter="openMediaPreview(profileMedia)"
                        >
                            <v-avatar color="primary" size="96">
                                <v-img
                                    :src="profileMedia.url"
                                    :alt="profileMedia.title"
                                    cover
                                />
                            </v-avatar>
                            <div class="member-summary__avatar-overlay">
                                <v-icon
                                    icon="mdi-magnify-plus-outline"
                                    color="white"
                                    size="24"
                                />
                            </div>
                        </div>
                        <v-avatar
                            v-else
                            color="primary"
                            size="96"
                            class="member-summary__avatar"
                        >
                            <span class="text-h4 font-weight-bold text-white">
                                {{ member.name.charAt(0) }}
                            </span>
                        </v-avatar>

                        <div class="member-summary__content">
                            <h3 class="text-h5 font-weight-bold mb-1">
                                {{ member.name }}
                            </h3>
                            <p
                                class="text-body-2 text-medium-emphasis mb-0 member-summary__email"
                            >
                                {{ member.email || "-" }}
                            </p>
                            <div class="member-summary__badges mt-3">
                                <BaseBadge
                                    inline
                                    type="level"
                                    :value="member.level?.name"
                                    class="text-capitalize font-weight-bold"
                                />
                                <BaseBadge
                                    inline
                                    type="status"
                                    :value="member.status?.code === 1"
                                />
                            </div>
                            <span
                                v-if="profileMedia?.url"
                                class="text-caption text-medium-emphasis mt-2"
                            >
                                Klik foto untuk memperbesar
                            </span>
                        </div>

                        <div class="member-summary__quick-info">
                            <div class="member-summary__quick-item">
                                <v-icon
                                    icon="mdi-barcode"
                                    color="primary"
                                    size="20"
                                />
                                <div class="min-w-0">
                                    <span>Kode Mitra</span>
                                    <strong :title="member.code || '-'">
                                        {{ member.code || "-" }}
                                    </strong>
                                </div>
                            </div>
                            <div class="member-summary__quick-item">
                                <v-icon
                                    icon="mdi-phone-outline"
                                    color="primary"
                                    size="20"
                                />
                                <div class="min-w-0">
                                    <span>Nomor Telepon</span>
                                    <strong :title="member.mobile_phone || '-'">
                                        {{ member.mobile_phone || "-" }}
                                    </strong>
                                </div>
                            </div>
                            <div class="member-summary__quick-item">
                                <v-icon
                                    icon="mdi-calendar-check-outline"
                                    color="primary"
                                    size="20"
                                />
                                <div class="min-w-0">
                                    <span>Tanggal Bergabung</span>
                                    <strong>
                                        {{
                                            member.joined_at
                                                ? formatDate(member.joined_at)
                                                : "-"
                                        }}
                                    </strong>
                                </div>
                            </div>
                            <div class="member-summary__quick-item">
                                <v-icon
                                    icon="mdi-account-arrow-up-outline"
                                    color="primary"
                                    size="20"
                                />
                                <div class="min-w-0">
                                    <span>Upline</span>
                                    <strong
                                        :title="member.parent?.name || 'Pusat'"
                                    >
                                        {{ member.parent?.name || "Pusat" }}
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <section class="mb-6" aria-labelledby="member-documents">
                        <h3
                            id="member-documents"
                            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3"
                        >
                            <v-icon size="22" color="primary"
                                >mdi-file-image-outline</v-icon
                            >
                            Dokumen Mitra
                        </h3>
                        <v-row dense justify="center">
                            <v-col
                                v-for="media in memberDocuments"
                                :key="media.key"
                                cols="12"
                                sm="6"
                                md="4"
                            >
                                <v-card
                                    variant="flat"
                                    class="member-media-card rounded-lg border border-opacity-25 h-100"
                                    :class="{
                                        'member-media-card--clickable':
                                            media.url,
                                    }"
                                    :role="media.url ? 'button' : undefined"
                                    :tabindex="media.url ? 0 : undefined"
                                    @click="openMediaPreview(media)"
                                    @keyup.enter="openMediaPreview(media)"
                                >
                                    <div class="member-media-preview">
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
                                                    class="member-media-fallback"
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
                                            class="member-media-fallback"
                                        >
                                            <v-icon
                                                :icon="media.icon"
                                                size="36"
                                            />
                                            <span>Belum diunggah</span>
                                        </div>
                                        <div
                                            v-if="media.url"
                                            class="member-media-overlay"
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
                    </section>

                    <v-row>
                        <v-col cols="12" md="6">
                            <div class="d-flex flex-column ga-4">
                                <div>
                                    <h3
                                        class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3"
                                    >
                                        <v-icon size="22" color="primary"
                                            >mdi-account-circle-outline</v-icon
                                        >
                                        Data Personal & Legalitas
                                    </h3>
                                    <v-card
                                        variant="flat"
                                        class="rounded-lg border border-opacity-25"
                                    >
                                        <v-list
                                            density="compact"
                                            class="bg-transparent pa-2"
                                        >
                                            <v-list-item
                                                v-if="
                                                    member.username &&
                                                    member.username !==
                                                        member.code
                                                "
                                            >
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-account-badge-outline</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                    >Username</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{
                                                            member.username
                                                        }}</span
                                                    ></template
                                                >
                                            </v-list-item>

                                            <v-list-item>
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-cake-variant-outline</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                    >Tanggal
                                                    Lahir</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{
                                                            member.birth_date
                                                                ? formatDate(
                                                                      member.birth_date,
                                                                  )
                                                                : "-"
                                                        }}</span
                                                    ></template
                                                >
                                            </v-list-item>

                                            <v-list-item>
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-gender-male-female-variant</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                    >Jenis
                                                    Kelamin</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{
                                                            member.gender || "-"
                                                        }}</span
                                                    ></template
                                                >
                                            </v-list-item>

                                            <v-list-item>
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-card-account-details-outline</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                    >Jenis
                                                    Identitas</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{
                                                            member.identity
                                                                ?.type || "-"
                                                        }}</span
                                                    ></template
                                                >
                                            </v-list-item>

                                            <v-list-item>
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-identifier</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                    >Nomor
                                                    Identitas</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{
                                                            member.identity
                                                                ?.number || "-"
                                                        }}</span
                                                    ></template
                                                >
                                            </v-list-item>

                                            <v-list-item v-if="member.nib">
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-domain</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                    >NIB</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{ member.nib }}</span
                                                    ></template
                                                >
                                            </v-list-item>

                                            <v-list-item v-if="member.npwp">
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-file-document-outline</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                    >NPWP</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{ member.npwp }}</span
                                                    ></template
                                                >
                                            </v-list-item>
                                        </v-list>
                                    </v-card>
                                </div>
                            </div>
                        </v-col>

                        <v-col cols="12" md="6">
                            <div class="d-flex flex-column ga-4">
                                <div
                                    v-if="
                                        member.addresses &&
                                        member.addresses.length > 0
                                    "
                                >
                                    <h3
                                        class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3"
                                    >
                                        <v-icon size="22" color="primary"
                                            >mdi-map-marker-outline</v-icon
                                        >
                                        Data Alamat
                                    </h3>
                                    <v-card
                                        v-for="(
                                            address, idx
                                        ) in member.addresses"
                                        :key="address.id || idx"
                                        variant="flat"
                                        class="rounded-lg border border-opacity-25 mb-3"
                                    >
                                        <v-card-text class="pa-4 text-body-2">
                                            <div
                                                class="d-flex align-center justify-space-between mb-2"
                                            >
                                                <span
                                                    class="font-weight-bold text-primary"
                                                    >{{
                                                        address.label ||
                                                        "Alamat " + (idx + 1)
                                                    }}</span
                                                >
                                                <v-chip
                                                    v-if="address.is_default"
                                                    color="success"
                                                    size="x-small"
                                                    label
                                                    class="font-weight-bold"
                                                    >UTAMA</v-chip
                                                >
                                            </div>
                                            <p class="font-weight-bold mb-1">
                                                {{ address.recipient }}
                                                <span
                                                    class="text-medium-emphasis font-weight-regular"
                                                    >({{ address.phone }})</span
                                                >
                                            </p>
                                            <p
                                                class="text-grey-darken-2"
                                                style="line-height: 1.5"
                                            >
                                                {{ address.full_address }}<br />
                                                {{
                                                    formatLocation(
                                                        address.region,
                                                    )
                                                }}
                                            </p>
                                        </v-card-text>
                                    </v-card>
                                </div>

                                <div
                                    v-if="
                                        member.bank_accounts &&
                                        member.bank_accounts.length > 0
                                    "
                                >
                                    <h3
                                        class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3 mt-4"
                                    >
                                        <v-icon size="22" color="primary"
                                            >mdi-bank-outline</v-icon
                                        >
                                        Data Rekening
                                    </h3>
                                    <v-card
                                        v-for="(
                                            bank, idx
                                        ) in member.bank_accounts"
                                        :key="bank.id || idx"
                                        variant="flat"
                                        class="rounded-lg border border-opacity-25 mb-3"
                                    >
                                        <v-card-text class="pa-4 text-body-2">
                                            <div
                                                class="d-flex justify-space-between align-start mb-2"
                                            >
                                                <p
                                                    class="font-weight-bold mb-1"
                                                >
                                                    {{
                                                        bank.bank_name ||
                                                        "Bank Tidak Diketahui"
                                                    }}
                                                </p>
                                                <div class="d-flex ga-1">
                                                    <v-chip
                                                        v-if="bank.is_default"
                                                        color="primary"
                                                        size="x-small"
                                                        label
                                                        class="font-weight-bold"
                                                        >UTAMA</v-chip
                                                    >
                                                    <v-chip
                                                        v-if="!bank.is_active"
                                                        color="error"
                                                        size="x-small"
                                                        label
                                                        class="font-weight-bold"
                                                        >TIDAK AKTIF</v-chip
                                                    >
                                                </div>
                                            </div>
                                            <p
                                                class="text-h6 font-weight-bold mb-2"
                                                style="letter-spacing: 1px"
                                            >
                                                {{
                                                    bank.account_number || "---"
                                                }}
                                            </p>
                                            <p
                                                class="text-medium-emphasis mb-0"
                                            >
                                                a.n.
                                                <span
                                                    class="font-weight-medium"
                                                    >{{
                                                        bank.account_name ||
                                                        "---"
                                                    }}</span
                                                >
                                            </p>

                                        </v-card-text>
                                    </v-card>
                                </div>
                            </div>
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
                        <div class="member-media-fallback py-12">
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
import BaseBadge from "@/shared/components/BaseBadge.vue";
import type { Member } from "@/admin/types/member";
import memberService from "@/admin/services/member.service";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDate, formatLocation } = useFormatter();

const dialog = ref(false);
const loading = ref(false);
const member = ref<Member | null>(null);
const mediaPreviewDialog = ref(false);
const selectedMedia = ref<MemberMedia | null>(null);

interface MemberMedia {
    key: "profile" | "identity" | "payment-proof";
    title: string;
    url: string | null;
    icon: string;
}

const profileMedia = computed<MemberMedia>(() => ({
    key: "profile",
    title: "Foto Profil",
    url: member.value?.image || null,
    icon: "mdi-account-circle-outline",
}));

const memberDocuments = computed<MemberMedia[]>(() => {
    const documents: MemberMedia[] = [
        {
            key: "identity",
            title: "Dokumen Identitas",
            url: member.value?.identity?.image || null,
            icon: "mdi-card-account-details-outline",
        },
    ];

    if (member.value?.payment_proof_image_url) {
        documents.push({
            key: "payment-proof",
            title: "Bukti Transfer",
            url: member.value.payment_proof_image_url,
            icon: "mdi-receipt-text-check-outline",
        });
    }

    return documents;
});

const openMediaPreview = (media: MemberMedia | null) => {
    if (!media?.url) {
        return;
    }

    selectedMedia.value = media;
    mediaPreviewDialog.value = true;
};

const open = async (id: number) => {
    dialog.value = true;
    loading.value = true;
    member.value = null;
    mediaPreviewDialog.value = false;
    selectedMedia.value = null;

    try {
        member.value = await memberService.getMember(id);
    } catch (error) {
        console.error("Gagal mengambil detail mitra", error);
        alert("Gagal mengambil detail mitra");
        dialog.value = false;
    } finally {
        loading.value = false;
    }
};

const close = () => {
    dialog.value = false;
    mediaPreviewDialog.value = false;
    selectedMedia.value = null;

    setTimeout(() => {
        member.value = null;
    }, 300);
};

defineExpose({ open, close });
</script>

<style scoped>
.member-summary {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    padding: 18px 20px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 14px;
    background: rgb(var(--v-theme-surface-variant));
}

.member-summary__avatar {
    position: relative;
    flex: 0 0 auto;
}

.member-summary__avatar--clickable {
    width: 96px;
    height: 96px;
    overflow: hidden;
    border-radius: 50%;
    cursor: pointer;
    outline: none;
}

.member-summary__avatar-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.42);
    opacity: 0;
    transition: opacity 0.2s ease;
}

.member-summary__avatar--clickable:hover .member-summary__avatar-overlay,
.member-summary__avatar--clickable:focus-visible
    .member-summary__avatar-overlay {
    opacity: 1;
}

.member-summary__content {
    display: flex;
    min-width: 0;
    flex: 1 1 240px;
    flex-direction: column;
    align-items: flex-start;
}

.member-summary__email {
    max-width: 100%;
    overflow-wrap: anywhere;
}

.member-summary__badges {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.member-summary__quick-info {
    display: grid;
    width: min(100%, 460px);
    flex: 0 1 460px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin-left: auto;
}

.member-summary__quick-item {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 10px;
    padding: 9px 11px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 10px;
    background: rgb(var(--v-theme-surface));
}

.member-summary__quick-item > div {
    display: flex;
    min-width: 0;
    flex-direction: column;
}

.member-summary__quick-item span {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 11px;
    line-height: 1.25;
}

.member-summary__quick-item strong {
    overflow: hidden;
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
    font-size: 12px;
    line-height: 1.45;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.member-media-card {
    overflow: hidden;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        transform 0.2s ease;
}

.member-media-card--clickable {
    cursor: pointer;
}

.member-media-card--clickable:hover,
.member-media-card--clickable:focus-visible {
    border-color: rgb(var(--v-theme-primary)) !important;
    box-shadow: 0 8px 20px rgba(53, 22, 28, 0.12);
    outline: none;
    transform: translateY(-2px);
}

.member-media-preview {
    position: relative;
    height: 150px;
    overflow: hidden;
    background: rgb(var(--v-theme-surface-variant));
}

.member-media-preview .v-img {
    width: 100%;
    height: 100%;
}

.member-media-fallback {
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

.member-media-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.38);
    opacity: 0;
    transition: opacity 0.2s ease;
}

.member-media-card--clickable:hover .member-media-overlay,
.member-media-card--clickable:focus-visible .member-media-overlay {
    opacity: 1;
}

.media-preview-dialog {
    min-height: 240px;
    background: rgb(var(--v-theme-surface-variant));
}

@media (max-width: 900px) {
    .member-summary__quick-info {
        width: 100%;
        flex-basis: 100%;
        margin-left: 0;
    }
}

@media (max-width: 600px) {
    .member-summary {
        flex-direction: column;
        gap: 14px;
        padding: 18px 16px;
        text-align: center;
    }

    .member-summary__content {
        align-items: center;
    }

    .member-summary__badges {
        justify-content: center;
    }

    .member-summary__avatar-overlay {
        top: auto;
        left: auto;
        width: 36px;
        height: 36px;
        border-radius: 50% 0 50% 0;
        opacity: 1;
    }

    .member-media-preview {
        height: 175px;
    }

    .member-media-overlay {
        top: auto;
        left: auto;
        width: 42px;
        height: 42px;
        border-radius: 12px 0 0;
        opacity: 1;
    }
}
</style>
