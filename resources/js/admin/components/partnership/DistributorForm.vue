<template>
    <v-dialog v-model="dialog" max-width="900" persistent scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28"
                        >mdi-account-plus-outline</v-icon
                    >
                    <span class="text-h6 font-weight-bold text-primary">
                        Tambah Mitra Distributor
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

            <v-card-text class="pa-6 pt-2 mb-2">
                <v-form
                    ref="formRef"
                    v-model="isFormValid"
                    @submit.prevent="save"
                >
                    <!-- Bagian Informasi Pribadi -->
                    <h3
                        class="text-subtitle-1 font-weight-bold mb-4 mt-2 d-flex align-center text-primary"
                    >
                        <v-icon class="mr-2" size="20"
                            >mdi-account-outline</v-icon
                        >
                        Informasi Pribadi
                    </h3>
                    <v-row dense>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-text-field
                                v-model="form.name"
                                label="Nama Lengkap"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :rules="[rules.required('Nama lengkap')]"
                            ></v-text-field>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-text-field
                                v-model="form.email"
                                label="Email"
                                type="email"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :rules="[rules.required('Email'), rules.email]"
                            ></v-text-field>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-text-field
                                v-model="form.mobile_phone"
                                label="Nomor Telepon"
                                placeholder="08xxxxxxxxxx atau 628xxxxxxxxxx"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="15"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :rules="[
                                    rules.required('Nomor Telepon'),
                                    rules.numeric('Nomor Telepon'),
                                    rules.indonesianPhone,
                                ]"
                            ></v-text-field>
                        </v-col>
                        <v-col cols="12" md="3" class="px-2 pb-2">
                            <v-select
                                v-model="form.gender"
                                :items="['Laki-laki', 'Perempuan']"
                                label="Jenis Kelamin"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :rules="[rules.requiredSelect('Jenis Kelamin')]"
                            ></v-select>
                        </v-col>
                        <v-col cols="12" md="3" class="px-2 pb-2">
                            <v-text-field
                                v-model="form.birth_date"
                                label="Tanggal Lahir"
                                type="date"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :rules="[rules.required('Tanggal Lahir')]"
                            ></v-text-field>
                        </v-col>
                    </v-row>

                    <v-divider class="my-4 border-opacity-50"></v-divider>

                    <!-- Bagian Identitas & Alamat -->
                    <h3
                        class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center text-primary"
                    >
                        <v-icon class="mr-2" size="20"
                            >mdi-card-account-details-outline</v-icon
                        >
                        Identitas & Alamat
                    </h3>
                    <v-row dense>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-select
                                v-model="form.identity_type"
                                :items="identityOptions"
                                label="Jenis Identitas"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :rules="[
                                    rules.requiredSelect('Jenis Identitas'),
                                ]"
                            ></v-select>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-text-field
                                v-model="form.identity_no"
                                :label="
                                    form.identity_type === 'PASPOR'
                                        ? 'Nomor Paspor'
                                        : 'Nomor Identitas'
                                "
                                :placeholder="
                                    form.identity_type === 'PASPOR'
                                        ? 'Masukkan nomor paspor'
                                        : 'Masukkan nomor identitas'
                                "
                                :inputmode="
                                    form.identity_type === 'PASPOR'
                                        ? 'text'
                                        : 'numeric'
                                "
                                :pattern="
                                    form.identity_type === 'PASPOR'
                                        ? undefined
                                        : '[0-9]*'
                                "
                                :maxlength="identitySettings.maxLength"
                                :hint="identitySettings.hint"
                                persistent-hint
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :rules="[
                                    rules.required('Nomor identitas'),
                                    validateIdentityNumber,
                                ]"
                            ></v-text-field>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-text-field
                                v-model="form.nib"
                                label="Nomor NIB (Opsional)"
                                placeholder="Masukkan nomor NIB"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="20"
                                counter="20"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :rules="[rules.numeric('NIB')]"
                            ></v-text-field>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-text-field
                                v-model="form.npwp"
                                label="Nomor NPWP (Opsional)"
                                placeholder="Masukkan nomor NPWP"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="30"
                                counter="30"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :rules="[rules.numeric('NPWP')]"
                            ></v-text-field>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-autocomplete
                                v-model="form.province_id"
                                :items="provinces"
                                item-title="name"
                                item-value="id"
                                label="Provinsi KTP"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :loading="loadingProvinces"
                                :rules="[rules.requiredSelect('Provinsi')]"
                                @update:model-value="onProvinceChange"
                            ></v-autocomplete>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-autocomplete
                                v-model="form.city_id"
                                :items="cities"
                                item-title="name"
                                item-value="id"
                                label="Kota / Kabupaten KTP"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :loading="loadingCities"
                                :disabled="!form.province_id"
                                :rules="[rules.requiredSelect('Kota')]"
                                @update:model-value="onCityChange"
                            ></v-autocomplete>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-autocomplete
                                v-model="form.district_id"
                                :items="districts"
                                item-title="name"
                                item-value="id"
                                label="Kecamatan KTP"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :loading="loadingDistricts"
                                :disabled="!form.city_id"
                                :rules="[rules.requiredSelect('Kecamatan')]"
                                @update:model-value="onDistrictChange"
                            ></v-autocomplete>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-autocomplete
                                v-model="form.subdistrict_id"
                                :items="subdistricts"
                                item-title="name"
                                item-value="id"
                                label="Kelurahan / Desa KTP"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :loading="loadingSubdistricts"
                                :disabled="!form.district_id"
                                :rules="[rules.requiredSelect('Kelurahan')]"
                            ></v-autocomplete>
                        </v-col>
                        <v-col cols="12" class="px-2 pb-2">
                            <v-textarea
                                v-model="form.address"
                                label="Alamat Sesuai KTP"
                                variant="outlined"
                                density="comfortable"
                                rows="2"
                                auto-grow
                                hide-details="auto"
                                :rules="[rules.required('Alamat KTP')]"
                            ></v-textarea>
                        </v-col>
                        <v-col cols="12" class="px-2 pb-2">
                            <v-checkbox
                                v-model="domicileSameAsKtp"
                                label="Alamat domisili sama dengan alamat KTP"
                                density="comfortable"
                                hide-details="auto"
                            ></v-checkbox>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-autocomplete
                                v-model="form.domicile_province_id"
                                :items="domicileProvinces"
                                item-title="name"
                                item-value="id"
                                label="Provinsi Domisili"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :loading="loadingDomicileProvinces"
                                :disabled="domicileSameAsKtp"
                                :rules="[rules.requiredSelect('Provinsi domisili')]"
                                @update:model-value="onDomicileProvinceChange"
                            ></v-autocomplete>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-autocomplete
                                v-model="form.domicile_city_id"
                                :items="domicileCities"
                                item-title="name"
                                item-value="id"
                                label="Kota / Kabupaten Domisili"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :loading="loadingDomicileCities"
                                :disabled="domicileSameAsKtp || !form.domicile_province_id"
                                :rules="[rules.requiredSelect('Kota domisili')]"
                                @update:model-value="onDomicileCityChange"
                            ></v-autocomplete>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-autocomplete
                                v-model="form.domicile_district_id"
                                :items="domicileDistricts"
                                item-title="name"
                                item-value="id"
                                label="Kecamatan Domisili"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :loading="loadingDomicileDistricts"
                                :disabled="domicileSameAsKtp || !form.domicile_city_id"
                                :rules="[rules.requiredSelect('Kecamatan domisili')]"
                                @update:model-value="onDomicileDistrictChange"
                            ></v-autocomplete>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-autocomplete
                                v-model="form.domicile_subdistrict_id"
                                :items="domicileSubdistricts"
                                item-title="name"
                                item-value="id"
                                label="Kelurahan / Desa Domisili"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :loading="loadingDomicileSubdistricts"
                                :disabled="domicileSameAsKtp || !form.domicile_district_id"
                                :rules="[rules.requiredSelect('Kelurahan domisili')]"
                            ></v-autocomplete>
                        </v-col>
                        <v-col cols="12" class="px-2 pb-2">
                            <v-textarea
                                v-model="form.domicile_address"
                                label="Alamat Domisili Sekarang"
                                variant="outlined"
                                density="comfortable"
                                rows="2"
                                auto-grow
                                hide-details="auto"
                                :disabled="domicileSameAsKtp"
                                hint="Biarkan sama jika alamat tinggal sesuai KTP"
                            ></v-textarea>
                        </v-col>
                    </v-row>

                    <v-divider class="my-4 border-opacity-50"></v-divider>

                    <h3
                        class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center text-primary"
                    >
                        <v-icon class="mr-2" size="20"
                            >mdi-file-image-outline</v-icon
                        >
                        Foto & Dokumen
                    </h3>
                    <v-row dense class="upload-grid">
                        <v-col cols="12" sm="6" class="px-2 pb-3">
                            <div class="upload-field h-100">
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
                                        <div class="document-preview">
                                            <v-img v-if="url" :src="url" cover />
                                            <v-icon
                                                v-else
                                                icon="mdi-account-outline"
                                                color="primary"
                                                size="44"
                                            />
                                        </div>
                                    </template>
                                </MediaUpload>
                                <div class="upload-field__content">
                                    <strong>Foto Profil</strong>
                                    <span>Opsional · JPG, PNG, atau WEBP</span>
                                </div>
                            </div>
                        </v-col>
                        <v-col cols="12" sm="6" class="px-2 pb-3">
                            <div class="upload-field h-100">
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
                                        <div class="document-preview">
                                            <v-img v-if="url" :src="url" cover />
                                            <v-icon
                                                v-else
                                                icon="mdi-card-account-details-outline"
                                                color="primary"
                                                size="44"
                                            />
                                        </div>
                                    </template>
                                </MediaUpload>
                                <div class="upload-field__content">
                                    <strong>Dokumen Identitas</strong>
                                    <span>Opsional · Pastikan dokumen terbaca</span>
                                </div>
                            </div>
                        </v-col>
                    </v-row>
                </v-form>
            </v-card-text>

            <v-card-actions class="pa-6 pt-4 border-t">
                <v-spacer></v-spacer>
                <v-btn
                    variant="outlined"
                    color="medium-emphasis"
                    class="text-none px-6"
                    @click="close"
                    :disabled="loadingSave"
                >
                    Batal
                </v-btn>
                <v-btn
                    color="primary"
                    variant="flat"
                    class="text-none px-6 ml-3"
                    @click="save"
                    :loading="loadingSave"
                    :disabled="!isFormValid || isUploadingMedia"
                >
                    Simpan Distributor
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { computed, ref, reactive, watch } from "vue";
import api from "@/shared/services/api";
import { useRegionCascade } from "@/shared/composables/useRegionCascade";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import MediaUpload from "@/shared/components/MediaUpload.vue";
import { identityNumberRule, identityNumberSettings, memberIdentityOptions } from "@/shared/utils/member-identity";

