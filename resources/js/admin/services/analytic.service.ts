import api from "@/shared/services/api";
import type { ApiResponse } from "@/shared/types";
import type { AnalyticsResponseData } from "@/admin/types/analytics";

export const AnalyticService = {
    /**
     * Get dashboard analytics data
     * @param params Query parameters (date_from, date_to)
     */
    async getDashboardAnalytics(params?: { date_from?: string; date_to?: string }): Promise<ApiResponse<AnalyticsResponseData>> {
        const response = await api.get<ApiResponse<AnalyticsResponseData>>("/admin/dashboard/analytics", { params });
        return response.data;
    },
};
