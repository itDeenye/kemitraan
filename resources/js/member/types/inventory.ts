import type { DataTablePagination } from '@/member/types/network';
export type { DataTablePagination };

export type {
    ReturnCourierRate,
    ReturnPickupSchedule,
    ReturnShipping,
    ReturnShippingItem,
    ReturnDetailItem,
    ReturnStatusLog,
    ReturnActions,
    ReturnSummary,
    ReturnItem,
    ReturnPayloadItem,
    ReturnPayload,
    ReturnListResponse,
} from './return';

export interface ProductSummary {
    id: number;
    code: string;
    name: string;
    category_id?: number;
    category_name?: string;
    unit?: string;
}

export interface StockItem {
    id: number;
    member_id: number;
    product: ProductSummary;
    balance: number;
    transfer_in: number;
    transfer_out: number;
}

export interface StockMutation {
    id: number;
    member_id: number;
    product: ProductSummary;
    type: string;
    quantity: number;
    unit_price: number;
    balance: number;
    note: string | null;
    datetime: string;
}

export interface EligibleReceiptItem {
    goods_receive_detail_id: number;
    product_id: number;
    product_code: string;
    product_name: string;
    product_image?: string | null;
    product_price?: number;
    product_weight: number;
    batch_number: string;
    expire_date: string | null;
    received_quantity: number;
    returned_quantity: number;
    remaining_quantity: number;
}

export interface EligibleReceipt {
    receive_id: number;
    receive_number: string;
    delivery_note_number?: string | null;
    transaction: {
        id: number;
        code: string;
    };
    seller: {
        type: string;
        id: number;
        code: string | null;
        name: string;
    };
    summary: {
        product_count: number;
        received_quantity: number;
        returned_quantity: number;
        remaining_quantity: number;
    };
    received_at: string;
    return_deadline: string;
    items?: EligibleReceiptItem[];
    shipping_methods?: string[];
}

export interface StockListResponse {
    results: StockItem[];
    pagination: DataTablePagination;
}

export interface MutationListResponse {
    results: StockMutation[];
    pagination: DataTablePagination;
}

export interface StockAdjustmentDetail {
    id: number;
    member_stock_id: number;
    product: ProductSummary;
    type: string;
    quantity: number;
    unit_price: number;
    note: string | null;
}

export interface StockAdjustmentItem {
    id: number;
    code: string;
    note: string | null;
    total_items: number;
    total_quantity: number;
    happened_at: string;
    details?: StockAdjustmentDetail[];
}

export interface StockAdjustmentListResponse {
    results: StockAdjustmentItem[];
    pagination: DataTablePagination;
}

export interface StockAdjustmentPayload {
    product_id: number;
    qty: number;
    reason: string;
}

export interface EligibleReceiptListResponse {
    results: EligibleReceipt[];
    pagination: DataTablePagination;
}
