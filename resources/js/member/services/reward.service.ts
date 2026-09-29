import api from '@/shared/services/api';
import type { 
    AnnualRewardResponse, 
    MonthlyRewardGrowthResponse,
    DownlineMonthlyRewardResponse,
    MonthlyRewardResponse, 
    StockistRewardResponse,
    SharingProfitResponse,
    SharingProfitDetailResponse
} from '@/member/types/reward';

interface RewardListParams {
    page?: number;
    limit?: number;
    year?: string | number;
}

class RewardService {
    async getMonthlyRewards(params: RewardListParams = {}): Promise<MonthlyRewardResponse> {
        const { data } = await api.get('/member/rewards/monthly', { params });
        return data;
    }

    async getMonthlyReward(id: number | string) {
        const { data } = await api.get(`/member/rewards/monthly/${id}`);
        return data;
    }

    async getDownlineMonthlyRewards(params: RewardListParams = {}): Promise<DownlineMonthlyRewardResponse> {
        const { data } = await api.get('/member/rewards/monthly/downlines', { params });
        return data;
    }

    async approveDownlineMonthlyReward(id: number | string) {
        const { data } = await api.post(`/member/rewards/monthly/downlines/${id}/approve`);
        return data;
    }

    async getMonthlyRewardGrowth(params: { year?: string | number; month?: string | number } = {}): Promise<MonthlyRewardGrowthResponse> {
        const { data } = await api.get('/member/rewards/monthly/growth', { params });
        return data;
    }

    async getAnnualRewards(params: { year?: string | number } = {}): Promise<AnnualRewardResponse> {
        const { data } = await api.get('/member/rewards/annual', { params });
        return data;
    }

    async getStockistRewards(params: RewardListParams = {}): Promise<StockistRewardResponse> {
        const { data } = await api.get('/member/rewards/stockists', { params });
        return data;
    }

    async getStockistReward(id: number | string) {
        const { data } = await api.get(`/member/rewards/stockists/${id}`);
        return data;
    }

    async getSharingProfits(params: RewardListParams = {}): Promise<SharingProfitResponse> {
        const { data } = await api.get('/member/rewards/sharing-profits', { params });
        return data;
    }

    async getSharingProfit(id: number | string): Promise<SharingProfitDetailResponse> {
        const { data } = await api.get(`/member/rewards/sharing-profits/${id}`);
        return data;
    }
}

export default new RewardService();
