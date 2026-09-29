import api from '@/shared/services/api';

export interface ProfilePayload {
    name: string;
    email: string;
    username?: string;
    image_url?: string;
}

export interface PasswordPayload {
    current_password?: string;
    password?: string;
    password_confirmation?: string;
}

class ProfileService {
    /** Fetch admin profile */
    async getProfile() {
        const { data } = await api.get('/admin/profile');
        return data.data;
    }

    /** Update basic profile info */
    async updateProfile(payload: ProfilePayload) {
        const { data } = await api.put('/admin/profile', payload);
        return data;
    }

    /** Update password */
    async updatePassword(payload: PasswordPayload) {
        const { data } = await api.put('/admin/profile/password', payload);
        return data;
    }
}

export default new ProfileService();
