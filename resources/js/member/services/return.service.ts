import api from '@/shared/services/api';
import type {
    ReturnListResponse,
    ReturnItem,
    ReturnPayload,
    ReturnActionSummary,
} from '../types/return';
import type { EligibleReceiptListResponse as EligibleResponse } from '../types/inventory';

class ReturnService {
    async getActionSummary(): Promise<ReturnActionSummary> {
        const { data } = await api.get('/member/inventory/returns/summary');
        return data.data;
    }

    async getEligibleReceipts(params?: Record<string, any>): Promise<EligibleResponse> {
        const { data } = await api.get('/member/inventory/returns/eligible-receipts', { params });
        return data.data;
    }

    async getReturns(params?: Record<string, any>): Promise<ReturnListResponse> {
        const { data } = await api.get('/member/inventory/returns', { params });
        return data.data;
    }

    async findExistingByReceipt(
        goodsReceiveId: number,
        receiveNumber?: string,
    ): Promise<ReturnItem | null> {
        const result = await this.getReturns({
            limit: 100,
            ...(receiveNumber ? { search: receiveNumber } : {}),
        });

        return result.results.find(
            (item) =>
                Number(item.goods_receive?.id) === Number(goodsReceiveId) &&
                item.status?.code !== 'rejected',
        ) ?? null;
    }

    async getReturn(id: number): Promise<ReturnItem> {
        const { data } = await api.get(`/member/inventory/returns/${id}`);
        return data.data;
    }
    
    async getReturnCouriers(payload: {
        goods_receive_id: number;
        address_id: number;
        items: Array<{ goods_receive_detail_id: number; quantity: number }>;
        couriers?: string[];
    }): Promise<{ results: any[] }> {
        const { data } = await api.post('/member/inventory/returns/shipping/couriers', payload);
        return data.data;
    }

    async getReturnSchedules(payload: {
        goods_receive_id: number;
        address_id: number;
        items: Array<{ goods_receive_detail_id: number; quantity: number }>;
    }): Promise<{ results: any[] }> {
        const { data } = await api.post('/member/inventory/returns/shipping/schedules', payload);
        return data.data;
    }

    async storeReturn(payload: ReturnPayload): Promise<ReturnItem> {
        const { data } = await api.post('/member/inventory/returns', payload);
        return data.data;
    }

    async shipReturn(id: number, payload: { pickup_schedule: string }): Promise<ReturnItem> {
        const { data } = await api.post(`/member/inventory/returns/${id}/ship`, payload);
        return data.data;
    }

    async getApprovedReturnSchedules(id: number): Promise<{
        reference_type: string;
        results: Array<{
            time: string;
            available_until: string;
            is_available: boolean;
        }>;
    }> {
        const { data } = await api.get(
            `/member/inventory/returns/${id}/shipping/schedules`,
        );
        return data.data;
    }

    async confirmReplacement(id: number): Promise<ReturnItem> {
        const { data } = await api.post(
            `/member/inventory/returns/${id}/replacement/receive`,
            {},
        );
        return data.data;
    }
}

export default new ReturnService();
