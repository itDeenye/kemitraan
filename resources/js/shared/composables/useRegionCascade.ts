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
    const requestVersions = [0, 0, 0, 0];
    const loadingStates = [loadingProvinces, loadingCities, loadingDistricts, loadingSubdistricts];
    let cascadeVersion = 0;

    const invalidateFrom = (level: number) => {
        cascadeVersion++;
        for (let index = level; index < requestVersions.length; index++) {
            requestVersions[index]++;
            loadingStates[index].value = false;
        }
    };
    const cancelPendingRequests = () => invalidateFrom(1);
    const reset = () => {
        invalidateFrom(0);
        cities.value = [];
        districts.value = [];
        subdistricts.value = [];
    };

    const fetchProvinces = async () => {
        const version = ++requestVersions[0];
        loadingProvinces.value = true;
        try {
            const result = await referenceService.getProvinces();
            if (version === requestVersions[0]) provinces.value = result;
        } catch (e) {
            console.error("Gagal mengambil provinsi", e);
        } finally {
            if (version === requestVersions[0]) loadingProvinces.value = false;
        }
    };

    const fetchCities = async (provinceId: string | number) => {
        const version = ++requestVersions[1];
        loadingCities.value = true;
        try {
            const result = await referenceService.getCities(provinceId);
            if (version === requestVersions[1]) cities.value = result;
        } catch (e) {
            console.error("Gagal mengambil kota", e);
        } finally {
            if (version === requestVersions[1]) loadingCities.value = false;
        }
    };

    const fetchDistricts = async (cityId: string | number) => {
        const version = ++requestVersions[2];
        loadingDistricts.value = true;
        try {
            const result = await referenceService.getDistricts(cityId);
            if (version === requestVersions[2]) districts.value = result;
        } catch (e) {
            console.error("Gagal mengambil kecamatan", e);
        } finally {
            if (version === requestVersions[2]) loadingDistricts.value = false;
        }
    };

    const fetchSubdistricts = async (districtId: string | number) => {
        const version = ++requestVersions[3];
        loadingSubdistricts.value = true;
        try {
            const result = await referenceService.getSubdistricts(districtId);
            if (version === requestVersions[3]) subdistricts.value = result;
        } catch (e) {
            console.error("Gagal mengambil kelurahan", e);
        } finally {
            if (version === requestVersions[3]) loadingSubdistricts.value = false;
        }
    };

    /**
     * Handler ketika provinsi berubah — reset kota, kecamatan, kelurahan.
     * Caller harus me-null-kan field form sendiri jika menggunakan reactive form.
     */
    const onProvinceChange = async (val: any) => {
        invalidateFrom(1);
        cities.value = [];
        districts.value = [];
        subdistricts.value = [];
        if (val) {
            await fetchCities(val);
        }
    };

    const onCityChange = async (val: any) => {
        invalidateFrom(2);
        districts.value = [];
        subdistricts.value = [];
        if (val) {
            await fetchDistricts(val);
        }
    };

    const onDistrictChange = async (val: any) => {
        invalidateFrom(3);
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
        invalidateFrom(1);
        const version = cascadeVersion;
        if (region.province_id) {
            await fetchCities(region.province_id);
        }
        if (version !== cascadeVersion) return;
        if (region.city_id) {
            await fetchDistricts(region.city_id);
        }
        if (version !== cascadeVersion) return;
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
        cancelPendingRequests,
        reset,
    };
}
