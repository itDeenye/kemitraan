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
                        maxlength="200"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :rules="[
                            rules.required('Nama lengkap'),
                            rules.maxLength('Nama lengkap', 200),
                        ]"
                        class="mb-4"
                    ></v-text-field>

                    <v-text-field
                        v-model="formData.whatsapp"
                        label="No. WhatsApp *"
                        placeholder="08xxxxxxxxxx, 628xxxxxxxxxx, atau +628xxxxxxxxxx"
                        inputmode="tel"
                        pattern="[+]?[0-9]*"
                        maxlength="16"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        :rules="[
                            rules.required('Nomor WhatsApp'),
                            rules.phoneCharacters,
                            rules.indonesianPhone('Nomor WhatsApp'),
                        ]"
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
                        :rules="[rules.requiredSelect('Jenis kelamin')]"
                        class="mb-4"
                    ></v-select>

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
                        :rules="[rules.requiredSelect('Provinsi')]"
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
                        :rules="[rules.requiredSelect('Kota/Kabupaten')]"
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
                        :rules="[rules.requiredSelect('Kecamatan')]"
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
                        :rules="[rules.requiredSelect('Kelurahan/Desa')]"
                        class="mb-2"
                    ></v-autocomplete>

                    <v-textarea
                        v-model="formData.address"
                        label="Alamat Detail *"
                        placeholder="Jl. Contoh No. 123, RT 01/RW 02"
                        persistent-placeholder
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        maxlength="500"
                        :rules="[
                            rules.required('Alamat detail'),
                            rules.maxLength('Alamat detail', 500),
                        ]"
                        rows="3"
                        class="mb-4"
                    ></v-textarea>
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
import { ref, reactive } from "vue";
import saleService from "@/member/services/sale.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useRegionCascade } from "@/shared/composables/useRegionCascade";
import type { SaleCustomerPayload } from "@/member/types/sale";

const emit = defineEmits(["saved"]);
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
    onProvinceChange: baseProvinceChange,
    onCityChange: baseCityChange,
    onDistrictChange: baseDistrictChange,
    reset: resetRegions,
} = useRegionCascade();

const rules = {
    required: (field: string) => (value: unknown) =>
        (typeof value === "string" ? value.trim().length > 0 : !!value) ||
        `${field} wajib diisi`,
    requiredSelect: (field: string) => (value: unknown) =>
        (value !== null && value !== undefined && value !== "") ||
        `${field} wajib dipilih`,
    maxLength: (field: string, maximum: number) => (value: unknown) =>
        !value ||
        String(value).length <= maximum ||
        `${field} maksimal ${maximum} karakter`,
    phoneCharacters: (value: unknown) =>
        !value ||
        /^\+?\d+$/.test(String(value)) ||
        "Nomor hanya boleh berisi angka dan tanda + di awal",
    indonesianPhone: (field: string) => (value: unknown) =>
        !value ||
        !/^\+?\d+$/.test(String(value)) ||
        /^(?:08|628|\+628)\d{7,12}$/.test(String(value)) ||
        `${field} harus diawali 08, 628, atau +628 dan berisi 9–15 digit`,
};

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
    resetRegions();

    if (formRef.value) formRef.value.resetValidation();
    isOpen.value = true;
    loadingDefaults.value = true;

    try {
        if (provinces.value.length === 0) {
            await fetchProvinces();
        }

        if (sequence !== openSequence || !isOpen.value) return;
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
