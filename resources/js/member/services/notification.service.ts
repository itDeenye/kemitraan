import api from "@/shared/services/api";
import type {
    MarkNotificationReadResponse,
    NotificationResponse,
} from "@/member/types/notification";

class NotificationService {
    async getNotifications(): Promise<NotificationResponse> {
        const response = await api.get("/member/notifications");
        return response.data;
    }

    async markAsRead(id: number): Promise<MarkNotificationReadResponse> {
        const response = await api.post(`/member/notifications/${id}/read`);
        return response.data;
    }
}

export default new NotificationService();
