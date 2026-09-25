export interface Warehouse {
    id: number;
    name: string;
}

export interface ProductSummary {
    id: number;
    code: string;
    name: string;
    category_id?: number;
    category_name?: string;
    unit?: string;
}

export interface WarehouseStock {
    id: number;
    warehouse: Warehouse;
    product: ProductSummary;
    balance: number;
    transfer_in: number;
    transfer_out: number;
}

export interface StockAdjustmentDetail {
    id?: number;
    warehouse_stock_id?: number;
    product_id?: number;
    product?: ProductSummary;
    batch_number: string;
    type: 'in' | 'out';
    quantity: number;
    unit_price?: number;
    note?: string;
}

export interface StockAdjustment {
    id?: number;
    code?: string;
    note: string;
    warehouse_id: number;
    warehouse?: Warehouse;
    administrator?: {
        id: number;
        name: string;
    };
    total_items?: number;
    total_quantity?: number;
    happened_at?: string;
    details: StockAdjustmentDetail[];
}
