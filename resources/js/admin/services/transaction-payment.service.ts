import api from "@/shared/services/api";
import type { 
    TransactionPayment, 
    TransactionPaymentApprovePayload, 
    TransactionPaymentRejectPayload 
} from "@/admin/types/transaction-payment";

class TransactionPaymentService {
    async getDetail(id: number | string): Promise<TransactionPayment> {
        const { data } = await api.get(`/admin/transactions/payments/${id}`);
        return data.data;
    }

    async approve(id: number | string, payload: TransactionPaymentApprovePayload): Promise<any> {
        const { data } = await api.post(`/admin/transactions/payments/${id}/approve`, payload);
        return data;
    }

    async reject(id: number | string, payload: TransactionPaymentRejectPayload): Promise<any> {
        const { data } = await api.post(`/admin/transactions/payments/${id}/reject`, payload);
        return data;
    }

    async getReceiptObjectUrl(url: string): Promise<string> {
        const mediaUrl = new URL(url, window.location.origin);

        if (!mediaUrl.pathname.startsWith('/api/v1/')) {
            return url;
        }

        const apiPath = `${mediaUrl.pathname.slice('/api/v1'.length)}${mediaUrl.search}`;
        const response = await api.get<Blob>(apiPath, { responseType: 'blob' });

        return URL.createObjectURL(response.data);
    }
}

export default new TransactionPaymentService();
