import { ref, computed } from "vue";
import { AnalyticService } from "@/admin/services/analytic.service";
import type { AnalyticsResponseData } from "@/admin/types/analytics";
import { useSnackbarStore } from "@/shared/stores/snackbar";

export function useAnalytics() {
    const isLoading = ref(false);
    const analyticsData = ref<AnalyticsResponseData | null>(null);
    const snackbar = useSnackbarStore();

    const fetchAnalytics = async (params?: { date_from?: string; date_to?: string }) => {
        isLoading.value = true;
        try {
            const response = await AnalyticService.getDashboardAnalytics(params);
            if (response.success) {
                analyticsData.value = response.data;
            } else {
                snackbar.showMessage(response.message || "Gagal memuat analitik", "error");
            }
        } catch (error: any) {
            console.error("Failed to fetch analytics:", error);
            snackbar.showMessage(error.response?.data?.message || "Terjadi kesalahan saat memuat analitik", "error");
        } finally {
            isLoading.value = false;
        }
    };

    return {
        isLoading,
        analyticsData,
        fetchAnalytics,
    };
}
