import api from '@/shared/services/api';
import type { SystemConfig, SystemConfigPayload } from '@/admin/types/system-config';

class SystemConfigService {
    /** Fetch paginated list of system configs */
    async getConfigs(params?: Record<string, any>): Promise<{ results: SystemConfig[]; pagination: any }> {
        const { data } = await api.get('/admin/system/configs', { params });
        return data.data;
    }

    /** Fetch single config detail */
    async getConfig(id: number): Promise<SystemConfig> {
        const { data } = await api.get(`/admin/system/configs/${id}`);
        return data.data;
    }

    /** Update existing config */
    async updateConfig(id: number, payload: SystemConfigPayload): Promise<SystemConfig> {
        const { data } = await api.put(`/admin/system/configs/${id}`, payload);
        return data.data;
    }
}

export default new SystemConfigService();
