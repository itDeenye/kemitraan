import api from '@/shared/services/api';

export interface ReferenceBank {
    id: number;
    code: string;
    name: string;
    logo?: string;
}

class ReferenceService {
    async getBanks(): Promise<ReferenceBank[]> {
        const { data } = await api.get('/references/banks');
        return data.data;
    }

    async getProvinces(): Promise<any[]> {
        const { data } = await api.get('/references/provinces');
        return data.data;
    }

    async getCities(provinceId: string | number): Promise<any[]> {
        const { data } = await api.get('/references/cities', { params: { province_id: provinceId } });
        return data.data;
    }

    async getDistricts(cityId: string | number): Promise<any[]> {
        const { data } = await api.get('/references/districts', { params: { city_id: cityId } });
        return data.data;
    }

    async getSubdistricts(districtId: string | number): Promise<any[]> {
        const { data } = await api.get('/references/subdistricts', { params: { district_id: districtId } });
        return data.data;
    }
}

export default new ReferenceService();
