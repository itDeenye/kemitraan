export interface TransactionReturnMember {
    id: number;
    code: string;
    name: string;
}

export interface TransactionReturnTransaction {
    id: number;
    code: string;
}

export interface TransactionReturnGoodsReceive {
    id: number;
    number: string;
}

export interface TransactionReturnActions {
    can_approve: boolean;
    can_reject: boolean;
    can_receive: boolean;
    can_ship_replacement: boolean;
    requires_pickup_pin: boolean;
}

export interface TransactionReturnPickupAddress {
    address_id: number;
    name: string;
    phone: string;
    address: string;
    province_id: number;
    city_id: number;
    district_id: number;
    subdistrict_id: number;
}

export interface TransactionReturnDetailItem {
    id: number;
    goods_receive_detail_id: number;
    product: {
        id: number;
        code: string;
        name: string;
    };
    quantity: number;
    received_quantity?: number;
    replacement_quantity?: number;
    batch_number: string;
    expiry_date: string;
    reason: string;
}

export interface TransactionReturnStatusHistory {
    status: string;
    note: string;
    created_by: number;
    created_at: string;
}

export interface TransactionReturn {
    id: number;
    code: string;
    transaction: TransactionReturnTransaction;
    goods_receive: TransactionReturnGoodsReceive;
    member: TransactionReturnMember;
    description: string;
    status: string;
    created_at: string;
    actions: TransactionReturnActions;
}

export interface TransactionReturnDetail extends TransactionReturn {
    pickup_address: TransactionReturnPickupAddress;
    attachments: {
        images: string[];
        video: string | null;
    };
    return_shipping: any | null;
    replacement_shipping: any | null;
    details: TransactionReturnDetailItem[];
    status_history: TransactionReturnStatusHistory[];
}

export interface ReturnApproveItem {
    product_id: number;
    quantity: number;
    batch_number: string;
    expiry_date: string;
}

export interface ReturnApprovePayload {
    note?: string;
    pickup_pin?: string;
}

export interface ReturnCourierOption {
    courier_code: string;
    courier_name: string;
    service_type: string;
    cost: number;
    etd: string;
    drop_off_available: boolean;
    force_insurance: boolean;
    insurance: number;
    logo_url: string;
}

export interface ReturnCourierOptionsPayload {
    couriers: string[];
    items: { product_id: number; quantity: number }[];
}

export interface ReturnCourierOptionsResponse {
    reference_type: string;
    origin: Record<string, any>;
    destination: Record<string, any>;
    items: Record<string, any>[];
    package: Record<string, any>;
    results: ReturnCourierOption[];
}

export interface ReturnPickupSchedule {
    time: string;
    available_until: string;
    is_available: boolean;
}

export interface ReturnPickupSchedulesResponse {
    reference_type: string;
    results: ReturnPickupSchedule[];
}

export interface ReturnRejectPayload {
    note?: string;
}

export interface ReturnShipReplacementCourierExpressPayload {
    note: string;
    delivery_note_number: string;
    shipping_method: "courier_express";
    items: ReturnApproveItem[];
    courier: {
        cost: number;
        name: string;
        type: string;
        etd: string;
        insurance: number;
        force_insurance: boolean;
        service: string;
        pickup_method: string;
        pickup_schedule: string;
    };
}

export interface ReturnShipReplacementCourierManualPayload {
    note: string;
    delivery_note_number: string;
    shipping_method: "courier_manual";
    items: ReturnApproveItem[];
    courier: {
        cost: number;
        name: string;
        service: string;
        tracking_number: string;
    };
}

export interface ReturnShipReplacementPickupPayload {
    note: string;
    delivery_note_number: string;
    shipping_method: "pickup";
    items: ReturnApproveItem[];
    courier?: Record<string, never>;
}

export type ReturnShipReplacementPayload =
    | ReturnShipReplacementCourierExpressPayload
    | ReturnShipReplacementCourierManualPayload
    | ReturnShipReplacementPickupPayload;
