import api from '@/shared/services/api';
import type { Promotion, PromotionPayload } from '@/admin/types/promotion';

class PromotionService {
    async getPromotion(id: number): Promise<Promotion> {
        const { data } = await api.get(`/admin/product/promotions/${id}`);
        return data.data;
    }

    async createPromotion(payload: PromotionPayload): Promise<void> {
        await api.post('/admin/product/promotions', payload);
    }

    async updatePromotion(id: number, payload: PromotionPayload): Promise<void> {
        await api.put(`/admin/product/promotions/${id}`, payload);
    }

    async deletePromotion(id: number): Promise<void> {
        await api.delete(`/admin/product/promotions/${id}`);
    }
}

export default new PromotionService();
