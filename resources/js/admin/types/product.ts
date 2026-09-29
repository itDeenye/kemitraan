export interface ProductCategory {
    id: number;
    name: string;
}

export interface ProductPrices {
    customer: number;
    members?: {
        member_level_id: number;
        code: string;
        name: string;
        price: number;
    }[];
}

export interface ProductDimensions {
    length: number;
    width: number;
    height: number;
}

export interface Product {
    id: number;
    code: string;
    name: string;
    bpom_number: string | null;
    description: string | null;
    image: string | null;
    image_filename: string | null;
    prices: ProductPrices;
    weight_grams: number;
    dimensions_cm: ProductDimensions;
    unit: string;
    is_publish: boolean;
    is_active: boolean;
    category: ProductCategory | null;
}

export interface ProductPayload {
    category_id: number | null;
    code: string;
    name: string;
    bpom_number?: string | null;
    image_url?: string | null;
    description: string;
    customer_price: number;
    distributor_price?: number;
    agent_price?: number;
    reseller_price?: number;
    member_prices?: { member_level_id: number, price: number }[];
    length?: number;
    width?: number;
    height?: number;
    weight: number;
    unit: string;
    is_publish: boolean;
    is_active: boolean;
}
