import api from "@/shared/services/api";
import type { ApiResponse } from "@/shared/types";

export interface AdminActionSummary {
    total: number;
    menus: Array<{
        route: string;
        count: number;
    }>;
}

export const AdminActionSummaryService = {
    async get(): Promise<ApiResponse<AdminActionSummary>> {
        const response = await api.get<ApiResponse<AdminActionSummary>>(
            "/admin/dashboard/action-summary",
        );

        return response.data;
    },
};
