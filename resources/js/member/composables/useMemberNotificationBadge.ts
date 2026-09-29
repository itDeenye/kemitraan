import { readonly, ref } from "vue";
import notificationService from "@/member/services/notification.service";

const unreadCount = ref(0);
let activeRequest: Promise<void> | null = null;

export function useMemberNotificationBadge() {
    const setUnreadCount = (count: number) => {
        unreadCount.value = Math.max(0, Number(count) || 0);
    };

    const decrementUnreadCount = () => {
        setUnreadCount(unreadCount.value - 1);
    };

    const refreshUnreadCount = async () => {
        if (activeRequest) {
            return activeRequest;
        }

        activeRequest = notificationService
            .getNotifications()
            .then((response) => {
                if (response.success) {
                    setUnreadCount(response.data.unread_count);
                }
            })
            .finally(() => {
                activeRequest = null;
            });

        return activeRequest;
    };

    return {
        unreadCount: readonly(unreadCount),
        setUnreadCount,
        decrementUnreadCount,
        refreshUnreadCount,
    };
}