const emit = defineEmits(["saved"]);
const snackbar = useSnackbarStore();

const dialog = ref(false);
const isFormValid = ref(false);
const formRef = ref();
const loadingSave = ref(false);
const mediaUploads = reactive({
    profile: false,
    identity: false,
});
const isUploadingMedia = computed(() =>
    Object.values(mediaUploads).some(Boolean),
);

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
    requiredSelect: (field: string) => (v: any) =>
        !!v || `${field} wajib dipilih`,
    email: (v: any) => /.+@.+\..+/.test(v) || "Format email tidak valid",
    numeric: (field: string) => (v: any) =>
        !v || /^\d+$/.test(v) || `${field} hanya boleh berisi angka`,
    indonesianPhone: (v: any) =>
        !v ||
        !/^\d+$/.test(v) ||
        /^(?:08|628)\d{7,12}$/.test(v) ||
        "Nomor telepon harus diawali 08 atau 628 dan berisi 9–15 digit",
};

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
    onProvinceChange: baseProvinceChange,
    onCityChange: baseCityChange,
    onDistrictChange: baseDistrictChange,
    reset: resetKtpRegion,
} = useRegionCascade();

const {
    provinces: domicileProvinces,
    cities: domicileCities,
    districts: domicileDistricts,
    subdistricts: domicileSubdistricts,
    loadingProvinces: loadingDomicileProvinces,
    loadingCities: loadingDomicileCities,
    loadingDistricts: loadingDomicileDistricts,
    loadingSubdistricts: loadingDomicileSubdistricts,
    fetchProvinces: fetchDomicileProvinces,
    onProvinceChange: baseDomicileProvinceChange,
    onCityChange: baseDomicileCityChange,
    onDistrictChange: baseDomicileDistrictChange,
    initFromExisting: initDomicileRegion,
    cancelPendingRequests: cancelDomicileRequests,
    reset: resetDomicileRegion,
} = useRegionCascade();
const domicileSameAsKtp = ref(true);

