<template>
    <div :class="embedded ? 'registration-module' : 'screen-body'">
        <div v-if="!embedded" class="filter-chips mb-4">
            <span
                class="filter-chip"
                :class="{ active: activeTab === 'form' }"
                @click="activeTab = 'form'"
                >Form Pendaftaran</span
            >
            <span
                class="filter-chip"
                :class="{ active: activeTab === 'list' }"
                @click="switchToList"
                >Riwayat Pendaftaran ({{ registrationCount }})</span
            >
            <span
                v-if="isDistributor"
                class="filter-chip"
                :class="{ active: activeTab === 'approval' }"
                @click="switchToApprovals"
                >Approval Pendaftaran ({{ approvalCount }})</span
            >
        </div>

        <template v-if="activeTab === 'form'">
            <div v-if="loadingOptions" class="empty-state">
                <v-progress-circular
                    indeterminate
                    color="var(--wine)"
                    size="32"
                />
                <span class="soft-label mt-2"
                    >Memuat formulir pendaftaran...</span
                >
            </div>

            <template v-else-if="options">
                <v-form
                    ref="regFormRef"
                    v-model="isFormValid"
                    @submit.prevent="submitRegistration"
                >
                    <div class="section-heading mt-2">
                        <h3>Informasi Jaringan</h3>
                    </div>
                    <div class="card" style="padding: 16px">
                        <v-row dense>
                            <v-col cols="12" md="6">
                                <v-select
                                    :model-value="[options.sponsor]"
                                    label="Pendaftar"
                                    variant="outlined"
                                    density="compact"
                                    disabled
                                    menu-icon=""
                                    class="mb-2"
                                >
                                    <template v-slot:selection="{ item }">
                                        <div
                                            class="flex items-center gap-2 w-full"
                                        >
                                            <span>{{ item.raw.name }}</span>
                                            <span
                                                class="text-[12px] text-[var(--muted)]"
                                                >{{ item.raw.code }}</span
                                            >
                                            <BaseBadge
                                                type="level"
                                                :value="item.raw.level_code"
                                                chip-class="!h-auto !py-[2px] !px-2 !text-[10px]"
                                                inline
                                            />
                                        </div>
                                    </template>
                                </v-select>
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.level_id"
                                    :items="options.levels"
                                    item-title="name"
                                    item-value="id"
                                    label="Level Mitra yang Didaftarkan *"
                                    placeholder="Pilih Level Mitra"
                                    persistent-placeholder
                                    variant="outlined"
                                    density="compact"
                                    :rules="[required('Level Mitra')]"
                                    :error-messages="errors.level_id"
                                    class="mb-2"
                                />
                            </v-col>
                            <v-col v-if="canChooseAgentUpline" cols="12">
                                <v-autocomplete
                                    v-model="form.upline_member_id"
                                    :items="options.agents"
                                    :item-title="agentTitle"
                                    item-value="id"
                                    label="Agen Utama untuk Reseller (Opsional)"
                                    placeholder="Kosongkan untuk langsung di bawah Distributor"
                                    variant="outlined"
                                    density="compact"
                                    clearable
                                    :error-messages="errors.upline_member_id"
                                    no-data-text="Belum ada Agen Utama aktif pada jaringan Distributor"
                                    class="mb-2"
                                />
                                <div class="soft-label mt-[-4px] mb-2">
                                    Kosongkan agar Reseller langsung di bawah
                                    Distributor, atau pilih Agen Utama tujuan.
                                </div>
                            </v-col>
                        </v-row>
                    </div>

                    <div class="section-heading mt-4">
                        <h3>Data Pribadi</h3>
                    </div>
                    <div class="card" style="padding: 16px">
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="form.name"
                                    label="Nama Lengkap *"
                                    placeholder="Masukkan nama lengkap sesuai KTP"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[required('Nama Lengkap')]"
                                    :error-messages="errors.name"
                                    class="mb-2"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.mobile_phone"
                                    label="Nomor Telepon *"
                                    placeholder="08xxxxxxxxxx"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[
                                        required('Nomor Telepon'),
                                        numeric('Nomor Telepon'),
                                        validPhone('Nomor Telepon'),
                                    ]"
                                    :error-messages="errors.mobile_phone"
                                    class="mb-2"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.email"
                                    label="Email *"
                                    type="email"
                                    placeholder="email@contoh.com"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[
                                        required('Email'),
                                        validEmail('Email'),
                                    ]"
                                    :error-messages="errors.email"
                                    class="mb-2"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.gender"
                                    label="Jenis Kelamin *"
                                    variant="outlined"
                                    density="compact"
                                    :items="options.genders"
                                    :rules="[required('Jenis Kelamin')]"
                                    :error-messages="errors.gender"
                                    class="mb-2"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.birth_date"
                                    label="Tanggal Lahir *"
                                    type="date"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[required('Tanggal Lahir')]"
                                    :error-messages="errors.birth_date"
                                    class="mb-2"
                                />
                            </v-col>
                        </v-row>
                    </div>

                    <div class="section-heading mt-4">
                        <h3>Alamat</h3>
                    </div>
                    <div class="card" style="padding: 16px">
                        <v-row dense>
                            <v-col cols="12" md="6">
                                <v-autocomplete
                                    v-model="form.province_id"
                                    label="Provinsi *"
                                    placeholder="Pilih Provinsi"
                                    variant="outlined"
                                    density="compact"
                                    :items="provinces"
                                    item-title="name"
                                    item-value="id"
                                    :loading="loadingProvinces"
                                    :rules="[required('Provinsi')]"
                                    :error-messages="errors.province_id"
                                    @update:model-value="handleProvinceChange"
                                    class="mb-2"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-autocomplete
                                    v-model="form.city_id"
                                    label="Kota/Kabupaten *"
                                    placeholder="Pilih Kota/Kabupaten"
                                    variant="outlined"
                                    density="compact"
                                    :items="cities"
                                    item-title="name"
                                    item-value="id"
                                    :loading="loadingCities"
                                    :disabled="!form.province_id"
                                    :rules="[required('Kota/Kabupaten')]"
                                    :error-messages="errors.city_id"
                                    @update:model-value="handleCityChange"
                                    class="mb-2"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-autocomplete
                                    v-model="form.district_id"
                                    label="Kecamatan *"
                                    placeholder="Pilih Kecamatan"
                                    variant="outlined"
                                    density="compact"
                                    :items="districts"
                                    item-title="name"
                                    item-value="id"
                                    :loading="loadingDistricts"
                                    :disabled="!form.city_id"
                                    :rules="[required('Kecamatan')]"
                                    :error-messages="errors.district_id"
                                    @update:model-value="handleDistrictChange"
                                    class="mb-2"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-autocomplete
                                    v-model="form.subdistrict_id"
                                    label="Kelurahan/Desa *"
                                    placeholder="Pilih Kelurahan/Desa"
                                    variant="outlined"
                                    density="compact"
                                    :items="subdistricts"
                                    item-title="name"
                                    item-value="id"
                                    :loading="loadingSubdistricts"
                                    :disabled="!form.district_id"
                                    :rules="[required('Kelurahan/Desa')]"
                                    :error-messages="errors.subdistrict_id"
                                    class="mb-2"
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-textarea
                                    v-model="form.address"
                                    label="Alamat Detail *"
                                    placeholder="Masukkan nama jalan, nomor, RT/RW..."
                                    rows="2"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[required('Alamat Detail')]"
                                    :error-messages="errors.address"
                                    class="mb-2"
                                />
                            </v-col>
                        </v-row>
                    </div>

                    <div class="section-heading mt-4">
                        <h3>Identitas</h3>
                    </div>
                    <div class="card" style="padding: 16px">
                        <v-row dense>
                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="form.identity_type"
                                    label="Jenis Identitas *"
                                    variant="outlined"
                                    density="compact"
                                    :items="identityOptions"
                                    :rules="[required('Jenis Identitas')]"
                                    :error-messages="errors.identity_type"
                                    class="mb-2"
                                />
                            </v-col>
                            <v-col cols="12" md="8">
                                <v-text-field
                                    v-model="form.identity_no"
                                    label="Nomor Identitas *"
                                    :placeholder="identitySettings.hint"
                                    :hint="identitySettings.hint"
                                    persistent-hint
                                    :inputmode="
                                        form.identity_type === 'PASPOR'
                                            ? 'text'
                                            : 'numeric'
                                    "
                                    variant="outlined"
                                    density="compact"
                                    :counter="identitySettings.maxLength"
                                    :maxlength="identitySettings.maxLength"
                                    :rules="[
                                        required('Nomor Identitas'),
                                        validateIdentityNumber,
                                    ]"
                                    :error-messages="errors.identity_no"
                                    class="mb-2"
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="form.nib"
                                    label="NIB"
                                    placeholder="Masukkan 13 digit NIB (opsional)"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[numeric('NIB')]"
                                    :error-messages="errors.nib"
                                    class="mb-2"
                                />
                            </v-col>
                        </v-row>
                    </div>

                    <div class="section-heading mt-4">
                        <h3>Dokumen Wajib</h3>
                    </div>
                    <div class="card registration-media-card">
                        <p class="registration-media-intro">
                            Lengkapi ketiga dokumen berikut sebelum mengirim
                            pendaftaran. Pastikan gambar jelas dan mudah dibaca.
                        </p>
                        <div class="registration-upload-grid">
                            <div class="registration-upload-field">
                                <MediaUpload
                                    v-model="form.image_url"
                                    collection="profile"
                                    :size="120"
                                    :rounded="false"
                                    fallback-icon="mdi-account-outline"
                                    accept="image/jpeg,image/png,image/webp"
                                    @uploading="mediaUploads.profile = $event"
                                >
                                    <template #preview="{ url }">
                                        <div
                                            class="registration-document-preview"
                                        >
                                            <v-img
                                                v-if="url"
                                                :src="url"
                                                cover
                                            />
                                            <v-icon
                                                v-else
                                                icon="mdi-account-outline"
                                                color="var(--wine)"
                                                size="42"
                                            />
                                        </div>
                                    </template>
                                </MediaUpload>
                                <strong>Foto Profil *</strong>
                                <span>JPG, PNG, atau WEBP</span>
                                <span
                                    v-if="errors.image_url?.length"
                                    class="registration-upload-error"
                                >
                                    {{ errors.image_url[0] }}
                                </span>
                            </div>

                            <div class="registration-upload-field">
                                <MediaUpload
                                    v-model="form.identity_image_url"
                                    collection="general"
                                    :size="120"
                                    :rounded="false"
                                    fallback-icon="mdi-card-account-details-outline"
                                    accept="image/jpeg,image/png,image/webp"
                                    @uploading="mediaUploads.identity = $event"
                                >
                                    <template #preview="{ url }">
                                        <div
                                            class="registration-document-preview"
                                        >
                                            <v-img
                                                v-if="url"
                                                :src="url"
                                                cover
                                            />
                                            <v-icon
                                                v-else
                                                icon="mdi-card-account-details-outline"
                                                color="var(--wine)"
                                                size="42"
                                            />
                                        </div>
                                    </template>
                                </MediaUpload>
                                <strong>Dokumen Identitas *</strong>
                                <span>Foto dokumen harus terbaca</span>
                                <span
                                    v-if="errors.identity_image_url?.length"
                                    class="registration-upload-error"
                                >
                                    {{ errors.identity_image_url[0] }}
                                </span>
                            </div>

                            <div class="registration-upload-field">
                                <MediaUpload
                                    v-model="form.payment_proof_image_url"
                                    collection="payment_proof"
                                    :size="120"
                                    :rounded="false"
                                    fallback-icon="mdi-receipt-text-check-outline"
                                    accept="image/jpeg,image/png,image/webp"
                                    @uploading="mediaUploads.payment = $event"
                                >
                                    <template #preview="{ url }">
                                        <div
                                            class="registration-document-preview"
                                        >
                                            <v-img
                                                v-if="url"
                                                :src="url"
                                                cover
                                            />
                                            <v-icon
                                                v-else
                                                icon="mdi-receipt-text-check-outline"
                                                color="var(--wine)"
                                                size="42"
                                            />
                                        </div>
                                    </template>
                                </MediaUpload>
                                <strong>Bukti Transfer *</strong>
                                <span>Foto bukti pembayaran</span>
                                <span
                                    v-if="
                                        errors.payment_proof_image_url?.length
                                    "
                                    class="registration-upload-error"
                                >
                                    {{ errors.payment_proof_image_url[0] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="section-heading mt-4">
                        <h3>Data Bank</h3>
                    </div>
                    <div class="card" style="padding: 16px">
                        <v-row dense>
                            <v-col cols="12" md="6">
                                <v-autocomplete
                                    v-model="form.bank_id"
                                    label="Bank *"
                                    placeholder="Pilih Bank"
                                    variant="outlined"
                                    density="compact"
                                    :items="banks"
                                    item-title="name"
                                    item-value="id"
                                    :loading="loadingBanks"
                                    clearable
                                    :rules="[required('Bank')]"
                                    :error-messages="errors.bank_id"
                                    class="mb-2"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.bank_account_number"
                                    label="Nomor Rekening *"
                                    placeholder="Masukkan nomor rekening"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[
                                        required('Nomor Rekening'),
                                        numeric('Nomor Rekening'),
                                    ]"
                                    :error-messages="errors.bank_account_number"
                                    class="mb-2"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.bank_account_name"
                                    label="Nama Pemilik Rekening *"
                                    placeholder="Masukkan nama pemilik rekening"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[required('Nama Pemilik Rekening')]"
                                    :error-messages="errors.bank_account_name"
                                    class="mb-2"
                                />
                            </v-col>
                        </v-row>
                    </div>

                    <button
                        class="primary-button block mt-6"
                        type="submit"
                        :disabled="
                            !isFormValid ||
                            !hasRequiredUploads ||
                            isUploadingMedia ||
                            submitting
                        "
                    >
                        <template v-if="submitting">Mendaftarkan...</template>
                        <template v-else>
                            <v-icon icon="mdi-check-circle" size="14" />
                            Daftarkan Mitra
                        </template>
                    </button>
                </v-form>
            </template>
        </template>

        <template v-if="activeTab === 'list'">
            <div v-if="loadingList" class="empty-state">
                <v-progress-circular
                    indeterminate
                    color="var(--wine)"
                    size="32"
                />
                <span class="soft-label mt-2"
                    >Memuat riwayat pendaftaran...</span
                >
            </div>

            <div
                v-else-if="registrations.length === 0"
                class="card empty-state"
            >
                <v-icon
                    icon="mdi-clipboard-text-outline"
                    size="40"
                    color="var(--muted)"
                />
                <span class="soft-label mt-2"
                    >Belum ada pengajuan pendaftaran</span
                >
            </div>

            <div v-else class="card list-card">
                <div
                    v-for="reg in registrations"
                    :key="reg.id"
                    class="list-row cursor-pointer"
                    @click="
                        router.push(
                            `/member/network/registrations/${encodeRouteId(reg.id)}`,
                        )
                    "
                >
                    <div class="row-icon">
                        <v-icon icon="mdi-account-plus-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong class="block mb-0.5">{{
                            reg.applicant.name
                        }}</strong>
                        <div
                            class="text-[13px] text-[var(--muted)] flex items-center gap-1"
                        >
                            <span v-if="reg.applicant.username">{{
                                reg.applicant.username
                            }}</span>
                            <span v-if="reg.applicant.username">&middot;</span>
                            <BaseBadge
                                type="level"
                                :value="reg.level.code"
                                chip-class="!h-auto !py-[2px] !px-2 !text-[10px]"
                                inline
                            />
                        </div>
                    </div>
                    <div class="row-side">
                        <span
                            class="badge"
                            :class="{
                                green: reg.status.code === 'approved',
                                orange: reg.status.code === 'requested',
                            }"
                        >
                            {{ reg.status.label }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="hasMoreRegistrations" class="text-center mt-4">
                <button
                    class="pagination-btn"
                    :disabled="loadingMore"
                    @click="loadMoreRegistrations"
                >
                    <v-progress-circular
                        v-if="loadingMore"
                        indeterminate
                        size="14"
                        width="2"
                        class="mr-2"
                    />
                    {{ loadingMore ? "Memuat..." : "Muat lainnya" }}
                </button>
            </div>
        </template>

        <template v-if="activeTab === 'approval' && isDistributor">
            <div v-if="loadingApprovals" class="empty-state">
                <v-progress-circular
                    indeterminate
                    color="var(--wine)"
                    size="32"
                />
                <span class="soft-label mt-2"
                    >Memuat approval pendaftaran...</span
                >
            </div>

            <div
                v-else-if="registrationApprovals.length === 0"
                class="card empty-state"
            >
                <v-icon
                    icon="mdi-clipboard-check-outline"
                    size="42"
                    color="var(--muted)"
                />
                <h4>Tidak ada approval pendaftaran</h4>
                <p>
                    Pendaftaran Reseller oleh Agen Utama yang memerlukan
                    persetujuan Anda akan tampil di sini.
                </p>
            </div>

            <div v-else class="card list-card">
                <div
                    v-for="registration in registrationApprovals"
                    :key="registration.id"
                    class="list-row approval-registration-row"
                    role="button"
                    tabindex="0"
                    @click="openApprovalDetail(registration.id)"
                    @keyup.enter="openApprovalDetail(registration.id)"
                >
                    <div class="row-icon approval-registration-row__icon">
                        <v-icon icon="mdi-account-clock-outline" size="19" />
                    </div>
                    <div class="row-main">
                        <strong class="block mb-1">
                            {{ registration.applicant.name }}
                        </strong>
                        <div class="approval-registration-row__meta">
                            <BaseBadge
                                type="level"
                                :value="registration.level.code"
                                chip-class="!h-auto !py-[2px] !px-2 !text-[10px]"
                                inline
                            />
                            <span>
                                Upline:
                                {{ registration.sponsor?.name || "-" }}
                            </span>
                            <!-- <span>
                                {{ formatDate(registration.submitted_at) }}
                            </span> -->
                        </div>
                    </div>
                    <div class="approval-registration-row__action">
                        <span class="badge orange">Perlu Approval</span>
                    </div>
                </div>
            </div>

            <div v-if="hasMoreApprovals" class="text-center mt-4">
                <button
                    class="pagination-btn"
                    :disabled="loadingMoreApprovals"
                    @click="loadMoreApprovals"
                >
                    <v-progress-circular
                        v-if="loadingMoreApprovals"
                        indeterminate
                        size="14"
                        width="2"
                        class="mr-2"
                    />
                    {{ loadingMoreApprovals ? "Memuat..." : "Muat lainnya" }}
                </button>
            </div>
        </template>

        <MobileConfirm ref="confirmDialogRef" />
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/shared/stores/auth";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useRegionCascade } from "@/shared/composables/useRegionCascade";
import { useFormatter } from "@/shared/composables/useFormatter";
import referenceService from "@/shared/services/reference.service";
import type { ReferenceBank } from "@/shared/services/reference.service";
import networkService from "@/member/services/network.service";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import MediaUpload from "@/shared/components/MediaUpload.vue";
import MobileConfirm from "@/member/components/MobileConfirm.vue";
import { encodeRouteId } from "@/shared/utils/route-id";
import {
    identityNumberRule,
    identityNumberSettings,
    memberIdentityOptions,
} from "@/shared/utils/member-identity";
import type {
    RegistrationOptions,
    RegistrationListItem,
    RegistrationListParams,
    DataTablePagination,
} from "@/member/types/network";

type RegistrationTab = "form" | "list" | "approval";

const props = withDefaults(
    defineProps<{
        embedded?: boolean;
        tab?: RegistrationTab;
        searchQuery?: string;
    }>(),
    {
        embedded: false,
        tab: "form",
        searchQuery: "",
    },
);
const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const snackbar = useSnackbarStore();
const { formatDate } = useFormatter();
const isDistributor = computed(
    () => authStore.user?.member?.level?.code === "DST",
);
const requestedTab = Array.isArray(route.query.tab)
    ? route.query.tab[0]
    : route.query.tab;
const activeTab = ref<RegistrationTab>(
    props.embedded
        ? props.tab
        : requestedTab === "approval" && isDistributor.value
          ? "approval"
          : requestedTab === "list"
            ? "list"
            : "form",
);
const regFormRef = ref<any>(null);
const confirmDialogRef = ref<InstanceType<typeof MobileConfirm> | null>(null);
const isFormValid = ref(false);
const loadingOptions = ref(false);
const submitting = ref(false);
const errors = reactive<Record<string, string[]>>({});
const options = ref<RegistrationOptions | null>(null);
const banks = ref<ReferenceBank[]>([]);
const loadingBanks = ref(false);
const mediaUploads = reactive({
    profile: false,
    identity: false,
    payment: false,
});

const {
    provinces,
    cities,
    districts,
    subdistricts,
    loadingProvinces,
    loadingCities,
    loadingDistricts,
    loadingSubdistricts,
    fetchProvinces,
    onProvinceChange,
    onCityChange,
    onDistrictChange,
} = useRegionCascade();

const loadingList = ref(false);
const loadingMore = ref(false);
const registrations = ref<RegistrationListItem[]>([]);
const listPagination = ref<DataTablePagination | null>(null);
const registrationCount = ref(0);
const currentPage = ref(1);
const hasMoreRegistrations = computed(
    () => listPagination.value && listPagination.value.next !== 0,
);
const loadingApprovals = ref(false);
const loadingMoreApprovals = ref(false);
const registrationApprovals = ref<RegistrationListItem[]>([]);
const approvalPagination = ref<DataTablePagination | null>(null);
const approvalCount = ref(0);
const hasMoreApprovals = computed(
    () => approvalPagination.value && approvalPagination.value.next !== 0,
);
let embeddedSearchTimer: ReturnType<typeof setTimeout> | null = null;

const form = reactive({
    level_id: null as number | null,
    upline_member_id: null as number | null,
    name: "",
    email: "",
    mobile_phone: "",
    gender: "Laki-laki",
    birth_date: "",
    address: "",
    province_id: null as string | null,
    city_id: null as string | null,
    district_id: null as string | null,
    subdistrict_id: null as number | null,
    bank_id: null as number | null,
    bank_account_name: "",
    bank_account_number: "",
    identity_type: "KTP",
    identity_no: "",
    identity_image_url: "",
    image_url: "",
    payment_proof_image_url: "",
    nib: "",
});
const selectedLevel = computed(() =>
    options.value?.levels.find((level) => level.id === form.level_id),
);
const identityOptions = computed(() =>
    memberIdentityOptions(options.value?.identity_types ?? []),
);
const identitySettings = computed(() =>
    identityNumberSettings(form.identity_type),
);
const validateIdentityNumber = (value: unknown) =>
    identityNumberRule(form.identity_type, value);
const canChooseAgentUpline = computed(
    () =>
        options.value?.sponsor.level_code === "DST" &&
        selectedLevel.value?.code === "RSL",
);
const hasRequiredUploads = computed(
    () =>
        !!form.image_url &&
        !!form.identity_image_url &&
        !!form.payment_proof_image_url,
);
const isUploadingMedia = computed(() =>
    Object.values(mediaUploads).some(Boolean),
);

Object.keys(form).forEach((key) => {
    watch(
        () => form[key as keyof typeof form],
        () => {
            if (errors[key]) {
                delete errors[key];
            }
        },
    );
});

const required = (label: string) => (v: any) => !!v || `${label} wajib diisi`;
const numeric = (label: string) => (v: any) =>
    !v || /^\d+$/.test(v) || `${label} hanya boleh berisi angka`;
const validPhone = (label: string) => (v: any) =>
    !v || /^(08|628)/.test(v) || `${label} harus diawali 08 atau 628`;
const validEmail = (label: string) => (v: any) =>
    !v || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || `${label} tidak valid`;
const agentTitle = (agent: { name: string; code: string }) =>
    `${agent.name} - ${agent.code}`;

watch(
    () => form.level_id,
    () => {
        form.upline_member_id = null;
        delete errors.upline_member_id;
    },
);

function handleProvinceChange(val: any) {
    form.city_id = null;
    form.district_id = null;
    form.subdistrict_id = null;
    onProvinceChange(val);
}

function handleCityChange(val: any) {
    form.district_id = null;
    form.subdistrict_id = null;
    onCityChange(val);
}

function handleDistrictChange(val: any) {
    form.subdistrict_id = null;
    onDistrictChange(val);
}

async function loadOptions() {
    loadingOptions.value = true;
    try {
        const [opts, bankList] = await Promise.all([
            networkService.getRegistrationOptions(),
            referenceService.getBanks(),
        ]);
        options.value = opts;
        banks.value = bankList;
        await fetchProvinces();
    } catch (e: any) {
        console.error("Failed to load registration options:", e);
        snackbar.showError("Gagal memuat formulir pendaftaran");
    } finally {
        loadingOptions.value = false;
    }
}

async function loadRegistrations(page = 1, append = false) {
    if (append) {
        loadingMore.value = true;
    } else {
        loadingList.value = true;
    }
    try {
        const data = await networkService.getRegistrations({
            page,
            limit: 10,
            search: props.searchQuery || undefined,
        });
        if (append) {
            registrations.value.push(...data.results);
        } else {
            registrations.value = data.results;
        }
        listPagination.value = data.pagination;
        if (!props.searchQuery) {
            registrationCount.value = data.pagination.total_data;
        }
        currentPage.value = data.pagination.current;
    } catch (e: any) {
        console.error("Failed to load registrations:", e);
    } finally {
        loadingList.value = false;
        loadingMore.value = false;
    }
}

function loadMoreRegistrations() {
    if (listPagination.value && listPagination.value.next !== 0) {
        loadRegistrations(listPagination.value.next, true);
    }
}

function switchToList() {
    activeTab.value = "list";
    if (registrations.value.length === 0) {
        loadRegistrations();
    }
}

async function loadRegistrationApprovals(page = 1, append = false) {
    if (!isDistributor.value) {
        return;
    }

    if (append) {
        loadingMoreApprovals.value = true;
    } else {
        loadingApprovals.value = true;
    }

    try {
        const data = await networkService.getRegistrationApprovals({
            page,
            limit: 10,
            search: props.searchQuery || undefined,
            sort: "-id",
            "filter[distributor_approval_status]": "pending",
        });

        if (append) {
            registrationApprovals.value.push(...data.results);
        } else {
            registrationApprovals.value = data.results;
        }

        approvalPagination.value = data.pagination;
        if (!props.searchQuery) {
            approvalCount.value = data.pagination.total_data;
        }
    } catch (error: any) {
        console.error("Failed to load registration approvals:", error);
        snackbar.showMessage(
            error.response?.data?.message ||
                "Gagal memuat approval pendaftaran.",
            "error",
        );
    } finally {
        loadingApprovals.value = false;
        loadingMoreApprovals.value = false;
    }
}

function switchToApprovals() {
    activeTab.value = "approval";
    void loadRegistrationApprovals();
}

function loadMoreApprovals() {
    if (approvalPagination.value && approvalPagination.value.next !== 0) {
        void loadRegistrationApprovals(approvalPagination.value.next, true);
    }
}

function openApprovalDetail(id: number) {
    router.push(`/member/network/registration-approvals/${encodeRouteId(id)}`);
}

watch(
    () => props.tab,
    (tab) => {
        if (!props.embedded) {
            return;
        }

        activeTab.value =
            tab === "approval" && !isDistributor.value ? "form" : tab;
        if (activeTab.value === "list") {
            void loadRegistrations();
        } else if (activeTab.value === "approval") {
            void loadRegistrationApprovals();
        }
    },
);

watch(
    () => props.searchQuery,
    () => {
        if (!props.embedded || activeTab.value === "form") {
            return;
        }

        if (embeddedSearchTimer) {
            clearTimeout(embeddedSearchTimer);
        }
        embeddedSearchTimer = setTimeout(() => {
            if (activeTab.value === "list") {
                void loadRegistrations();
            } else if (activeTab.value === "approval") {
                void loadRegistrationApprovals();
            }
        }, 400);
    },
);

async function submitRegistration() {
    if (isUploadingMedia.value) {
        snackbar.showMessage("Tunggu hingga proses upload selesai.", "info");

        return;
    }

    const requiredUploads = [
        ["image_url", form.image_url, "Foto Profil wajib diunggah"],
        [
            "identity_image_url",
            form.identity_image_url,
            "Dokumen identitas wajib diunggah",
        ],
        [
            "payment_proof_image_url",
            form.payment_proof_image_url,
            "Bukti Transfer wajib diunggah",
        ],
    ] as const;

    requiredUploads.forEach(([field, value, message]) => {
        if (!value) {
            errors[field] = [message];
        }
    });

    if (!hasRequiredUploads.value) {
        snackbar.showMessage(
            "Lengkapi seluruh dokumen wajib sebelum mendaftar.",
            "error",
        );

        return;
    }

    const { valid } = await regFormRef.value?.validate();
    if (!valid) return;

    if (!form.level_id) {
        errors.level_id = ["Level Mitra wajib diisi"];
        snackbar.showMessage(
            "Silakan pilih Level Mitra yang didaftarkan.",
            "error",
        );
        return;
    }

    if (confirmDialogRef.value) {
        const selectedLevelName = selectedLevel.value?.name || "Mitra";
        const uplineAgent =
            canChooseAgentUpline.value && form.upline_member_id
                ? options.value?.agents?.find(
                      (a) => a.id === form.upline_member_id,
                  )
                : null;
        const sponsor = options.value?.sponsor;

        const confirmMessage = `
            <div style="font-size: 13px; color: #4b5563; margin-bottom: 12px;">
                Apakah Anda yakin ingin mendaftarkan mitra baru dengan data berikut?
            </div>
            <div style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px 14px; text-align: left; font-size: 13px; display: flex; flex-direction: column; gap: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #e5e7eb; padding-bottom: 6px;">
                    <span style="color: #6b7280;">Level Mitra</span>
                    <span style="font-weight: 700; color: var(--wine, #940923);">${selectedLevelName}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px dashed #e5e7eb; padding-bottom: 6px;">
                    <span style="color: #6b7280;">Nama Lengkap</span>
                    <span style="font-weight: 600; color: #111827; text-align: right; max-width: 170px; word-break: break-word;">${form.name}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #e5e7eb; padding-bottom: 6px;">
                    <span style="color: #6b7280;">Nomor Telepon</span>
                    <span style="color: #111827; font-weight: 500;">${form.mobile_phone}</span>
                </div>
                ${
                    uplineAgent
                        ? `<div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px dashed #e5e7eb; padding-bottom: 6px;">
                            <span style="color: #6b7280;">Agen Utama Upline</span>
                            <div style="text-align: right; max-width: 170px;">
                                <div style="font-weight: 600; color: #111827; word-break: break-word;">${uplineAgent.name}</div>
                                ${uplineAgent.code ? `<div style="font-size: 11px; color: #6b7280;">${uplineAgent.code}</div>` : ""}
                            </div>
                           </div>`
                        : ""
                }
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <span style="color: #6b7280;">Pendaftar / Sponsor</span>
                    <div style="text-align: right; max-width: 170px;">
                        <div style="font-weight: 600; color: #374151; word-break: break-word;">${sponsor?.name || "-"}</div>
                        ${sponsor?.code ? `<div style="font-size: 11px; color: #6b7280;">${sponsor.code}</div>` : ""}
                    </div>
                </div>
            </div>
            <div style="font-size: 11px; color: #9ca3af; margin-top: 10px;">
                Pastikan data dan dokumen pendaftaran telah sesuai sebelum melanjutkan.
            </div>
        `;

        const isConfirmed = await confirmDialogRef.value.open({
            title: "Konfirmasi Pendaftaran",
            message: confirmMessage,
            confirmText: "Ya, Daftarkan",
            cancelText: "Batal",
            confirmColor: "wine",
            icon: "mdi-account-plus",
        });

        if (!isConfirmed) return;
    }

    submitting.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        const response = await networkService.storeRegistration({
            level_id: form.level_id as number,
            upline_member_id: canChooseAgentUpline.value
                ? form.upline_member_id
                : null,
            name: form.name,
            email: form.email || null,
            mobile_phone: form.mobile_phone,
            gender: form.gender,
            birth_date: form.birth_date,
            address: form.address,
            province_id: form.province_id || "",
            city_id: form.city_id || "",
            district_id: form.district_id || "",
            subdistrict_id: form.subdistrict_id,
            country_id: 1,
            bank_id: form.bank_id,
            bank_account_name: form.bank_account_name || null,
            bank_account_number: form.bank_account_number || null,
            identity_type: form.identity_type,
            identity_no: form.identity_no,
            identity_image_url: form.identity_image_url,
            image_url: form.image_url,
            payment_proof_image_url: form.payment_proof_image_url,
            nib: form.nib || null,
        });

        resetForm();
        router.push(
            `/member/network/registrations/${encodeRouteId(response.id)}?success=1`,
        );
    } catch (e: any) {
        const msg =
            e.response?.data?.message ||
            "Gagal mendaftarkan mitra. Silakan coba lagi.";

        // Show field-level errors from validation
        const serverErrors = e.response?.data?.errors;
        if (serverErrors) {
            Object.assign(errors, serverErrors);
            snackbar.showMessage(msg, "error");
        } else {
            snackbar.showMessage(msg, "error");
        }
    } finally {
        submitting.value = false;
    }
}

