export interface PromotionProduct {
    id?: number;
    product_id?: number;
    code?: string;
    name?: string;
    qty: number;
    discount_percent: number;
}

export interface Promotion {
    id: number;
    name: string;
    type: string;
    value: number;
    start_date: string;
    end_date: string;
    terms: string;
    product_count: number;
    is_active: boolean;
    products?: PromotionProduct[];
}

export interface PromotionPayload {
    name: string;
    type: string;
    value: number;
    start_date: string;
    end_date: string;
    terms: string;
    is_active: boolean;
    products: {
        product_id: number;
        qty: number;
        discount_percent: number;
    }[];
}
