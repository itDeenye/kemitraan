<template>
    <v-dialog v-model="dialog" max-width="800" scrollable persistent>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28">
                        {{ isEdit ? "mdi-store-edit" : "mdi-store-plus" }}
                    </v-icon>
                    <span class="text-h6 font-weight-bold">
                        {{ isEdit ? "Edit Stokis" : "Tambah Stokis" }}
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

            <v-card-text class="pa-6">
                <v-form
                    ref="formRef"
                    @submit.prevent="submit"
                    v-model="isFormValid"
                >
                    <v-row>
                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="form.member_id"
                                :items="members"
                                item-title="name"
                                item-value="id"
                                label="Mitra Pemilik Stokis"
                                variant="outlined"
                                density="comfortable"
                                :loading="loadingMembers"
                                @update:search="searchMembers"
                                hide-details="auto"
                                placeholder="Ketik nama atau kode mitra..."
                                :rules="[rules.requiredSelect('Mitra')]"
                                :custom-filter="customFilter"
                            >
                                <template v-slot:item="{ props, item }">
                                    <v-list-item
                                        v-bind="props"
                                        :title="item.raw.name"
                                        :subtitle="item.raw.code"
                                    >
                                    </v-list-item>
                                </template>
                            </v-autocomplete>
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.name"
                                label="Nama Stokis"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :rules="[rules.required('Nama stokis')]"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.email"
                                label="Email Stokis"
                                type="email"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :rules="[rules.required('Email'), rules.email]"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.mobile_phone"
                                label="Nomor Telepon"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                :rules="[rules.required('No Telepon')]"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12">
                            <v-textarea
                                v-model="form.address"
                                label="Alamat Lengkap"
                                variant="outlined"
                                density="comfortable"
                                rows="3"
                                hide-details="auto"
                                :rules="[rules.required('Alamat lengkap')]"
                            ></v-textarea>
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="form.province_id"
                                :items="provinces"
                                item-title="name"
                                item-value="id"
                                label="Provinsi"
                                variant="outlined"
                                density="comfortable"
                                :loading="loadingProvinces"
                                @update:model-value="onProvinceChange"
                                hide-details="auto"
                                :rules="[rules.requiredSelect('Provinsi')]"
                            ></v-autocomplete>
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="form.city_id"
                                :items="cities"
                                item-title="name"
                                item-value="id"
                                label="Kota/Kabupaten"
                                variant="outlined"
                                density="comfortable"
                                :loading="loadingCities"
                                :disabled="!form.province_id"
                                @update:model-value="onCityChange"
                                hide-details="auto"
                                :rules="[rules.requiredSelect('Kota')]"
                            ></v-autocomplete>
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="form.district_id"
                                :items="districts"
                                item-title="name"
                                item-value="id"
                                label="Kecamatan"
                                variant="outlined"
                                density="comfortable"
                                :loading="loadingDistricts"
                                :disabled="!form.city_id"
                                @update:model-value="onDistrictChange"
                                hide-details="auto"
                                :rules="[rules.requiredSelect('Kecamatan')]"
                            ></v-autocomplete>
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="form.subdistrict_id"
                                :items="subdistricts"
                                item-title="name"
                                item-value="id"
                                label="Kelurahan/Desa"
                                variant="outlined"
                                density="comfortable"
                                :loading="loadingSubdistricts"
                                :disabled="!form.district_id"
                                hide-details="auto"
                                :rules="[rules.requiredSelect('Kelurahan')]"
                            ></v-autocomplete>
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.latitude"
                                label="Latitude"
                                type="text"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.longitude"
                                label="Longitude"
                                type="text"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12">
                            <v-textarea
                                v-model="form.note"
                                label="Catatan / Info Tambahan (Opsional)"
                                variant="outlined"
                                density="comfortable"
                                rows="2"
                                hide-details="auto"
                                placeholder="Cth: Buka pukul 08.00-17.00"
                            ></v-textarea>
                        </v-col>

                        <v-col cols="12">
                            <v-switch
                                v-model="form.is_active"
                                :label="
                                    form.is_active
                                        ? 'Stokis Aktif'
                                        : 'Stokis Nonaktif'
                                "
                                color="success"
                                hide-details
                                inset
                            ></v-switch>
                        </v-col>
                    </v-row>
                </v-form>
            </v-card-text>

            <v-card-actions class="pa-6 pt-0">
                <v-spacer></v-spacer>
                <v-btn
                    variant="outlined"
                    color="medium-emphasis"
                    class="text-none px-6"
                    @click="close"
                    :disabled="loading"
                >
                    Batal
                </v-btn>
                <v-btn
                    color="primary"
                    variant="flat"
                    class="text-none px-6 ml-3"
                    @click="submit"
                    :loading="loading"
                    :disabled="!isFormValid"
                >
                    {{ isEdit ? "Simpan Perubahan" : "Tambah Stokis" }}
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
const loading = ref(false);
const formRef = ref();
const isFormValid = ref(false);
const isEdit = ref(false);
const stockistId = ref<number | null>(null);

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
    requiredSelect: (field: string) => (v: any) =>
        !!v || `${field} wajib dipilih`,
    email: (v: any) => /.+@.+\..+/.test(v) || "Email tidak valid",
};

