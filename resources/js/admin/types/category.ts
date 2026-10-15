export interface Category {
    id: number;
    name: string;
    description: string;
    product_count: number;
    is_active: boolean;
    sort_order: number;
}

export interface CategoryPayload {
    name: string;
    description: string;
    is_active: boolean;
}
