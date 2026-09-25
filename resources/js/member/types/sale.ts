export interface SaleCustomer {
    id?: number;
    name: string;
    whatsapp: string;
    phone?: string;
    gender?: string;
    address?: string;
    province_id?: number;
    city_id?: number;
    district_id?: number;
    subdistrict_id?: number;
}

export interface PaginatedResponse<T> {
    success: boolean;
    message: string;
    data: {
        results: T[];
        pagination: any;
    };
}

export interface SingleResponse<T> {
    success: boolean;
    message: string;
    data: T;
}

export interface SaleSummary {
    product_count: number;
    total_quantity: number;
    product_total?: number;
    total_discount?: number;
    voucher_discount?: number;
    after_discount?: number;
    shipping_cost?: number;
    shipping_cost_insurance?: number;
    shipping_cost_total?: number;
    payment_charge?: number;
    grand_total: number;
}

export interface SaleProductPreview {
    id: number;
    code: string;
    name: string;
    bpom_number: string | null;
    image: string | null;
    price: number;
    quantity: number;
    subtotal: number;
    other_product_count: number;
}

export interface SaleStatus {
    code: string;
    label: string;
}

export interface SaleActions {
    can_verify_payment: boolean;
    can_ship: boolean;
    can_cancel: boolean;
    requires_tracking_number: boolean;
    requires_pickup_method: boolean;
    requires_pickup_schedule: boolean;
    requires_pickup_pin: boolean;
}

export interface SaleOrderType {
    code: "preorder" | "regular";
    label: "PO" | "Reguler";
}

export interface SalePreorderParty {
    type: string;
    id: number;
    code: string | null;
    name: string | null;
}

export interface SalePreorderTransaction {
    id: number | null;
    code: string | null;
    status: string;
    status_label: string;
    payment_status: string;
    payment_status_label: string;
    ordered_at: string | null;
    is_projected?: boolean;
}

export interface SalePreorderChainItem {
    sequence: number;
    transaction: SalePreorderTransaction;
    seller: SalePreorderParty;
    buyer: SalePreorderParty;
    is_projected?: boolean;
}

export interface SalePreorder {
    current_transaction_id: number;
    current_member_transaction_id: number;
    origin: {
        transaction: SalePreorderTransaction;
        buyer: SalePreorderParty;
    };
    chain: SalePreorderChainItem[];
}

export interface SalePayment {
    id?: number;
    bank?: {
        id: number;
        code?: string;
        name?: string;
        bank_name?: string;
        account_name: string;
        account_number: string;
    };
    bill_amount?: number;
    amount?: number;
    receipt_url: string | null;
    status: SaleStatus;
    note?: string;
    transferred_at?: string;
    verified_at?: string;
}

export interface SaleOrder {
    id: number;
    code: string;
    type: "stock" | "retail";
    parent_transaction_id: number;
    is_preorder: boolean;
    order_type: SaleOrderType;
    preorder?: SalePreorder | null;
    seller: any;
    buyer: any;
    customer: SaleCustomer;
    summary: SaleSummary;
    product_preview: SaleProductPreview;
    payment_method: string;
    payment_status: string | null;
    shipping_method: string;
    status: SaleStatus;
    actions: SaleActions;
    status_at: string;
    ordered_at: string;
    payment: SalePayment;
}

export interface SaleItem {
    id: number;
    product: {
        id: number;
        code: string;
        name: string;
        bpom_number: string | null;
        image: string | null;
    };
    price: number;
    discount_percent: number;
    discount_value: number;
    net_price: number;
    quantity: number;
    preorder_quantity: number;
    subtotal: number;
    points: number;
}

export interface SaleShipping {
    method: string;
    location?: string;
    code?: string | null;
    courier: string | null;
    service: string | null;
    delivery_note_number: string | null;
    items: any[];
    tracking_number: string | null;
    verification_status?: string | null;
    cost: number;
    insurance: number;
    shipping_cost_insurance: number;
    total_cost: number;
    destination: string | null;
}

export interface SaleOrderDetail extends Omit<SaleOrder, 'product_preview'> {
    items: SaleItem[];
    shipping: SaleShipping;
}

export interface ProductCatalogCategory {
    id: number;
    name: string;
}

export interface ProductCatalog {
    id: number;
    code: string;
    name: string;
    bpom_number: string;
    image: string | null;
    price: number;
    available_stock: number;
    weight_grams: number;
    unit: string;
    category: ProductCatalogCategory;
}

export interface SaleFormOptions {
    seller: {
        id: number;
        code: string;
        name: string;
        level: string;
        address?: string;
        origin?: {
            name?: string;
            phone?: string;
            address?: string;
            province_id?: number | string;
            city_id?: number | string;
            district_id?: number | string;
            subdistrict_id?: number;
            province_name?: string;
            city_name?: string;
            district_name?: string;
            subdistrict_name?: string;
            zipcode?: number;
            latitude?: string | null;
            longitude?: string | null;
        };
    };
    bank_accounts: {
        id: number;
        bank_code: string;
        bank_name: string;
        account_name: string;
        account_number: string;
    }[];
    payment_methods: {
        code: string;
        name: string;
    }[];
    shipping_methods: {
        code: string;
        name: string;
    }[];
}

export interface SaleCustomerPayload {
    name: string;
    whatsapp: string;
    phone?: string;
    gender: string;
    birth_date?: string;
    address: string;
    province_id: number | string | null;
    city_id: number | string | null;
    district_id: number | string | null;
    subdistrict_id: number | string | null;
}

export interface SaleOrderPayload {
    customer_id: number;
    payment_method: string;
    bank_account_id?: number;
    shipping_method: string;
    courier?: {
        name: string;
        service: string;
        type?: string;
        etd?: string;
        force_insurance?: boolean;
        insurance?: number;
        cost: number;
    };
    items: {
        product_id: number;
        quantity: number;
        batches: {
            quantity: number;
            batch_number: string;
            expiry_date: string;
        }[];
    }[];
}

export interface SalePaymentApprovePayload {
    receipt_url: string;
    note?: string;
}

export interface SalePaymentRejectPayload {
    note: string;
}

export interface SaleShipPayload {
    delivery_note_number?: string;
    tracking_number?: string;
    pickup_pin?: string;
    pickup_method?: "PICKUP" | "DROP-OFF";
    pickup_schedule?: string;
    items: {
        product_id: number;
        quantity: number;
        batch_number: string;
        expiry_date: string; // YYYY-MM-DD
    }[];
}

export interface ShippingRatePayload {
    origin: {
        district_id: number;
        subdistrict_id: number;
    };
    destination: {
        district_id: number;
        subdistrict_id: number;
    };
    couriers: string[];
    items: {
        product_id: number;
        quantity: number;
    }[];
}
