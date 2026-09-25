import { ref } from "vue";
import referenceService from "@/shared/services/reference.service";

export interface RegionData {
    province_id?: number | string | null;
    city_id?: number | string | null;
    district_id?: number | string | null;
    subdistrict_id?: number | string | null;
}

/**
 * Composable untuk mengelola dropdown cascade wilayah:
 * Provinsi → Kota → Kecamatan → Kelurahan
 *
 * Menghilangkan duplikasi kode region cascade di banyak form.
 */
export function useRegionCascade() {
    const provinces = ref<any[]>([]);
    const cities = ref<any[]>([]);
    const districts = ref<any[]>([]);
    const subdistricts = ref<any[]>([]);

    const loadingProvinces = ref(false);
    const loadingCities = ref(false);
    const loadingDistricts = ref(false);
    const loadingSubdistricts = ref(false);

    const fetchProvinces = async () => {
        loadingProvinces.value = true;
        try {
            provinces.value = await referenceService.getProvinces();
        } catch (e) {
            console.error("Gagal mengambil provinsi", e);
        } finally {
            loadingProvinces.value = false;
        }
    };

    const fetchCities = async (provinceId: string | number) => {
        loadingCities.value = true;
        try {
            cities.value = await referenceService.getCities(provinceId);
        } catch (e) {
            console.error("Gagal mengambil kota", e);
        } finally {
            loadingCities.value = false;
        }
    };

    const fetchDistricts = async (cityId: string | number) => {
        loadingDistricts.value = true;
        try {
            districts.value = await referenceService.getDistricts(cityId);
        } catch (e) {
            console.error("Gagal mengambil kecamatan", e);
        } finally {
            loadingDistricts.value = false;
        }
    };

    const fetchSubdistricts = async (districtId: string | number) => {
        loadingSubdistricts.value = true;
        try {
            subdistricts.value =
                await referenceService.getSubdistricts(districtId);
        } catch (e) {
            console.error("Gagal mengambil kelurahan", e);
        } finally {
            loadingSubdistricts.value = false;
        }
    };

    /**
     * Handler ketika provinsi berubah — reset kota, kecamatan, kelurahan.
     * Caller harus me-null-kan field form sendiri jika menggunakan reactive form.
     */
    const onProvinceChange = async (val: any) => {
        cities.value = [];
        districts.value = [];
        subdistricts.value = [];
        if (val) {
            await fetchCities(val);
        }
    };

    const onCityChange = async (val: any) => {
        districts.value = [];
        subdistricts.value = [];
        if (val) {
            await fetchDistricts(val);
        }
    };

    const onDistrictChange = async (val: any) => {
        subdistricts.value = [];
        if (val) {
            await fetchSubdistricts(val);
        }
    };

    /**
     * Inisialisasi cascade dari data yang sudah ada (untuk kasus edit).
     * Menerima object region dan secara berurutan memuat dependensi dropdown.
     */
    const initFromExisting = async (region: RegionData) => {
        if (region.province_id) {
            await fetchCities(region.province_id);
        }
        if (region.city_id) {
            await fetchDistricts(region.city_id);
        }
        if (region.district_id) {
            await fetchSubdistricts(region.district_id);
        }
    };

    return {
        // State
        provinces,
        cities,
        districts,
        subdistricts,
        loadingProvinces,
        loadingCities,
        loadingDistricts,
        loadingSubdistricts,

        // Fetch methods
        fetchProvinces,
        fetchCities,
        fetchDistricts,
        fetchSubdistricts,

        // Change handlers
        onProvinceChange,
        onCityChange,
        onDistrictChange,

        // Init helper
        initFromExisting,
    };
}
