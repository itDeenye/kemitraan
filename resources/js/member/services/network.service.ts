import api from '@/shared/services/api';
import type {
    GenealogyParams,
    GenealogyResponse,
    RegistrationListParams,
    RegistrationOptions,
    RegistrationListResponse,
    RegistrationListItem,
    RegistrationPayload,
    RegistrationDetail,
    RegistrationApprovalPayload,
} from '@/member/types/network';
import type { ApiResponse } from '@/shared/types';

class NetworkService {
    async getGenealogy(params: GenealogyParams = {}): Promise<GenealogyResponse> {
        const { data } = await api.get('/member/network/genealogy', { params });
        return data.data;
    }

    async getRegistrationOptions(): Promise<RegistrationOptions> {
        const { data } = await api.get('/member/network/registrations/options');
        return data.data;
    }

    async getRegistrations(params: RegistrationListParams = {}): Promise<RegistrationListResponse> {
        const { data } = await api.get('/member/network/registrations', { params });
        return data.data;
    }

    async getRegistration(id: number | string): Promise<RegistrationDetail> {
        const { data } = await api.get(`/member/network/registrations/${id}`);
        return data.data;
    }

    async storeRegistration(payload: RegistrationPayload): Promise<RegistrationListItem> {
        const { data } = await api.post('/member/network/registrations', payload);
        return data.data;
    }

    async getRegistrationApprovals(params: RegistrationListParams = {}): Promise<RegistrationListResponse> {
        const { data } = await api.get('/member/network/registration-approvals', { params });
        return data.data;
    }

    async getRegistrationApproval(id: number): Promise<RegistrationDetail> {
        const { data } = await api.get(`/member/network/registration-approvals/${id}`);
        return data.data;
    }

    async approveRegistration(
        id: number,
        payload: RegistrationApprovalPayload,
    ): Promise<ApiResponse<RegistrationDetail>> {
        const { data } = await api.post(
            `/member/network/registration-approvals/${id}/approve`,
            payload,
        );
        return data;
    }

    async rejectRegistration(
        id: number,
        payload: RegistrationApprovalPayload,
    ): Promise<ApiResponse<RegistrationDetail>> {
        const { data } = await api.post(
            `/member/network/registration-approvals/${id}/reject`,
            payload,
        );
        return data;
    }
}

export default new NetworkService();
