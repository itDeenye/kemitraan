import type {
    TransactionOrderType,
    TransactionPreorder,
} from "@/admin/types/transaction-order";

export interface TransactionPaymentRegion {
    id: number | null;
    name: string | null;
}

export interface TransactionPaymentLocation {
    type?: string;
    id?: number;
    name: string | null;
    code: string | null;
    phone: string | null;
    address: string | null;
    subdistrict?: TransactionPaymentRegion;
    district?: TransactionPaymentRegion;
    city?: TransactionPaymentRegion;
    province?: TransactionPaymentRegion;
    postal_code?: string | number | null;
    note?: string | null;
    latitude?: string | number | null;
    longitude?: string | number | null;
}

export interface TransactionPaymentParty {
    type: string;
    id: number;
    code: string | null;
    name: string | null;
}

export interface TransactionPaymentSeller extends TransactionPaymentParty {
    origin?: TransactionPaymentLocation | null;
}

export interface TransactionPaymentBuyer extends TransactionPaymentParty {
    destination?: TransactionPaymentLocation | null;
}

export interface TransactionPaymentShipping {
    method: string;
    courier?: string | null;
    service?: string | null;
    vehicle?: string | null;
    order_id?: string | null;
    pickup_method?: string | null;
    pickup_number?: string | null;
    pickup_schedule?: string | null;
    schedule_at?: string | null;
    delivery_note_number?: string | null;
    tracking_number?: string | null;
    delivery_status?: string | null;
    verification_status?: string | null;
    cost?: number;
    admin_fee?: number;
    total_cost?: number;
    insurance?: number;
    shipping_cost_insurance?: number;
}

export interface TransactionPaymentBank {
    id: number;
    code: string | null;
    name: string | null;
    account_name: string | null;
    account_number: string | null;
}

export interface TransactionPaymentDetails {
    id: number;
    bank: TransactionPaymentBank;
    bill_amount: number;
    amount: number;
    receipt_url: string | null;
    status: string;
    administrator_id: number | null;
    note: string | null;
    transferred_at: string | null;
    verified_at: string | null;
    spread_payment: unknown | null;
}

export interface TransactionPaymentTotals {
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
}

export interface TransactionPaymentProduct {
    id: number;
    code: string | null;
    name: string | null;
    image: string | null;
}

export interface TransactionPaymentItem {
    id: number;
    product: TransactionPaymentProduct;
    price: number;
    discount_percent: number;
    discount_value: number;
    net_price: number;
    quantity: number;
    preorder_quantity: number;
    subtotal: number;
    points: number;
}

export interface TransactionPaymentActions {
    can_ship: boolean;
    can_reship: boolean;
    requires_courier_selection: boolean;
    requires_tracking_number: boolean;
    requires_pickup_method: boolean;
    requires_pickup_schedule: boolean;
    requires_pickup_pin: boolean;
}

export interface TransactionPayment {
    id: number;
    code: string;
    type: string;
    order_type: TransactionOrderType;
    seller: TransactionPaymentSeller;
    buyer: TransactionPaymentBuyer;
    totals: TransactionPaymentTotals;
    payment_method: string;
    shipping_method: string;
    status: string;
    actions: TransactionPaymentActions;
    status_at: string | null;
    ordered_at: string | null;
    is_preorder: boolean;
    parent_transaction_id: number;
    preorder: TransactionPreorder | null;
    details: TransactionPaymentItem[];
    payment: TransactionPaymentDetails;
    shipping: TransactionPaymentShipping | null;
}

export interface TransactionPaymentApprovePayload {
    note: string;
}

export interface TransactionPaymentRejectPayload {
    note: string;
}
