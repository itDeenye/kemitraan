export type NotificationAction =
    | {
          type: "open_sale_order";
          transaction_id: number;
      }
    | {
          type: "open_stock";
          stock_id: number;
      }
    | {
          type: "open_purchase_order";
          transaction_id: number;
      }
    | {
          type: "open_return";
          return_id: number;
      };

export interface NotificationItem {
    id: number;
    title: string;
    content: string;
    category: string;
    is_read: boolean;
    read_at: string | null;
    created_at: string;
    reference: {
        table: string;
        id: number;
    } | null;
    action: NotificationAction | null;
}

export interface ExpiringProductItem {
    product_id: number;
    product_name: string;
    product_code: string;
    expire_date: string;
}

export interface ReplenishmentNeededItem {
    product_id: number;
    product_name: string;
    product_code: string;
    current_stock: number;
}

export interface NotificationData {
    unread_count: number;
    transaction_notifications: NotificationItem[];
    expiring_products: ExpiringProductItem[];
    replenishment_needed: ReplenishmentNeededItem[];
}

export interface NotificationResponse {
    success: boolean;
    message: string;
    data: NotificationData;
}

export interface MarkNotificationReadResponse {
    success: boolean;
    message: string;
    data: {
        id: number;
        is_read: boolean;
        read_at: string | null;
        unread_count: number;
    };
}
