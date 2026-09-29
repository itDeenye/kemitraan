import type { DataTablePagination } from '@/member/types/network';

export interface ReturnCourierRate {
    courier_code: string;
    courier_name: string;
    service_type: string;
    cost: number;
    etd: string;
    drop_off_available: boolean;
    force_insurance: boolean;
    insurance: number;
    pickup_method: string;
    logo_url?: string | null;
}

export interface ReturnPickupSchedule {
    time: string;
    label: string;
    is_available: boolean;
}

export interface ReturnShipping {
    method: string;
    courier?: string;
    service?: string;
    etd?: string;
    pickup_method?: string;
    pickup_schedule?: string;
    pickup_number?: string;
    delivery_note_number?: string | null;
    items?: ReturnShippingItem[];
    tracking_number?: string;
    cost?: number;
    insurance?: number;
    force_insurance?: boolean;
    total_cost?: number;
    package_weight?: number;
    delivery_status?: string;
    cost_bearer?: string;
    location?: {
        id: number;
        name: string;
        address: string;
        phone: string;
    };
    pin?: string;
    pickup_pin?: string;
    verification_code?: string;
}

export interface ReturnShippingItem {
    product_id: number;
    quantity: number;
    batch_number?: string;
    expiry_date?: string;
}

export interface ReturnDetailItem {
    id: number;
    goods_receive_detail_id?: number | null;
    product_name?: string;
    product_code?: string;
    product: {
        id: number;
        code: string;
        name: string;
        image_url: string;
    };
    quantity: number;
    requested_quantity?: number;
    received_quantity?: number;
    not_received_quantity?: number;
    replacement_quantity?: number;
    batch_number?: string;
    expiry_date?: string;
    reason: string;
}

export interface ReturnStatusLog {
    status: string;
    label: string;
    note: string | null;
    created_at: string;
}

export interface ReturnActions {
    can_ship_return: boolean;
    requires_pickup_schedule: boolean;
    can_confirm_replacement: boolean;
    can_show_pickup_code: boolean;
    pickup_code: string | null;
    verification_code: string | null;
}

export interface ReturnSummary {
    product_count: number;
    total_quantity: number;
    requested_quantity?: number;
    received_quantity?: number;
    not_received_quantity?: number;
    replacement_quantity?: number;
}

export interface ReturnItem {
    id: number;
    code: string;
    transaction: {
        id: number;
        code: string;
    };
    goods_receive?: {
        id: number;
        number: string;
    } | null;
    pickup_address?: {
        address_id: number;
        name: string;
        phone: string;
        address: string;
        province_id: number;
        city_id: number;
        district_id: number;
        subdistrict_id: number;
    };
    description: string;
    status: {
        code: string;
        label: string;
    };
    return_shipping?: ReturnShipping | null;
    replacement_shipping?: ReturnShipping | null;
    summary: ReturnSummary;
    actions?: ReturnActions;
    attachments?: {
        images: string[];
        video: string | null;
    };
    created_at: string;
    items?: ReturnDetailItem[];
    status_history?: ReturnStatusLog[];
}

export interface ReturnPayloadItem {
    goods_receive_detail_id: number;
    product_id?: number;
    product_name?: string;
    product_code?: string;
    batch_number?: string;
    max_quantity?: number;
    quantity: number;
    reason: string;
}

export interface ReturnPayload {
    goods_receive_id: number;
    address_id: number;
    description: string;
    image_urls: string[];
    video_url?: string | null;
    shipping_method: 'pickup' | 'courier_express';
    delivery_note_number: string;
    shipping_cost?: number;
    courier?: {
        courier_code: string;
        courier_name: string;
        service_type: string;
        cost: number;
        etd?: string;
        drop_off_available?: boolean;
        force_insurance?: boolean;
        insurance?: number;
        pickup_method?: string;
        pickup_schedule?: string | null;
    } | null;
    items: ReturnPayloadItem[];
}

export interface ReturnListResponse {
    results: ReturnItem[];
    pagination: DataTablePagination;
}

export interface ReturnActionSummary {
    eligible: number;
    action_required: number;
    history: number;
    total_actions: number;
}
