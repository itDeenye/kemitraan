<template>
    <div class="screen-body">
        <div v-if="isLoading" style="padding: 24px; text-align: center">
            <v-progress-circular
                indeterminate
                color="primary"
                size="24"
            ></v-progress-circular>
            <p style="margin: 12px 0 0; color: var(--muted); font-size: 13px">
                Memuat notifikasi...
            </p>
        </div>

        <template v-else>
            <!-- Transaction Notifications -->
            <div v-if="notifications.transaction_notifications.length > 0">
                <div class="section-heading mt-3">
                    <h3>Pemberitahuan Sistem</h3>
                </div>
                <div class="card list-card mt-3">
                    <div
                        v-for="item in notifications.transaction_notifications"
                        :key="`trans-${item.id}`"
                        class="list-row"
                        :style="{
                            background: item.is_read
                                ? 'transparent'
                                : '#f0fdf4',
                        }"
                        role="button"
                        tabindex="0"
                        @click="handleNotificationClick(item)"
                        @keyup.enter="handleNotificationClick(item)"
                        style="cursor: pointer"
                    >
                        <div class="row-icon">
                            <v-icon
                                :icon="
                                    item.is_read
                                        ? 'mdi-bell-outline'
                                        : 'mdi-bell-badge'
                                "
                                :color="item.is_read ? 'grey' : 'primary'"
                                size="16"
                            />
                        </div>
                        <div class="row-main">
                            <strong>{{ item.title }}</strong>
                            <span>{{ item.content }}</span>
                        </div>
                        <div class="row-side" style="text-align: right">
                            <span
                                style="font-size: 11px; color: var(--muted)"
                                >{{ formatDateTime(item.created_at) }}</span
                            >
                            <div v-if="!item.is_read" style="margin-top: 4px">
                                <v-badge color="primary" dot inline></v-badge>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expiring Products -->
            <div v-if="notifications.expiring_products.length > 0">
                <div class="section-heading mt-6">
                    <h3>Produk Mendekati Kedaluwarsa</h3>
                </div>
                <div class="card list-card mt-3">
                    <div
                        v-for="item in notifications.expiring_products"
                        :key="`exp-${item.product_id}-${item.expire_date}`"
                        class="list-row"
                        role="button"
                        tabindex="0"
                        style="cursor: pointer"
                        @click="openStock"
                        @keyup.enter="openStock"
                    >
                        <div class="row-icon">
                            <v-icon
                                icon="mdi-calendar-alert"
                                color="orange"
                                size="16"
                            />
                        </div>
                        <div class="row-main">
                            <strong>{{ item.product_name }}</strong>
                            <span>Kode: {{ item.product_code }}</span>
                        </div>
                        <div class="row-side">
                            <span class="badge orange"
                                >ED: {{ formatDate(item.expire_date) }}</span
                            >
                            <v-icon
                                icon="mdi-chevron-right"
                                color="grey"
                                size="18"
                                class="ml-1"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Replenishment Needed -->
            <div v-if="notifications.replenishment_needed.length > 0">
                <div class="section-heading mt-6">
                    <h3>Produk Habis</h3>
                    <span>Segera tambah stok</span>
                </div>
                <div class="card list-card mt-3">
                    <div
                        v-for="item in notifications.replenishment_needed"
                        :key="`rep-${item.product_id}`"
                        class="list-row"
                        role="button"
                        tabindex="0"
                        style="cursor: pointer"
                        @click="openStock"
                        @keyup.enter="openStock"
                    >
                        <div class="row-icon">
                            <v-icon
                                icon="mdi-package-variant-closed"
                                color="red"
                                size="16"
                            />
                        </div>
                        <div class="row-main">
                            <strong>{{ item.product_name }}</strong>
                            <span>Kode: {{ item.product_code }}</span>
                        </div>
                        <div class="row-side">
                            <span class="badge red"
                                >Sisa: {{ item.current_stock }}</span
                            >
                            <v-icon
                                icon="mdi-chevron-right"
                                color="grey"
                                size="18"
                                class="ml-1"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-if="
                    notifications.transaction_notifications.length === 0 &&
                    notifications.expiring_products.length === 0 &&
                    notifications.replenishment_needed.length === 0
                "
                style="padding: 24px; text-align: center"
                class="card mt-3"
            >
                <v-icon
                    icon="mdi-bell-sleep-outline"
                    size="32"
                    color="grey-lighten-1"
                    class="mb-2"
                ></v-icon>
                <p style="margin: 0; color: var(--muted); font-size: 13px">
                    Tidak ada notifikasi saat ini
                </p>
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import notificationService from "@/member/services/notification.service";
import type {
    NotificationAction,
    NotificationData,
    NotificationItem,
} from "@/member/types/notification";
import { useMemberNotificationBadge } from "@/member/composables/useMemberNotificationBadge";
import { useFormatter } from "@/shared/composables/useFormatter";
import { encodeRouteId } from "@/shared/utils/route-id";

const { formatDateTime, formatDate } = useFormatter();
const router = useRouter();
const { setUnreadCount, decrementUnreadCount } =
    useMemberNotificationBadge();

const isLoading = ref(true);
const notifications = ref<NotificationData>({
    unread_count: 0,
    transaction_notifications: [],
    expiring_products: [],
    replenishment_needed: [],
});

const fetchNotifications = async () => {
    isLoading.value = true;
    try {
        const response = await notificationService.getNotifications();
        if (response.success && response.data) {
            notifications.value = response.data;
            setUnreadCount(response.data.unread_count);
        }
    } catch (error) {
        console.error("Failed to fetch notifications:", error);
    } finally {
        isLoading.value = false;
    }
};

const notificationTarget = (action: NotificationAction | null) => {
    if (!action) return null;

    switch (action.type) {
        case "open_sale_order":
            return `/member/transactions/sales/${encodeRouteId(action.transaction_id)}`;
        case "open_stock":
            return "/member/stock";
        case "open_purchase_order":
            return `/member/transactions/orders/${encodeRouteId(action.transaction_id)}`;
        case "open_return":
            return `/member/stock/returns/${encodeRouteId(action.return_id)}`;
    }

    return null;
};

const handleNotificationClick = async (notification: NotificationItem) => {
    if (!notification.is_read) {
        notification.is_read = true;
        notifications.value.unread_count = Math.max(
            0,
            notifications.value.unread_count - 1,
        );
        decrementUnreadCount();

        try {
            const response = await notificationService.markAsRead(
                notification.id,
            );
            notification.read_at = response.data.read_at;
            notifications.value.unread_count = response.data.unread_count;
            setUnreadCount(response.data.unread_count);
        } catch (error) {
            console.error("Failed to mark notification as read:", error);
            notification.is_read = false;
            notifications.value.unread_count += 1;
            setUnreadCount(notifications.value.unread_count);
        }
    }

    const target = notificationTarget(notification.action);
    if (target) {
        await router.push(target);
    }
};

const openStock = () => router.push("/member/stock");

onMounted(() => {
    fetchNotifications();
});
</script>
