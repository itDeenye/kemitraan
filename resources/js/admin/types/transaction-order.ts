export interface TransactionOrderSummary {
    total: number;
    waiting_payment: number;
    waiting_payment_approval: number;
    processing: number;
    delivery: number;
    completed: number;
    cancelled: number;
}

export interface TransactionOrderType {
    code: "preorder" | "regular";
    label: "PO" | "Reguler";
}

export interface TransactionPreorderParty {
    type: string;
    id: number;
    code: string | null;
    name: string | null;
}

export interface TransactionPreorderTransaction {
    id: number;
    code: string;
    status: string;
    status_label: string;
    payment_status: string;
    payment_status_label: string;
    ordered_at: string | null;
}

export interface TransactionPreorderStep {
    sequence: number;
    transaction: TransactionPreorderTransaction;
    seller: TransactionPreorderParty;
    buyer: TransactionPreorderParty;
}

export interface TransactionPreorder {
    current_transaction_id: number;
    origin: {
        transaction: TransactionPreorderTransaction;
        buyer: TransactionPreorderParty;
    };
    chain: TransactionPreorderStep[];
}

export interface TransactionOrder {
    id: number;
    code: string;
    can_sync_supply_chain?: boolean;
    invoice_number: string | null;
    supply_chain_status:
        | "not_integrated"
        | "awaiting_callback"
        | "completed";
    type: string;
    order_type: TransactionOrderType;
    seller: {
        type: string;
        id: number;
        code: string | null;
        name: string;
        origin?: {
            type?: string;
            id?: number;
            code?: string | null;
            name: string | null;
            phone: string | null;
            address: string | null;
            subdistrict?: { id: number | null; name: string | null };
            district?: { id: number | null; name: string | null };
            city?: { id: number | null; name: string | null };
            province?: { id: number | null; name: string | null };
            postal_code?: string | null;
            note?: string | null;
            latitude?: string | null;
            longitude?: string | null;
        } | null;
    };
    buyer: {
        type: string;
        id: number;
        code: string | null;
        name: string;
        destination?: {
            type?: string;
            id?: number;
            code?: string | null;
            name: string | null;
            phone: string | null;
            address: string | null;
            subdistrict?: { id: number | null; name: string | null };
            district?: { id: number | null; name: string | null };
            city?: { id: number | null; name: string | null };
            province?: { id: number | null; name: string | null };
            postal_code?: string | null;
            note?: string | null;
            latitude?: string | null;
            longitude?: string | null;
        } | null;
    };
    totals: {
        total_monthly_points: number;
        total_annual_points: number;
        product_total: number;
        discount_percent: number;
        discount_value: number;
        product_discount: number;
        voucher_id: number;
        voucher_value: number;
        after_discount: number;
        shipping_cost: number;
        shipping_cost_insurance: number;
        shipping_cost_total: number;
        payment_charge: number;
        grand_total: number;
        bill_amount: number;
    };
    payment_method: string;
    shipping_method: string;
    display_shipping_method?: string;
    preorder_fulfillment_mode?: 'direct' | 'upline_transit';
    status: string;
    actions?: {
        can_approve_stock_screening: boolean;
        can_reject_stock_screening: boolean;
        can_ship: boolean;
        requires_tracking_number: boolean;
        requires_pickup_method: boolean;
        requires_pickup_schedule: boolean;
        requires_pickup_pin: boolean;
        can_track: boolean;
        can_simulate_finished: boolean;
    };
    status_at: string;
    ordered_at: string;
    is_preorder?: boolean;
    parent_transaction_id?: number;
    preorder?: TransactionPreorder | null;
    details?: TransactionOrderItem[];
    payment?: any;
    tracking?: {
        is_available: boolean;
        provider: string;
        method: string;
        transaction_id: number;
        order_id: string | null;
        tracking_number: string | null;
        status: string | null;
        courier: string | null;
        service: string | null;
    } | null;
    shipping?: any;
}

export interface TransactionOrderItem {
    id: number;
    product: {
        id: number;
        code: string | null;
        name: string | null;
        bpom_number: string | null;
        image: string | null;
        monthly_points: number;
        annual_points: number;
        weight?: number;
        batch_number?: string | null;
        expiry_date?: string | null;
        batches?: { quantity: number; batch_number: string; expiry_date: string | null }[];
    };
    price: number;
    net_price: number;
    discount_percent: number;
    discount_value: number;
    quantity: number;
    preorder_quantity: number;
    subtotal: number;
    points: number;
    monthly_points: number;
    annual_points: number;
}