const defaultForm = {
    name: "",
    email: "",
    mobile_phone: "",
    gender: "Laki-laki",
    birth_date: "",
    address: "",
    domicile_address: "",
    province_id: null as number | string | null,
    city_id: null as number | string | null,
    district_id: null as number | string | null,
    subdistrict_id: null as number | string | null,
    domicile_province_id: null as number | string | null,
    domicile_city_id: null as number | string | null,
    domicile_district_id: null as number | string | null,
    domicile_subdistrict_id: null as number | string | null,
    identity_type: "KTP",
    identity_no: "",
    identity_image_url: "",
    nib: "",
    npwp: "",
    image_url: "",
    status: 1, // Default active
};

const form = reactive({ ...defaultForm });
const identityOptions = memberIdentityOptions();
const identitySettings = computed(() => identityNumberSettings(form.identity_type));
const validateIdentityNumber = (value: unknown) => identityNumberRule(form.identity_type, value);

watch(
    () => [domicileSameAsKtp.value, form.address, form.province_id, form.city_id, form.district_id, form.subdistrict_id],
    () => {
        if (!domicileSameAsKtp.value) return;
        Object.assign(form, {
            domicile_address: form.address,
            domicile_province_id: form.province_id,
            domicile_city_id: form.city_id,
            domicile_district_id: form.district_id,
            domicile_subdistrict_id: form.subdistrict_id,
        });
        domicileCities.value = [...cities.value];
        domicileDistricts.value = [...districts.value];
        domicileSubdistricts.value = [...subdistricts.value];
    },
);
watch([cities, districts, subdistricts], () => {
    if (!domicileSameAsKtp.value) return;
    domicileCities.value = [...cities.value];
    domicileDistricts.value = [...districts.value];
    domicileSubdistricts.value = [...subdistricts.value];
});
watch(domicileSameAsKtp, async (same) => {
    cancelDomicileRequests();
    if (!same) {
        await initDomicileRegion({ province_id: form.domicile_province_id, city_id: form.domicile_city_id, district_id: form.domicile_district_id });
    }
});

