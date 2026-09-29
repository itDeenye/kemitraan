import api from "@/shared/services/api";
import type { 
    SharingProfitMitra, 
    SharingProfitDetail, 
    SharingProfitHistory 
} from "../types/sharing-profit";

class SharingProfitService {
    async getMitras(params?: any): Promise<any> {
        const response = await api.get('/admin/rewards/sharing-profits', { params });
        return response.data?.data;
    }

    async getMitraDetails(uplineId: number, params?: any): Promise<any> {
        const response = await api.get(`/admin/rewards/sharing-profits/${uplineId}`, { params });
        return response.data?.data;
    }

    async approve(uplineIds: number[]): Promise<number> {
        const response = await api.post('/admin/rewards/sharing-profits/approve', {
            upline_ids: uplineIds,
        });
        return response.data?.data?.processed_count ?? 0;
    }

    async transfer(uplineIds: number[], note: string): Promise<void> {
        await api.post('/admin/rewards/sharing-profits/transfer', {
            upline_ids: uplineIds,
            note: note,
        });
    }

    async getHistories(params?: any): Promise<any> {
        const response = await api.get('/admin/rewards/sharing-profits/history', { params });
        return response.data?.data;
    }

    async getHistoryDetail(id: number): Promise<SharingProfitHistory> {
        const response = await api.get(`/admin/rewards/sharing-profits/history/${id}`);
        return response.data?.data;
    }
}

export default new SharingProfitService();
