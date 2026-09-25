import api from '@/shared/services/api';
import type {
    MemberAddressPayload,
    MemberBankPayload,
    PasswordPayload,
    ProfilePayload,
} from '@/member/types/profile';

class ProfileService {
    /** Fetch member profile */
    async getProfile() {
        const { data } = await api.get('/member/profile');
        return data;
    }

    /** Update basic profile info */
    async updateProfile(payload: ProfilePayload) {
        const { data } = await api.put('/member/profile', payload);
        return data;
    }

    /** Update profile photo from an uploaded media URL */
    async updateProfilePhoto(imageUrl: string | null) {
        const { data } = await api.put('/member/profile/photo', {
            image_url: imageUrl,
        });
        return data;
    }

    /** Update password */
    async updatePassword(payload: PasswordPayload) {
        const { data } = await api.put('/member/profile/password', payload);
        return data;
    }

    /** Fetch member bank accounts */
    async getBanks() {
        const { data } = await api.get('/member/banks');
        return data;
    }

    /** Fetch a bank account detail */
    async getBank(id: number) {
        const { data } = await api.get(`/member/banks/${id}`);
        return data;
    }

    /** Create a new bank account */
    async createBank(payload: MemberBankPayload) {
        const { data } = await api.post('/member/banks', payload);
        return data;
    }

    /** Update a bank account */
    async updateBank(id: number, payload: Partial<MemberBankPayload>) {
        const { data } = await api.put(`/member/banks/${id}`, payload);
        return data;
    }

    /** Delete a bank account */
    async deleteBank(id: number) {
        const { data } = await api.delete(`/member/banks/${id}`);
        return data;
    }

    /** Set bank account as default */
    async setDefaultBank(id: number) {
        const { data } = await api.put(`/member/banks/${id}/default`);
        return data;
    }

    /** Fetch member addresses */
    async getAddresses() {
        const { data } = await api.get('/member/addresses');
        return data;
    }

    /** Fetch an address detail */
    async getAddress(id: number) {
        const { data } = await api.get(`/member/addresses/${id}`);
        return data;
    }

    /** Create a new address */
    async createAddress(payload: MemberAddressPayload) {
        const { data } = await api.post('/member/addresses', payload);
        return data;
    }

    /** Update an address */
    async updateAddress(id: number, payload: Partial<MemberAddressPayload>) {
        const { data } = await api.put(`/member/addresses/${id}`, payload);
        return data;
    }

    /** Delete an address */
    async deleteAddress(id: number) {
        const { data } = await api.delete(`/member/addresses/${id}`);
        return data;
    }

    /** Set address as default */
    async setDefaultAddress(id: number) {
        const { data } = await api.put(`/member/addresses/${id}/default`);
        return data;
    }
}

export default new ProfileService();
