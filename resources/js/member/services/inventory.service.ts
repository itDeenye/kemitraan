import api from '@/shared/services/api';
import type {
    StockListResponse,
    MutationListResponse,
    StockAdjustmentItem,
    StockAdjustmentListResponse,
    StockAdjustmentPayload,
} from '../types/inventory';

class InventoryService {
    async getCurrentStock(params?: Record<string, any>): Promise<StockListResponse> {
        const { data } = await api.get('/member/inventory/stocks', { params });
        return data.data;
    }

    async getMutations(params?: Record<string, any>): Promise<MutationListResponse> {
        const { data } = await api.get('/member/inventory/mutations', { params });
        return data.data;
    }

    async getAdjustments(params?: Record<string, any>): Promise<StockAdjustmentListResponse> {
        const { data } = await api.get('/member/inventory/adjustments', { params });
        return data.data;
    }

    async getAdjustment(id: number): Promise<StockAdjustmentItem> {
        const { data } = await api.get(`/member/inventory/adjustments/${id}`);
        return data.data;
    }

    async createAdjustment(payload: StockAdjustmentPayload): Promise<StockAdjustmentItem> {
        const { data } = await api.post('/member/inventory/adjustments', payload);
        return data.data;
    }
}

export default new InventoryService();