function resetForm() {
    Object.assign(form, {
        level_id: null,
        upline_member_id: null,
        name: "",
        email: "",
        mobile_phone: "",
        gender: "Laki-laki",
        birth_date: "",
        address: "",
        province_id: null,
        city_id: null,
        district_id: null,
        subdistrict_id: null,
        bank_id: null,
        bank_account_name: "",
        bank_account_number: "",
        bank_city: "",
        bank_branch: "",
        identity_type: "KTP",
        identity_no: "",
        identity_image_url: "",
        image_url: "",
        payment_proof_image_url: "",
        nib: "",
    });
    Object.assign(mediaUploads, {
        profile: false,
        identity: false,
        payment: false,
    });
    regFormRef.value?.resetValidation();
}

onMounted(() => {
    loadOptions();
    loadRegistrations();
    if (isDistributor.value) {
        loadRegistrationApprovals();
    }
});
</script>

<style scoped>
:deep(.v-field__input) {
    font-size: 14px !important;
}
:deep(.v-label) {
    font-size: 14px !important;
}
:deep(.v-messages) {
    font-size: 12px !important;
}

.registration-media-card {
    padding: 16px;
}

.registration-media-intro {
    margin: 0 0 16px;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.55;
}

.registration-upload-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.registration-upload-field {
    display: flex;
    min-width: 0;
    align-items: center;
    flex-direction: column;
    gap: 6px;
    padding: 16px 12px;
    border: 1px solid #eadde0;
    border-radius: 12px;
    background: #fffafb;
    text-align: center;
}

