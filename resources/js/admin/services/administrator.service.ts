import api from '@/shared/services/api';
import type { Administrator, AdministratorPayload, AdministratorPasswordPayload } from '@/admin/types/administrator';

class AdministratorService {
    async getAdministrator(id: number): Promise<Administrator> {
        const { data } = await api.get(`/admin/system/administrators/${id}`);
        return data.data;
    }

    async createAdministrator(payload: AdministratorPayload): Promise<any> {
        const { data } = await api.post('/admin/system/administrators', payload);
        return data;
    }

    async updateAdministrator(id: number, payload: AdministratorPayload): Promise<any> {
        const { data } = await api.put(`/admin/system/administrators/${id}`, payload);
        return data;
    }

    async updatePassword(id: number, payload: AdministratorPasswordPayload): Promise<any> {
        const { data } = await api.put(`/admin/system/administrators/${id}/password`, payload);
        return data;
    }

    async deleteAdministrator(id: number): Promise<any> {
        const { data } = await api.delete(`/admin/system/administrators/${id}`);
        return data;
    }
}

export default new AdministratorService();
