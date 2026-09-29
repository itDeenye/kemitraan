<template>
    <v-dialog v-model="isOpen" max-width="500" persistent scrollable>
        <v-card class="rounded-xl shadow-xl" elevation="0" max-height="90vh">
            <v-card-title
                class="px-6 py-4 font-weight-bold d-flex justify-space-between align-center border-b"
            >
                <span class="text-h6">Tambah Pelanggan Baru</span>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    density="comfortable"
                    @click="close"
                    :disabled="loading"
                ></v-btn>
            </v-card-title>

            <v-progress-linear
                v-if="loadingDefaults"
                indeterminate
                color="primary"
            ></v-progress-linear>

            <v-card-text
                class="pa-6 overflow-y-auto"
                :style="{
                    pointerEvents: loadingDefaults ? 'none' : undefined,
                    opacity: loadingDefaults ? 0.6 : 1,
                }"
            >
                <v-form ref="formRef" @submit.prevent="submit">
                    <v-text-field
                        v-model="formData.name"
                        label="Nama Lengkap *"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :rules="[(v) => !!v || 'Nama wajib diisi']"
                        class="mb-4"
                    ></v-text-field>

                    <v-text-field
                        v-model="formData.whatsapp"
                        label="No. WhatsApp *"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :rules="[(v) => !!v || 'WhatsApp wajib diisi']"
                        class="mb-4"
                    ></v-text-field>

                    <v-text-field
                        v-model="formData.phone"
                        label="No. Telepon (Opsional)"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mb-4"
                    ></v-text-field>

                    <v-select
                        v-model="formData.gender"
                        :items="[
                            { title: 'Laki-laki', value: 'L' },
                            { title: 'Perempuan', value: 'P' },
                        ]"
                        item-title="title"
                        item-value="value"
                        label="Jenis Kelamin *"
                        placeholder="Pilih Jenis Kelamin"
                        persistent-placeholder
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :rules="[(v) => !!v || 'Jenis kelamin wajib dipilih']"
                        class="mb-4"
                    ></v-select>

                    <v-textarea
                        v-model="formData.address"
                        label="Alamat Lengkap *"
                        placeholder="Masukkan Alamat Lengkap"
                        persistent-placeholder
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :rules="[(v) => !!v || 'Alamat lengkap wajib diisi']"
                        rows="3"
                        class="mb-4"
                    ></v-textarea>

                    <v-autocomplete
                        v-model="formData.province_id"
                        :items="provinces"
                        item-title="name"
                        item-value="id"
                        label="Provinsi *"
                        placeholder="Pilih Provinsi"
                        persistent-placeholder
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :loading="loadingProvinces"
                        @update:model-value="onProvinceChange"
                        :rules="[(v) => !!v || 'Provinsi wajib dipilih']"
                        class="mb-4"
                    ></v-autocomplete>

                    <v-autocomplete
                        v-model="formData.city_id"
                        :items="cities"
                        item-title="name"
                        item-value="id"
                        label="Kota/Kabupaten *"
                        placeholder="Pilih Kota/Kabupaten"
                        persistent-placeholder
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :loading="loadingCities"
                        :disabled="!formData.province_id"
                        @update:model-value="onCityChange"
                        :rules="[(v) => !!v || 'Kota/Kabupaten wajib dipilih']"
                        class="mb-4"
                    ></v-autocomplete>

                    <v-autocomplete
                        v-model="formData.district_id"
                        :items="districts"
                        item-title="name"
                        item-value="id"
                        label="Kecamatan *"
                        placeholder="Pilih Kecamatan"
                        persistent-placeholder
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :loading="loadingDistricts"
                        :disabled="!formData.city_id"
                        @update:model-value="onDistrictChange"
                        :rules="[(v) => !!v || 'Kecamatan wajib dipilih']"
                        class="mb-4"
                    ></v-autocomplete>

                    <v-autocomplete
                        v-model="formData.subdistrict_id"
                        :items="subdistricts"
                        item-title="name"
                        item-value="id"
                        label="Kelurahan/Desa *"
                        placeholder="Pilih Kelurahan/Desa"
                        persistent-placeholder
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :loading="loadingSubdistricts"
                        :disabled="!formData.district_id"
                        :rules="[(v) => !!v || 'Kelurahan/Desa wajib dipilih']"
                        class="mb-2"
                    ></v-autocomplete>
                </v-form>
            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions class="px-6 pt-4 pb-6 border-t">
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
                    :disabled="loadingDefaults"
                >
                    Simpan
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, type Ref } from "vue";
import saleService from "@/member/services/sale.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useRegionCascade } from "@/shared/composables/useRegionCascade";
import type { SaleCustomerPayload, SaleFormOptions } from "@/member/types/sale";

