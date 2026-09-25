<template>
    <div class="screen-body">
        <div class="filter-chips mb-4">
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
                                    label="Upline"
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
                                <v-text-field
                                    :model-value="options.target_level.name"
                                    label="Level Pendaftaran"
                                    variant="outlined"
                                    density="compact"
                                    disabled
                                    class="mb-2"
                                />
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
                            <v-col cols="12">
                                <v-textarea
                                    v-model="form.address"
                                    label="Alamat Lengkap *"
                                    placeholder="Masukkan nama jalan, nomor, rt/rw..."
                                    rows="2"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[required('Alamat Lengkap')]"
                                    :error-messages="errors.address"
                                    class="mb-2"
                                />
                            </v-col>
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
                                    :items="options.identity_types"
                                    :rules="[required('Jenis Identitas')]"
                                    :error-messages="errors.identity_type"
                                    class="mb-2"
                                />
                            </v-col>
                            <v-col cols="12" md="8">
                                <v-text-field
                                    v-model="form.identity_no"
                                    label="Nomor Identitas *"
                                    placeholder="Masukkan 16 digit NIK"
                                    variant="outlined"
                                    density="compact"
                                    counter="16"
                                    maxlength="16"
                                    :rules="[
                                        required('Nomor Identitas'),
                                        numeric('Nomor Identitas'),
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
                        :disabled="!isFormValid || submitting"
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
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useRegionCascade } from "@/shared/composables/useRegionCascade";
import referenceService from "@/shared/services/reference.service";
import type { ReferenceBank } from "@/shared/services/reference.service";
import networkService from "@/member/services/network.service";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { encodeRouteId } from "@/shared/utils/route-id";
import type {
    RegistrationOptions,
    RegistrationListItem,
    RegistrationListParams,
    DataTablePagination,
} from "@/member/types/network";

const router = useRouter();
const snackbar = useSnackbarStore();
const activeTab = ref<"form" | "list">("form");
const regFormRef = ref<any>(null);
const isFormValid = ref(false);
const loadingOptions = ref(false);
const submitting = ref(false);
const errors = reactive<Record<string, string[]>>({});
const options = ref<RegistrationOptions | null>(null);
const banks = ref<ReferenceBank[]>([]);
const loadingBanks = ref(false);

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

const form = reactive({
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
    nib: "",
});

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
        const data = await networkService.getRegistrations({ page, limit: 10 });
        if (append) {
            registrations.value.push(...data.results);
        } else {
            registrations.value = data.results;
        }
        listPagination.value = data.pagination;
        registrationCount.value = data.pagination.total_data;
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

async function submitRegistration() {
    const { valid } = await regFormRef.value?.validate();
    if (!valid) return;

    submitting.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        const response = await networkService.storeRegistration({
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
        nib: "",
    });
    regFormRef.value?.resetValidation();
}

onMounted(() => {
    loadOptions();
    loadRegistrations();
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
</style>
