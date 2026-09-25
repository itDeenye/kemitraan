import api from '@/shared/services/api';

export default {
    async getDashboard(year: number, month: number) {
        try {
            const response = await api.get('/member/dashboard', {
                params: {
                    year,
                    month
                }
            });
            return response.data;
        } catch (error: any) {
            console.error('Error fetching dashboard:', error);
            return {
                success: false,
                message: error.response?.data?.message || 'Gagal memuat dashboard',
                data: null
            };
        }
    }
};
