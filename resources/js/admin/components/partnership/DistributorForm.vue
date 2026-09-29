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
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :rules="[rules.required('Nomor Telepon')]"
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
                                :items="['KTP', 'SIM', 'Paspor']"
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
                                label="Nomor Identitas (NIK)"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :rules="[rules.required('Nomor identitas')]"
                            ></v-text-field>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-autocomplete
                                v-model="form.province_id"
                                :items="provinces"
                                item-title="name"
                                item-value="id"
                                label="Provinsi Domisili"
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
                                label="Kota / Kabupaten"
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
                                label="Kecamatan"
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
                                label="Kelurahan / Desa"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :loading="loadingSubdistricts"
                                :disabled="!form.district_id"
                                :rules="[rules.requiredSelect('Kelurahan')]"
                            ></v-autocomplete>
                        </v-col>
                        <v-col cols="12" md="6" class="px-2 pb-2">
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
                        <v-col cols="12" md="6" class="px-2 pb-2">
                            <v-textarea
                                v-model="form.domicile_address"
                                label="Alamat Domisili Sekarang"
                                variant="outlined"
                                density="comfortable"
                                rows="2"
                                auto-grow
                                hide-details="auto"
                                hint="Biarkan sama jika alamat tinggal sesuai KTP"
                            ></v-textarea>
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
                    :disabled="!isFormValid"
                >
                    Simpan Distributor
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import api from "@/shared/services/api";
import { useRegionCascade } from "@/shared/composables/useRegionCascade";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const emit = defineEmits(["saved"]);
const snackbar = useSnackbarStore();

const dialog = ref(false);
const isFormValid = ref(false);
const formRef = ref();
const loadingSave = ref(false);

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
    requiredSelect: (field: string) => (v: any) =>
        !!v || `${field} wajib dipilih`,
    email: (v: any) => /.+@.+\..+/.test(v) || "Format email tidak valid",
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
} = useRegionCascade();

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
    identity_type: "KTP",
    identity_no: "",
    identity_image_url: "",
    status: 1, // Default active
};

const form = reactive({ ...defaultForm });

// Methods
const open = async () => {
    Object.assign(form, defaultForm);
    dialog.value = true;

    // Fetch initial region data
    if (provinces.value.length === 0) {
        await fetchProvinces();
    }
};

const close = () => {
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

const save = async () => {
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    loadingSave.value = true;
    try {
        const payload = {
            ...form,
            province_id: form.province_id ? String(form.province_id) : null,
            city_id: form.city_id ? String(form.city_id) : null,
            district_id: form.district_id ? String(form.district_id) : null,
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