.registration-upload-field strong {
    margin-top: 4px;
    color: var(--ink);
    font-size: 13px;
    line-height: 1.35;
}

.registration-upload-field > span:not(.registration-upload-error) {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.4;
}

.registration-document-preview {
    display: flex;
    width: 100%;
    height: 100%;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 10px;
    background: #f8ecef;
}

.registration-upload-error {
    color: #b42318;
    font-size: 11px;
    line-height: 1.35;
}

.approval-registration-row {
    width: 100%;
    cursor: pointer;
    transition: background 0.2s ease;
}

.approval-registration-row:hover,
.approval-registration-row:focus-visible {
    background: #fff7f9;
    outline: none;
}

.approval-registration-row__icon {
    color: #9a6700;
    background: #fff4ce;
}

.approval-registration-row__meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px 8px;
}

.approval-registration-row__meta > span {
    margin: 0;
}

.approval-registration-row__action {
    display: flex;
    align-items: flex-end;
    flex-direction: column;
    gap: 6px;
}

@media (max-width: 640px) {
    .registration-upload-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .approval-registration-row__action .badge {
        display: none;
    }
}

@media (max-width: 440px) {
    .registration-upload-grid {
        grid-template-columns: 1fr;
    }

    .registration-upload-field {
        padding-block: 18px;
    }

    .approval-registration-row {
        align-items: flex-start;
    }

    .approval-registration-row__action .v-btn {
        min-width: 0;
        padding-inline: 9px;
    }
}
</style>
