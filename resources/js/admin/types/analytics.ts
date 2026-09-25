export interface AnalyticsSummary {
    total_partners: number;
    turnover: number;
    total_orders: number;
    total_commission: number;
    products_sold: number;
    active_orders: number;
    warehouse_stock: number;
}

export interface OrderStatusAnalytic {
    code: string;
    label: string;
    total: number;
}

export interface SalesTrendAnalytic {
    date: string;
    total_orders: number;
    turnover: number;
}

export interface MemberGrowthAnalytic {
    date: string;
    total_members: number;
}

export interface LevelDistributionAnalytic {
    id: number;
    code: string;
    name: string;
    total_members: number;
}

export interface HighlightedMemberAnalytic {
    id: number;
    code: string;
    name: string;
    level: {
        code: string;
        name: string;
    };
    total_direct_downlines: number;
}

export interface RecentOrderAnalytic {
    id: number;
    code: string;
    buyer: {
        type: string;
        name: string;
    };
    total: number;
    ordered_at: string;
    status: {
        code: string;
        label: string;
    };
}

export interface StockAlertAnalytic {
    id: number;
    code: string;
    name: string;
    balance: number;
    min_stock: number;
    stock: number;
}

export interface AnalyticsResponseData {
    period: {
        date_from: string;
        date_to: string;
    };
    summary: AnalyticsSummary;
    order_statuses: OrderStatusAnalytic[];
    sales_trend: SalesTrendAnalytic[];
    member_growth: MemberGrowthAnalytic[];
    level_distribution: LevelDistributionAnalytic[];
    highlighted_members: HighlightedMemberAnalytic[];
    recent_orders: RecentOrderAnalytic[];
    stock_alerts: StockAlertAnalytic[];
}
