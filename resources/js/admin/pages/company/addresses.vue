<template>
    <div>
        <div class="mb-6">
            <h1 class="text-h4 font-weight-bold mb-1">Konfigurasi Alamat</h1>
            <p class="text-body-2 text-medium-emphasis">
                Halaman untuk mengelola data alamat operasional perusahaan.
            </p>
        </div>

        <v-row v-if="!loadingData">
            <v-col cols="12" md="4">
                <v-card
                    variant="flat"
                    class="pa-6 text-center rounded-xl bg-surface elevation-1 h-100 d-flex flex-column align-center"
                >
                    <v-avatar color="primary-lighten-1" size="90" class="mb-4">
                        <v-icon size="48" color="primary">mdi-domain</v-icon>
                    </v-avatar>

                    <h2 class="text-h5 font-weight-bold mb-1">
                        {{ form.name || "Pusat Operasional" }}
                    </h2>
                    <p class="text-body-2 text-medium-emphasis mb-4">
                        {{ form.legal_name || "PT / CV belum diatur" }}
                    </p>

                    <v-chip
                        :color="form.is_active ? 'success' : 'error'"
                        variant="tonal"
                        prepend-icon="mdi-check-circle"
                        class="mb-6 font-weight-medium"
                        size="small"
                    >
                        {{ form.is_active ? "Status Aktif" : "Non-Aktif" }}
                    </v-chip>

                    <v-divider class="mb-4 border-opacity-50 w-100" />

                    <div
                        class="w-100 text-left text-body-2 text-medium-emphasis mb-2"
                    >
                        <div class="d-flex align-center ga-2 mb-2">
                            <v-icon size="18">mdi-email-outline</v-icon>
                            <span>{{ form.email || "-" }}</span>
                        </div>
                        <div class="d-flex align-center ga-2 mb-2">
                            <v-icon size="18">mdi-phone-outline</v-icon>
                            <span>{{ form.phone || "-" }}</span>
                        </div>
                        <div class="d-flex align-start ga-2 mb-2">
                            <v-icon size="18" class="mt-1"
                                >mdi-map-marker-outline</v-icon
                            >
                            <span>{{
                                form.address || "Alamat belum lengkap"
                            }}</span>
                        </div>
                    </div>
                </v-card>
            </v-col>

            <v-col cols="12" md="8">
                <v-card class="rounded-xl bg-surface elevation-1 h-100">
                    <v-card-text class="pa-6">
                        <v-form
                            ref="formRef"
                            v-model="isFormValid"
                            @submit.prevent="save"
                        >
                            <h3 class="text-h6 font-weight-bold mb-4">
                                Informasi Umum
                            </h3>
                            <v-row>
                                <v-col cols="12" md="6">
                                    <v-text-field
                                        v-model="form.name"
                                        variant="outlined"
                                        density="comfortable"
                                        :rules="[
                                            (v) =>
                                                !!v ||
                                                'Nama lokasi wajib diisi',
                                        ]"
                                    >
                                        <template #label>
                                            Nama Lokasi / Gudang
                                            <span class="text-error">*</span>
                                        </template>
                                    </v-text-field>
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field
                                        v-model="form.legal_name"
                                        variant="outlined"
                                        density="comfortable"
                                        :rules="[
                                            (v) =>
                                                !!v || 'Nama legal wajib diisi',
                                        ]"
                                    >
                                        <template #label>
                                            Nama Legal (PT / CV)
                                            <span class="text-error">*</span>
                                        </template>
                                    </v-text-field>
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-text-field
                                        v-model="form.npwp"
                                        label="NPWP"
                                        variant="outlined"
                                        density="comfortable"
                                    ></v-text-field>
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-text-field
                                        v-model="form.phone"
                                        label="No. Telepon"
                                        variant="outlined"
                                        density="comfortable"
                                    ></v-text-field>
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-text-field
                                        v-model="form.email"
                                        label="Email Perusahaan"
                                        type="email"
                                        variant="outlined"
                                        density="comfortable"
                                    ></v-text-field>
                                </v-col>
                            </v-row>

                            <v-divider class="my-4"></v-divider>

                            <h3 class="text-h6 font-weight-bold mb-4">
                                Lokasi & Alamat
                            </h3>
                            <v-row>
                                <v-col cols="12" md="6">
                                    <v-autocomplete
                                        v-model="form.province_id"
                                        :items="provinces"
                                        item-title="name"
                                        item-value="id"
                                        variant="outlined"
                                        density="comfortable"
                                        :loading="loadingProvinces"
                                        :rules="[
                                            (v) =>
                                                !!v || 'Provinsi wajib dipilih',
                                        ]"
                                        @update:model-value="onProvinceChange"
                                    >
                                        <template #label>
                                            Provinsi
                                            <span class="text-error">*</span>
                                        </template>
                                    </v-autocomplete>
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-autocomplete
                                        v-model="form.city_id"
                                        :items="cities"
                                        item-title="name"
                                        item-value="id"
                                        variant="outlined"
                                        density="comfortable"
                                        :loading="loadingCities"
                                        :disabled="!form.province_id"
                                        :rules="[
                                            (v) => !!v || 'Kota wajib dipilih',
                                        ]"
                                        @update:model-value="onCityChange"
                                    >
                                        <template #label>
                                            Kota / Kabupaten
                                            <span class="text-error">*</span>
                                        </template>
                                    </v-autocomplete>
                                </v-col>

                                <v-col cols="12" md="6">
                                    <v-autocomplete
                                        v-model="form.district_id"
                                        :items="districts"
                                        item-title="name"
                                        item-value="id"
                                        variant="outlined"
                                        density="comfortable"
                                        :loading="loadingDistricts"
                                        :disabled="!form.city_id"
                                        :rules="[
                                            (v) =>
                                                !!v ||
                                                'Kecamatan wajib dipilih',
                                        ]"
                                        @update:model-value="onDistrictChange"
                                    >
                                        <template #label>
                                            Kecamatan
                                            <span class="text-error">*</span>
                                        </template>
                                    </v-autocomplete>
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-autocomplete
                                        v-model="form.subdistrict_id"
                                        :items="subdistricts"
                                        item-title="name"
                                        item-value="id"
                                        variant="outlined"
                                        density="comfortable"
                                        :loading="loadingSubdistricts"
                                        :disabled="!form.district_id"
                                        :rules="[
                                            (v) =>
                                                !!v ||
                                                'Kelurahan wajib dipilih',
                                        ]"
                                    >
                                        <template #label>
                                            Kelurahan / Desa
                                            <span class="text-error">*</span>
                                        </template>
                                    </v-autocomplete>
                                </v-col>

                                <v-col cols="12">
                                    <v-textarea
                                        v-model="form.address"
                                        variant="outlined"
                                        density="comfortable"
                                        rows="2"
                                        auto-grow
                                        :rules="[
                                            (v) =>
                                                !!v ||
                                                'Detail alamat wajib diisi',
                                        ]"
                                    >
                                        <template #label>
                                            Detail Alamat (Jalan, RT/RW,
                                            Patokan)
                                            <span class="text-error">*</span>
                                        </template>
                                    </v-textarea>
                                </v-col>
                            </v-row>

                            <v-divider class="my-4"></v-divider>

                            <h3 class="text-h6 font-weight-bold mb-4">
                                Titik Koordinat
                            </h3>
                            <v-row>
                                <v-col cols="12" md="6">
                                    <v-text-field
                                        v-model.number="form.latitude"
                                        label="Latitude"
                                        type="number"
                                        variant="outlined"
                                        density="comfortable"
                                        placeholder="-7.2575"
                                    ></v-text-field>
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field
                                        v-model.number="form.longitude"
                                        label="Longitude"
                                        type="number"
                                        variant="outlined"
                                        density="comfortable"
                                        placeholder="112.7521"
                                    ></v-text-field>
                                </v-col>
                            </v-row>

                            <div class="d-flex justify-end mt-4">
                                <v-btn
                                    color="primary"
                                    variant="flat"
                                    class="text-none px-8 rounded-lg"
                                    type="submit"
                                    :loading="loadingSave"
                                    :disabled="!isFormValid"
                                >
                                    Simpan Perubahan
                                </v-btn>
                            </div>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row v-else>
            <v-col cols="12" md="4">
                <v-skeleton-loader type="card" height="400"></v-skeleton-loader>
            </v-col>
            <v-col cols="12" md="8">
                <v-skeleton-loader
                    type="article, actions"
                    height="400"
                ></v-skeleton-loader>
            </v-col>
        </v-row>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from "vue";
