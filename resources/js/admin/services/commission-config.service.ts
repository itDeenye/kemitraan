import api from '@/shared/services/api';
import type { SystemConfig } from '@/admin/types/system-config';

class CommissionConfigService {
    /** Fetch paginated list of commission configs */
    async getConfigs(params?: Record<string, any>): Promise<{ results: SystemConfig[]; pagination: any }> {
        const { data } = await api.get('/admin/system/commission-configs', { params });
        return data.data;
    }

    /** Update existing commission config (payload only needs `value`, no `type`) */
    async updateConfig(id: number, payload: { value: Record<string, any> }): Promise<SystemConfig> {
        const { data } = await api.put(`/admin/system/commission-configs/${id}`, payload);
        return data.data;
    }
}

export default new CommissionConfigService();
