export interface RewardMember {
    id: number;
    code: string;
    name: string;
    level: {
        id: number;
        code: string;
        name: string;
    };
}

export interface MonthlyReward {
    id: number;
    member: RewardMember;
    upline_id: number;
    year: number;
    month: number;
    total_points: number;
    point_value: number;
    reward_value: number;
    is_processed: boolean;
    administrator_id: number;
    processed_at: string | null;
}

export interface AnnualReward {
    id: number;
    member: RewardMember;
    year: number;
    total_points: number;
    updated_at: string;
    months?: {
        year: number;
        month: number;
        total_points: number;
    }[];
}

export interface StockistReward {
    id: number;
    member: Omit<RewardMember, 'level'>;
    year: number;
    month: number;
    total_spending: number;
    voucher_value: number;
    used_value: number;
    remaining_value: number;
    used_transaction: {
        id: number;
        code: string | null;
    };
    expiry_date: string;
    status: 'available' | 'used' | 'expired';
}
