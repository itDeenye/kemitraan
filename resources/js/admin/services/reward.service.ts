import api from "@/shared/services/api";
import type { MonthlyReward, AnnualReward, StockistReward } from "../types/reward";

class RewardService {
    /**
     * Get Monthly Reward detail
     */
    async getMonthlyDetail(id: number): Promise<MonthlyReward> {
        const response = await api.get(`/admin/rewards/monthly/${id}`);
        return response.data?.data;
    }

    /**
     * Process Monthly Reward
     */
    async processMonthly(id: number): Promise<void> {
        await api.post(`/admin/rewards/monthly/${id}/process`);
    }

    /**
     * Get Annual Reward detail
     */
    async getAnnualDetail(id: number): Promise<AnnualReward> {
        const response = await api.get(`/admin/rewards/annual/${id}`);
        return response.data?.data;
    }

    /**
     * Get Stockist Reward detail
     */
    async getStockistDetail(id: number): Promise<StockistReward> {
        const response = await api.get(`/admin/rewards/stockists/${id}`);
        return response.data?.data;
    }
}

export default new RewardService();
