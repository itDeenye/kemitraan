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

export interface MonthlyRewardItem {
    id: number;
    year: number;
    month: number;
    total_points: number;
    reward_value: number;
    is_processed: boolean;
    processed_at: string | null;
    payment_responsibility: {
        type: "sponsor" | "company";
        label: string;
        responsible_sponsor: {
            id: number;
            code: string;
            name: string;
            level: {
                id: number;
                code: string;
                name: string;
            };
        } | null;
    };
}

export interface DownlineMonthlyRewardItem extends MonthlyRewardItem {
    member: {
        id: number;
        code: string;
        name: string;
        level: {
            id: number;
            code: string;
            name: string;
        };
    };
    bank: {
        id: number;
        code: string | null;
        name: string | null;
        account_name: string | null;
        account_number: string | null;
    } | null;
    point_value: number;
}

export interface StockistRewardItem {
    id: number;
    year: number;
    month: number;
    total_spending: number;
    percentage: number;
    voucher_value: number;
    used_value: number;
    remaining_value: number;
    status: string;
    expiry_date: string | null;
    used_trx_id: number | null;
    used_trx_code: string | null;
    created_at: string;
}

export interface MonthlyRewardSummary {
    total_akumulasi: number;
    total_dibayarkan: number;
    total_belum_dibayarkan?: number;
}

export interface StockistRewardSummary {
    total_pembelanjaan: number;
    total_voucher: number;
    persentase_voucher: number;
}

export interface MonthlyRewardResponse {
    success: boolean;
    message: string;
    summary: MonthlyRewardSummary;
    summary_total?: MonthlyRewardSummary;
    available_years?: number[];
    data: {
        results: MonthlyRewardItem[];
        pagination: DataTablePagination;
    };
}

export interface DownlineMonthlyRewardResponse {
    success: boolean;
    message: string;
    summary: MonthlyRewardSummary;
    action_count: number;
    available_years?: number[];
    data: {
        results: DownlineMonthlyRewardItem[];
        pagination: DataTablePagination;
    };
}

export interface MonthlyRewardGrowthSeriesItem {
    month: number;
    label: string;
    reward_value: number;
    total_points: number;
}

export interface MonthlyRewardGrowthResponse {
    success: boolean;
    message: string;
    data: {
        period: {
            year: number;
            month: number;
            label: string;
            range_label: string;
        };
        summary: {
            year: number;
            month: number;
            label: string;
            member_level: {
                id: number;
                code: string;
                name: string;
            };
            minimum_points: number;
            remaining_points: number;
            is_qualified: boolean;
            monthly_reward: number;
            total_points: number;
            source: "realtime" | "snapshot";
            is_realtime: boolean;
        };
        growth: {
            percent: number;
            status: "up" | "down" | "flat";
            label: string;
        };
        series: MonthlyRewardGrowthSeriesItem[];
    };
}

export interface StockistRewardResponse {
    success: boolean;
    message: string;
    summary: StockistRewardSummary;
    available_years?: number[];
    data: {
        results: StockistRewardItem[];
        pagination: DataTablePagination;
    };
}

export interface AnnualMonthItem {
    month: number;
    total_points: number;
}

export interface AnnualRewardData {
    year: number;
    total_points: number;
    months: AnnualMonthItem[];
}

export interface AnnualRewardResponse {
    success: boolean;
    message: string;
    available_years?: number[];
    data: AnnualRewardData;
}

export interface SharingProfitItem {
    id: number;
    transaction: {
        id: number;
        code: string;
        amount: number;
    };
    buyer: {
        id: number;
        code: string;
        name: string;
    };
    percentage: number;
    amount: number;
    status: string;
    bank: {
        id: number;
        account_name: string;
        account_number: string;
    } | null;
    note: string | null;
    created_at: string;
    approved_at: string | null;
    paid_at: string | null;
}

export interface SharingProfitResponse {
    success: boolean;
    message: string;
    data: {
        results: SharingProfitItem[];
        pagination: DataTablePagination;
    };
}

export interface SharingProfitDetailResponse {
    success: boolean;
    message: string;
    data: SharingProfitItem;
}