// Methods
let formSession = 0;
const open = async () => {
    const session = ++formSession;
    resetKtpRegion();
    resetDomicileRegion();
    Object.assign(form, defaultForm);
    domicileSameAsKtp.value = true;
    Object.assign(mediaUploads, { profile: false, identity: false });
    dialog.value = true;

    // Fetch initial region data
    if (provinces.value.length === 0) {
        await fetchProvinces();
    }
    if (session !== formSession) return;
    if (domicileProvinces.value.length === 0) {
        await fetchDomicileProvinces();
    }
};

const close = () => {
    formSession++;
    resetKtpRegion();
    resetDomicileRegion();
    dialog.value = false;
    formRef.value?.reset();
};

const onProvinceChange = async (val: any) => {
    form.city_id = null;
    form.district_id = null;
    form.subdistrict_id = null;
    await baseProvinceChange(val);
};

const onCityChange = async (val: any) => {
    form.district_id = null;
    form.subdistrict_id = null;
    await baseCityChange(val);
};

const onDistrictChange = async (val: any) => {
    form.subdistrict_id = null;
    await baseDistrictChange(val);
};

const onDomicileProvinceChange = async (val: any) => {
    form.domicile_city_id = null;
    form.domicile_district_id = null;
    form.domicile_subdistrict_id = null;
    await baseDomicileProvinceChange(val);
};
const onDomicileCityChange = async (val: any) => {
    form.domicile_district_id = null;
    form.domicile_subdistrict_id = null;
    await baseDomicileCityChange(val);
};
const onDomicileDistrictChange = async (val: any) => {
    form.domicile_subdistrict_id = null;
    await baseDomicileDistrictChange(val);
};

const save = async () => {
    if (isUploadingMedia.value) {
        snackbar.showMessage("Tunggu hingga proses upload selesai.", "info");

        return;
    }

    const { valid } = await formRef.value.validate();
    if (!valid) return;

    loadingSave.value = true;
    try {
        const payload = {
            ...form,
            province_id: form.province_id ? String(form.province_id) : null,
            city_id: form.city_id ? String(form.city_id) : null,
            district_id: form.district_id ? String(form.district_id) : null,
            domicile_province_id: form.domicile_province_id ? String(form.domicile_province_id) : null,
            domicile_city_id: form.domicile_city_id ? String(form.domicile_city_id) : null,
            domicile_district_id: form.domicile_district_id ? String(form.domicile_district_id) : null,
            subdistrict_id: form.subdistrict_id
                ? String(form.subdistrict_id)
                : null,
        };

        await api.post("/admin/partnership/distributors", payload);
        snackbar.showMessage(
            "Mitra Distributor berhasil ditambahkan.",
            "success",
        );
        emit("saved");
        close();
    } catch (e: any) {
        snackbar.showMessage(
            e.response?.data?.message ||
                "Terjadi kesalahan saat menyimpan data.",
            "error",
        );
    } finally {
        loadingSave.value = false;
    }
};

defineExpose({
    open,
    close,
});
</script>

<style scoped>
.upload-field {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 16px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 12px;
    background: rgb(var(--v-theme-surface));
}

.upload-field__content {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    gap: 4px;
}

.upload-field__content strong {
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
    font-size: 14px;
}

.upload-field__content span {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 12px;
    line-height: 1.45;
}

.document-preview {
    display: flex;
    width: 100%;
    height: 100%;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 10px;
    background: rgb(var(--v-theme-surface-variant));
}

@media (max-width: 600px) {
    :deep(.v-card-title) {
        padding: 18px 16px !important;
    }

    :deep(.v-card-text),
    :deep(.v-card-actions) {
        padding-right: 16px !important;
        padding-left: 16px !important;
    }

    .upload-field {
        gap: 14px;
        padding: 14px;
    }
}

@media (max-width: 380px) {
    .upload-field {
        flex-direction: column;
        text-align: center;
    }
}
</style>
