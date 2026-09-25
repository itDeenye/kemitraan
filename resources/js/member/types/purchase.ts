import type { SaleOrderType, SalePreorder } from "./sale";

export interface PurchaseOrderSummary {
    purchases: {
        total: number;
        action_required: number;
        waiting_payment: number;
        waiting_payment_approval: number;
        processing: number;
        delivery: number;
        completed: number;
        cancelled: number;
    };
    sales: {
        total: number;
        action_required: number;
        waiting_payment: number;
        waiting_payment_approval: number;
        processing: number;
        delivery: number;
        completed: number;
        cancelled: number;
    };
    goods_receipts: {
        ready_to_receive: number;
    };
}

export interface DataTablePagination {
    total_data: number;
    total_page: number;
    total_display: number;
    first_page: boolean;
    last_page: boolean;
    prev: number;
    current: number;
    next: number;
    detail: number[];
    start: number;
    end: number;
}

export interface PurchaseCategory {
    id: number;
    name: string;
    description: string;
}

export interface PurchaseProduct {
    id: number;
    code: string;
    name: string;
    description: string;
    bpom_number: string;
    image: string | null;
    image_filename: string;
    price: number;
    customer_price: number;
    weight_grams: number;
    dimensions_cm: {
        length: number;
        width: number;
        height: number;
    };
    unit: string;
    is_package: boolean;
    stock: {
        available: number;
        status: {
            code: "available" | "low_stock" | "preorder" | "out_of_stock";
            label: string;
        };
        requires_preorder: boolean;
    };
    category: PurchaseCategory;
}

export interface PurchaseCheckoutBuyer {
    id: number;
    code: string;
    name: string;
    level: string;
    minimum_order: number;
    destination: {
        address_id: number;
        name: string;
        phone: string;
        address: string;
        province_id: number;
        province_name: string;
        city_id: number;
        city_name: string;
        district_id: number;
        district_name: string;
        subdistrict_id: number;
        subdistrict_name: string;
        zipcode: number | string;
        latitude: string | null;
        longitude: string | null;
    } | null;
}

export interface PurchaseCheckoutSeller {
    type: string;
    id: number;
    code: string | null;
    name: string;
    address: string;
    origin: {
        type?: string;
        id?: number;
        name: string;
        phone: string;
        address: string;
        province_id: number;
        province_name: string;
        city_id: number;
        city_name: string;
        district_id: number;
        district_name: string;
        subdistrict_id: number;
        subdistrict_name: string;
        zipcode: number | string;
        latitude: string | null;
        longitude: string | null;
    } | null;
}

export interface PurchaseCheckoutWarehouse {
    id: number;
    name: string;
    address: string;
}

export interface PurchaseCheckoutAddress {
    id: number;
    label: string;
    recipient: string;
    phone: string;
    address: string;
    is_default: boolean;
    destination: any;
}

export interface PurchaseCheckoutBank {
    id: number;
    type?: string;
    code: string;
    name: string;
    account_name: string;
    account_number: string;
}

export interface PurchaseCheckoutShippingMethod {
    code: string;
    name: string;
}

export interface PurchaseCheckoutOptions {
    buyer: PurchaseCheckoutBuyer;
    seller: PurchaseCheckoutSeller;
    warehouses?: PurchaseCheckoutWarehouse[];
    addresses: PurchaseCheckoutAddress[];
    banks: PurchaseCheckoutBank[];
    spread_payment_banks?: PurchaseCheckoutBank[];
    shipping_methods: PurchaseCheckoutShippingMethod[];
    voucher: {
        id: number;
        amount: number;
        expiry_date?: string;
        can_apply: boolean;
    } | null;
}

export interface PurchaseOrder {
    id: number;
    code: string;
    parent_transaction_id?: number;
    seller: {
        type: string;
        id: number;
        code: string | null;
        name: string;
        origin?: {
            name?: string;
            phone?: string;
            address?: string;
            city?: { id?: number; name?: string };
            district?: { id?: number; name?: string };
            subdistrict?: { id?: number; name?: string };
            province?: { id?: number; name?: string };
            postal_code?: string | null;
            type?: string;
        };
    };
    buyer?: {
        type: string;
        id: number;
        code: string | null;
        name: string;
        destination?: {
            name?: string;
            phone?: string;
            address?: string;
            city?: { id?: number; name?: string };
            district?: { id?: number; name?: string };
            subdistrict?: { id?: number; name?: string };
            province?: { id?: number; name?: string };
            postal_code?: string | null;
            type?: string;
        };
    };
    is_preorder: boolean;
    order_type?: SaleOrderType;
    preorder?: SalePreorder | null;
    summary: {
        product_count: number;
        total_quantity: number;
        preorder_quantity: number;
        product_total?: number;
        discount_percent?: number;
        product_discount?: number;
        voucher_id?: number;
        voucher_discount?: number;
        total_discount?: number;
        after_discount?: number;
        shipping_cost?: number;
        shipping_cost_insurance?: number;
        shipping_cost_total?: number;
        payment_charge?: number;
        grand_total: number;
        bill_amount?: number;
    };
    product_preview?: {
        id: number;
        code: string;
        name: string;
        bpom_number: string | null;
        image: string | null;
        price: number;
        quantity: number;
        subtotal: number;
        other_product_count: number;
    };
    items?: any[];
    shipping?: any;
    payment?: any;
    payment_method: string;
    shipping_method: string;
    status: {
        code: string;
        label: string;
    };
    actions: {
        can_cancel: boolean;
        can_upload_payment: boolean;
        waiting_for_previous_approval?: boolean;
        can_receive: boolean;
        can_show_pickup_code: boolean;
    };
    payment_status: string | null;
    status_at: string;
    ordered_at: string;
    created_at?: string;
    invoice?: string;
    total?: number;
    tracking?: {
        is_available: boolean;
        provider: string;
        method: string;
        order_id: string;
        tracking_number: string;
        status: string;
        histories: Array<{
            created_at: string;
            status: string;
            status_code: number;
            driver?: string;
            receiver?: string;
        }>;
    };
    [key: string]: any;
}

export interface GoodsReceipt {
    id: number;
    receipt_number: string;
    status: string;
    created_at: string;
    [key: string]: any;
}