const customFilter = (value: string, query: string, item: any) => {
    if (!query) return true;
    const q = query.toLowerCase();
    const name = item?.raw?.name?.toLowerCase() || "";
    const code = item?.raw?.code?.toLowerCase() || "";
    return name.includes(q) || code.includes(q);
};

const form = reactive({
    member_id: null as any,
    name: "",
    email: "",
    address: "",
    mobile_phone: "",
    image_url: "", // For now we keep it empty or could implement file upload later
    province_id: null as any,
    city_id: null as any,
    district_id: null as any,
    subdistrict_id: null as any,
    latitude: null as any,
    longitude: null as any,
    note: "",
    is_active: true,
});

// Options
const members = ref<any[]>([]);
const loadingMembers = ref(false);

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

let searchTimeout: any = null;

const searchMembers = (query: string) => {
    if (query === null || query === undefined) query = "";
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        loadingMembers.value = true;
        try {
            const { data } = await api.get("/admin/partnership/members", {
                params: { search: query, limit: 20 },
            });
            const results = data?.data?.results;
            const items = data?.data;
            members.value = Array.isArray(results)
                ? results
                : Array.isArray(items)
                  ? items
                  : [];
        } catch (e) {
            console.error("Failed to fetch members", e);
        } finally {
            loadingMembers.value = false;
        }
    }, 500);
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

const open = async (id?: number) => {
    if (provinces.value.length === 0) {
        fetchProvinces();
    }

    if (id) {
        isEdit.value = true;
        stockistId.value = id;
        loading.value = true;
        dialog.value = true;
        try {
            const { data } = await api.get(
                `/admin/partnership/stockists/${id}`,
            );
            const item = data.data;

            // Set member option if not exists
            if (item.member) {
                members.value = [item.member];
                form.member_id = item.member.id;
            }

            form.name = item.name;
            form.email = item.email;
            form.address = item.address;
            form.mobile_phone = item.mobile_phone;
            form.image_url = item.image_url || "";
            form.note = item.note || "";
            form.is_active =
                item.is_active ||
                item.status?.code === "active" ||
                item.status?.code === 1;

            // Region handling
            if (item.region) {
                form.province_id = String(item.region.province_id);
                form.city_id = String(item.region.city_id);
                form.district_id = String(item.region.district_id);
                form.subdistrict_id = item.region.subdistrict_id;

                await initFromExisting({
                    province_id: form.province_id,
                    city_id: form.city_id,
                    district_id: form.district_id,
                });
            }

            if (item.coordinates) {
                form.latitude = item.coordinates.latitude;
                form.longitude = item.coordinates.longitude;
            } else {
                form.latitude = item.latitude;
                form.longitude = item.longitude;
            }
        } catch (e: any) {
            snackbar.showMessage("Gagal mengambil data stokis.", "error");
            close();
        } finally {
            loading.value = false;
        }
    } else {
        isEdit.value = false;
        stockistId.value = null;
        form.member_id = null;
        form.name = "";
        form.email = "";
        form.address = "";
        form.mobile_phone = "";
        form.image_url = "";
        form.province_id = null;
        form.city_id = null;
        form.district_id = null;
        form.subdistrict_id = null;
        form.latitude = null;
        form.longitude = null;
        form.note = "";
        form.is_active = true;

        cities.value = [];
        districts.value = [];
        subdistricts.value = [];
        members.value = [];
        dialog.value = true;
        searchMembers("");
    }
};

const close = () => {
    dialog.value = false;
    formRef.value?.reset();
};

const submit = async () => {
    if (!formRef.value?.validate()) return;

    loading.value = true;
    try {
        const payload = {
            ...form,
            province_id: Number(form.province_id),
            city_id: Number(form.city_id),
            district_id: Number(form.district_id),
            subdistrict_id: Number(form.subdistrict_id),
            latitude: form.latitude ? Number(form.latitude) : null,
            longitude: form.longitude ? Number(form.longitude) : null,
        };

        if (isEdit.value) {
            await api.put(
                `/admin/partnership/stockists/${stockistId.value}`,
                payload,
            );
            snackbar.showMessage("Data stokis berhasil diperbarui.", "success");
        } else {
            await api.post("/admin/partnership/stockists", payload);
            snackbar.showMessage("Stokis berhasil ditambahkan.", "success");
        }

        emit("saved");
        close();
    } catch (e: any) {
        snackbar.showMessage(
            e.response?.data?.message ||
                "Terjadi kesalahan saat menyimpan data.",
            "error",
        );
    } finally {
        loading.value = false;
    }
};

defineExpose({
    open,
    close,
});
</script>
