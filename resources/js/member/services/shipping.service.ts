import api from "@/shared/services/api";
import type { ShippingRatePayload, ShippingRateResult } from '@/member/types/shipping';

const shippingService = {
    async getExpressRates(payload: ShippingRatePayload) {
        try {
            const { data } = await api.post('/member/shipping/express/rates', payload);
            return {
                success: true,
                message: data.message || 'Tarif pengiriman berhasil dimuat.',
                data: data.data
            };
        } catch (error: any) {
            return {
                success: false,
                message: error.response?.data?.message || 'Gagal memuat tarif pengiriman.',
                data: null
            };
        }
    }
};

export default shippingService;