const emit = defineEmits(["saved"]);
const props = defineProps<{
    defaultAddress?: SaleFormOptions["seller"]["origin"];
}>();
const snackbar = useSnackbarStore();

const isOpen = ref(false);
const loading = ref(false);
const loadingDefaults = ref(false);
const formRef = ref<any>(null);
let openSequence = 0;

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
    initFromExisting,
    onProvinceChange: baseProvinceChange,
    onCityChange: baseCityChange,
    onDistrictChange: baseDistrictChange,
} = useRegionCascade();

const onProvinceChange = async (val: any) => {
    formData.city_id = null;
    formData.district_id = null;
    formData.subdistrict_id = null;
    await baseProvinceChange(val);
};

const onCityChange = async (val: any) => {
    formData.district_id = null;
    formData.subdistrict_id = null;
    await baseCityChange(val);
};

const onDistrictChange = async (val: any) => {
    formData.subdistrict_id = null;
    await baseDistrictChange(val);
};

const formData = reactive<SaleCustomerPayload>({
    name: "",
    whatsapp: "",
    phone: "",
    gender: null as any,
    address: "",
    province_id: null as any,
    city_id: null as any,
    district_id: null as any,
    subdistrict_id: null as any,
});

const resolveRegionId = (
    options: Ref<{ id: string | number; name: string }[]>,
    id: string | number | null | undefined,
    name?: string,
): string | number | null => {
    if (id === null || id === undefined || id === "" || Number(id) === 0) {
        return null;
    }

    const selected = options.value.find(
        (option) =>
            String(option.id) === String(id) || Number(option.id) === Number(id),
    );
    if (selected) return selected.id;

    // Alamat lama masih bisa ditampilkan meski wilayahnya tak lagi ada di daftar aktif.
    if (name?.trim()) {
        const optionId =
            typeof options.value[0]?.id === "string" ? String(id) : id;
        options.value.push({ id: optionId, name: name.trim() });
        return optionId;
    }

    return null;
};

const open = async () => {
    const sequence = ++openSequence;
    formData.name = "";
    formData.whatsapp = "";
    formData.phone = "";
    formData.gender = null as any;
    formData.address = "";
    formData.province_id = null;
    formData.city_id = null;
    formData.district_id = null;
    formData.subdistrict_id = null;

    if (formRef.value) formRef.value.resetValidation();
    isOpen.value = true;
    loadingDefaults.value = true;

    try {
        const region = props.defaultAddress;
        await Promise.all([
            provinces.value.length === 0 ? fetchProvinces() : Promise.resolve(),
            region ? initFromExisting(region) : Promise.resolve(),
        ]);

        if (sequence !== openSequence || !isOpen.value) return;

        formData.address = region?.address || "";
        formData.province_id = resolveRegionId(
            provinces,
            region?.province_id,
            region?.province_name,
        );
        formData.city_id = resolveRegionId(
            cities,
            region?.city_id,
            region?.city_name,
        );
        formData.district_id = resolveRegionId(
            districts,
            region?.district_id,
            region?.district_name,
        );
        formData.subdistrict_id = resolveRegionId(
            subdistricts,
            region?.subdistrict_id,
            region?.subdistrict_name,
        );
    } finally {
        if (sequence === openSequence) loadingDefaults.value = false;
    }
};

const close = () => {
    openSequence++;
    isOpen.value = false;
};

const submit = async () => {
    if (!formRef.value || loadingDefaults.value) return;
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    loading.value = true;
    try {
        const res = await saleService.createCustomer({
            ...formData,
            province_id: Number(formData.province_id),
            city_id: Number(formData.city_id),
            district_id: Number(formData.district_id),
            subdistrict_id: Number(formData.subdistrict_id),
        });

        if (res.success) {
            snackbar.showMessage(
                res.message || "Pelanggan berhasil ditambahkan",
                "success",
            );
            emit("saved", res.data);
            close();
        }
    } catch (error: any) {
        console.error("Failed to add customer:", error);
        snackbar.showMessage(
            error.response?.data?.message || "Gagal menambahkan pelanggan",
            "error",
        );
    } finally {
        loading.value = false;
    }
};

defineExpose({
    open,
});
</script>
