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
} from '@/member/types/network';

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
}

export default new NetworkService();