import api from "@/shared/services/api";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useRegionCascade } from "@/shared/composables/useRegionCascade";

const snackbar = useSnackbarStore();

const isFormValid = ref(false);
const formRef = ref();

const loadingData = ref(true);
const loadingSave = ref(false);

const existingId = ref<number | null>(null);

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
    initFromExisting,
} = useRegionCascade();

const form = reactive({
    name: "",
    legal_name: "",
    npwp: "",
    phone: "",
    email: "",
    logo_url: "",
    address: "",
    province_id: null as number | string | null,
    city_id: null as number | string | null,
    district_id: null as number | string | null,
    subdistrict_id: null as number | string | null,
    latitude: null as number | null,
    longitude: null as number | null,
    is_active: true,
});

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

const loadData = async () => {
    loadingData.value = true;
    try {
        const { data } = await api.get("/admin/company/warehouses");
        const results = data.data?.results || [];

        if (results.length > 0) {
            const warehouse = results[0];
            existingId.value = warehouse.id;

            // Extract from region and coordinates
            const region = warehouse.region || {};
            const coordinates = warehouse.coordinates || {};

            Object.assign(form, {
                name: warehouse.name || "",
                legal_name: warehouse.legal_name || "",
                npwp: warehouse.npwp || "",
                phone: warehouse.phone || "",
                email: warehouse.email || "",
                logo_url: warehouse.logo || "",
                address: warehouse.address || "",
                province_id: region.province_id
                    ? String(region.province_id)
                    : null,
                city_id: region.city_id ? String(region.city_id) : null,
                district_id: region.district_id
                    ? String(region.district_id)
                    : null,
                subdistrict_id: region.subdistrict_id
                    ? region.subdistrict_id
                    : null,
                latitude: coordinates.latitude
                    ? parseFloat(coordinates.latitude)
                    : null,
                longitude: coordinates.longitude
                    ? parseFloat(coordinates.longitude)
                    : null,
                is_active:
                    warehouse.is_active !== undefined
                        ? warehouse.is_active
                        : true,
            });

            await initFromExisting({
                province_id: form.province_id,
                city_id: form.city_id,
                district_id: form.district_id,
            });
        }
    } catch (e) {
        console.error("Gagal memuat data konfigurasi alamat", e);
        snackbar.showMessage("Gagal memuat data.", "error");
    } finally {
        loadingData.value = false;
    }
};

const save = async () => {
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    loadingSave.value = true;
    try {
        if (existingId.value) {
            await api.put(
                `/admin/company/warehouses/${existingId.value}`,
                form,
            );
            snackbar.showMessage("Data alamat berhasil diperbarui.", "success");
        } else {
            const { data } = await api.post("/admin/company/warehouses", form);
            // After create, save the new ID
            if (data.data?.id) {
                existingId.value = data.data.id;
            }
            snackbar.showMessage(
                "Data alamat berhasil ditambahkan.",
                "success",
            );
        }
        await loadData();
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

onMounted(async () => {
    await fetchProvinces();
    await loadData();
});
</script>
