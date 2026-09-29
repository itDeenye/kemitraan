export interface StcBalance {
    balance: number;
    formatted_balance?: string;
}

export interface StcMutation {
    id: number;
    transaction_id: number;
    category: string;
    type: string;
    bank_name: string | null;
    amount: number;
    service_fee: number | null;
    recorded_balance: number | null;
    ending_balance: number | null;
    note: string;
    datetime: string;
}

export interface StcTopUp {
    id: number;
    transaction_id: number | null;
    category: string;
    type: string;
    bank_name: string | null;
    amount: number;
    service_fee: number | null;
    recorded_balance: number | null;
    ending_balance: number | null;
    note: string;
    datetime: string;
    sender_name: string | null;
    status: string;
    payment_reference: string | null;
}

export interface StcTopUpOption {
    id: number;
    bank_id: number;
    bank_name: string;
    bank_code: string;
    virtual_account: string;
    is_active: boolean;
}

export interface TopUpOptionsResponse {
    banks: StcTopUpOption[];
    top_up_code: string;
}
