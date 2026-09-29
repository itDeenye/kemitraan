import api from "@/shared/services/api";
import type { TransactionOrderSummary, TransactionOrder } from "@/admin/types/transaction-order";

class TransactionOrderService {
    async getSummary(dateFrom: string, dateTo: string): Promise<TransactionOrderSummary> {
        const { data } = await api.get("/admin/transactions/orders/summary", {
            params: {
                date_from: dateFrom,
                date_to: dateTo,
            }
        });
        return data.data;
    }

    async getDetail(id: number | string): Promise<TransactionOrder> {
        const { data } = await api.get(`/admin/transactions/orders/${id}`);
        return data.data;
    }

    async getDocument(id: number | string): Promise<any> {
        const { data } = await api.get(`/admin/transactions/orders/${id}/document`);
        return data.data;
    }

    async approveScreening(id: number | string, note: string): Promise<any> {
        const { data } = await api.post(`/admin/transactions/orders/${id}/stock-screening/approve`, { note });
        return data.data;
    }

    async rejectScreening(id: number | string, note: string): Promise<any> {
        const { data } = await api.post(`/admin/transactions/orders/${id}/stock-screening/reject`, { note });
        return data.data;
    }
}

export default new TransactionOrderService();
