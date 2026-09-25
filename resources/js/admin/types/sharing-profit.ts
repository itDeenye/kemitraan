export interface SharingProfitUpline {
    id: number;
    name: string;
    code: string;
}

export interface SharingProfitMitra {
    upline: SharingProfitUpline;
    total_trx_price: number;
    total_amount: number;
    transaction_count: number;
    submitted_count: number;
    approved_count: number;
    status: 'submitted' | 'approved';
    can_approve: boolean;
    can_transfer: boolean;
}

export interface SharingProfitDetail {
    id: number;
    submitted_datetime: string | null;
    approved_datetime: string | null;
    paid_datetime: string | null;
    trx_code: string;
    buyer_name: string;
    trx_price: number;
    amount: number;
    receipt_url: string | null;
    note: string | null;
    status: 'pending' | 'submitted' | 'approved' | 'paid' | 'rejected' | string;
}

export interface SharingProfitHistory {
    id: number;
    paid_datetime: string | null;
    trx_code: string;
    mitra_name: string;
    trx_price: number;
    amount: number;
    note: string;
    receipt_url: string;
    status: string;
}
