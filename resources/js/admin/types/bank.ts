export interface ReferenceBank {
    id: number;
    code: string;
    name: string;
    logo?: string;
}

export interface CompanyBank {
    id: number;
    type: string;
    bank: ReferenceBank;
    account_name: string;
    account_number: string;
    is_active: boolean;
}

export interface CompanyBankPayload {
    bank_id: number | null;
    type: string | null;
    account_name: string;
    account_number: string;
    is_active: boolean;
}
