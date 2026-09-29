import api from "@/shared/services/api";
import type {
    TransactionReturnDetail,
    ReturnApprovePayload,
    ReturnRejectPayload,
    ReturnShipReplacementPayload,
    ReturnCourierOptionsPayload,
    ReturnCourierOptionsResponse,
    ReturnPickupSchedulesResponse,
} from "@/admin/types/transaction-return";

class TransactionReturnService {
    async getDetail(id: number | string): Promise<TransactionReturnDetail> {
        const { data } = await api.get(`/admin/transactions/returns/${id}`);
        return data.data;
    }

    async approve(
        id: number | string,
        payload: ReturnApprovePayload,
    ): Promise<any> {
        const { data } = await api.post(
            `/admin/transactions/returns/${id}/approve`,
            payload,
        );
        return data;
    }

    async reject(
        id: number | string,
        payload?: ReturnRejectPayload,
    ): Promise<any> {
        const { data } = await api.post(
            `/admin/transactions/returns/${id}/reject`,
            payload,
        );
        return data;
    }

    async receive(
        id: number | string,
    ): Promise<any> {
        const { data } = await api.post(
            `/admin/transactions/returns/${id}/receive`,
            {},
        );
        return data;
    }

    async shipReplacement(
        id: number | string,
        payload: ReturnShipReplacementPayload,
    ): Promise<any> {
        const { data } = await api.post(
            `/admin/transactions/returns/${id}/replacement/ship`,
            payload,
        );
        return data;
    }

    async getCourierOptions(
        returnId: number | string,
        referenceType: string,
        payload: ReturnCourierOptionsPayload,
    ): Promise<ReturnCourierOptionsResponse> {
        const { data } = await api.post(
            `/admin/transactions/returns/${returnId}/shipping/${referenceType}/couriers`,
            payload,
        );
        return data.data;
    }

    async getPickupSchedules(
        returnId: number | string,
        referenceType: string,
    ): Promise<ReturnPickupSchedulesResponse> {
        const { data } = await api.get(
            `/admin/transactions/returns/${returnId}/shipping/${referenceType}/schedules`,
        );
        return data.data;
    }
}

export default new TransactionReturnService();
