<template>
    <div class="card" style="padding: 16px">
        <div v-if="initialLoading" class="text-center pa-6">
            <v-progress-circular indeterminate color="primary" />
            <p class="soft-label mt-3">Memuat formulir...</p>
        </div>

        <v-form
            v-else
            ref="formRef"
            @submit.prevent="submit"
            :disabled="loading"
        >
            <v-text-field
                v-model="formData.label"
                label="Label Alamat (Opsional)"
                placeholder="Contoh: Rumah atau Kantor"
                variant="outlined"
                density="comfortable"
                class="mb-2"
            />
            <v-text-field
                v-model="formData.recipient"
                label="Nama Penerima *"
                variant="outlined"
                density="comfortable"
                :rules="[(v) => !!v || 'Nama penerima wajib diisi']"
                class="mb-2"
            />
            <v-text-field
                v-model="formData.phone"
                label="Nomor Telepon *"
                variant="outlined"
                density="comfortable"
                :rules="[(v) => !!v || 'Nomor telepon wajib diisi']"
                class="mb-2"
            />
            <v-textarea
                v-model="formData.full_address"
                label="Alamat Lengkap *"
                variant="outlined"
                density="comfortable"
                :rules="[(v) => !!v || 'Alamat lengkap wajib diisi']"
                class="mb-2"
            />
            <v-autocomplete
                v-model="formData.province_id"
                :items="provinces"
                item-title="name"
                item-value="id"
                label="Provinsi *"
                variant="outlined"
                density="comfortable"
                :loading="loadingProvinces"
                :rules="[(v) => !!v || 'Provinsi wajib dipilih']"
                class="mb-2"
                @update:model-value="onProvinceChange"
            />
            <v-autocomplete
                v-model="formData.city_id"
                :items="cities"
                item-title="name"
                item-value="id"
                label="Kota/Kabupaten *"
                variant="outlined"
                density="comfortable"
                :loading="loadingCities"
                :disabled="!formData.province_id"
                :rules="[(v) => !!v || 'Kota/Kabupaten wajib dipilih']"
                class="mb-2"
                @update:model-value="onCityChange"
            />
            <v-autocomplete
                v-model="formData.district_id"
                :items="districts"
                item-title="name"
                item-value="id"
                label="Kecamatan *"
                variant="outlined"
                density="comfortable"
                :loading="loadingDistricts"
                :disabled="!formData.city_id"
                :rules="[(v) => !!v || 'Kecamatan wajib dipilih']"
                class="mb-2"
                @update:model-value="onDistrictChange"
            />
            <v-autocomplete
                v-model="formData.subdistrict_id"
                :items="subdistricts"
                item-title="name"
                item-value="id"
                label="Kelurahan/Desa *"
                variant="outlined"
                density="comfortable"
                :loading="loadingSubdistricts"
                :disabled="!formData.district_id"
                :rules="[(v) => !!v || 'Kelurahan/Desa wajib dipilih']"
                class="mb-2"
            />
            <div class="d-flex ga-3">
                <button
                    type="submit"
                    class="primary-button block"
                    :disabled="loading"
                >
                    {{
                        loading
                            ? "Menyimpan..."
                            : isEdit
                              ? "Perbarui Alamat"
                              : "Simpan Alamat"
                    }}
                </button>
            </div>
        </v-form>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import profileService from "@/member/services/profile.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useRegionCascade } from "@/shared/composables/useRegionCascade";

const props = defineProps<{ addressId?: number }>();
const router = useRouter();
const snackbar = useSnackbarStore();
const isEdit = computed(() => !!props.addressId);
const loading = ref(false);
const initialLoading = ref(true);
const formRef = ref<any>(null);

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

const formData = reactive({
    label: "",
    recipient: "",
    phone: "",
    full_address: "",
    province_id: null as string | number | null,
    city_id: null as string | number | null,
    district_id: null as string | number | null,
    subdistrict_id: null as string | number | null,
});

async function onProvinceChange(value: any) {
    formData.city_id = null;
    formData.district_id = null;
    formData.subdistrict_id = null;
    await baseProvinceChange(value);
}

async function onCityChange(value: any) {
    formData.district_id = null;
    formData.subdistrict_id = null;
    await baseCityChange(value);
}

async function onDistrictChange(value: any) {
    formData.subdistrict_id = null;
    await baseDistrictChange(value);
}

async function loadAddress() {
    try {
        await fetchProvinces();
        if (!props.addressId) return;

        const response = await profileService.getAddress(props.addressId);
        if (!response.success) return;
        const address = response.data;
        formData.label = address.label;
        formData.recipient = address.recipient;
        formData.phone = address.phone;
        formData.full_address = address.full_address;
        formData.province_id = address.region.province_id || null;
        formData.city_id = address.region.city_id || null;
        formData.district_id = address.region.district_id || null;
        formData.subdistrict_id = address.region.subdistrict_id || null;
        await initFromExisting({
            province_id: formData.province_id,
            city_id: formData.city_id,
            district_id: formData.district_id,
        });
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Alamat belum dapat dimuat.",
            "error",
        );
        await router.push("/member/profile/address");
    } finally {
        initialLoading.value = false;
    }
}

async function submit() {
    if (!formRef.value) return;
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    loading.value = true;
    try {
        const payload = { ...formData, country_id: 1 };
        const response = props.addressId
            ? await profileService.updateAddress(props.addressId, payload)
            : await profileService.createAddress(payload);
        if (response.success) {
            snackbar.showMessage(
                props.addressId
                    ? "Alamat berhasil diperbarui"
                    : "Alamat berhasil ditambahkan",
                "success",
            );
            await router.push("/member/profile/address");
        }
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Alamat belum dapat disimpan.",
            "error",
        );
    } finally {
        loading.value = false;
    }
}

onMounted(loadAddress);
</script>
