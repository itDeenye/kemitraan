export interface MemberProfile {
    account_id: number;
    username: string;
    last_login_at: string | null;
    role: {
        id: number;
        name: string;
    };
    member: {
        id: number;
        code: string;
        name: string;
        email: string;
        mobile_phone: string;
        level: {
            id: number;
            code: string;
            name: string;
            min_order: number;
        };
        image: string | null;
        is_stockist: boolean;
        gender: string;
        birth_date: string | null;
        identity: {
            type: string;
            number: string;
            image: string | null;
        };
        nib: string | null;
        social_media: {
            instagram: string | null;
            facebook: string | null;
            tiktok: string | null;
        };
        default_address: MemberAddress | null;
        default_bank_account: MemberBank | null;
    };
    reward_summary?: {
        monthly: {
            year: number;
            month: number;
            total_reward: number;
            total_paid: number;
            total_available: number;
        };
        annual: {
            year: number;
            total_points: number;
        };
    };
}

export interface ProfilePayload {
    name: string;
    email: string;
    phone: string;
    gender?: string;
    birth_date?: string;
    identity_type?: string;
    identity_no?: string;
    nib?: string;
    instagram?: string;
    facebook?: string;
    tiktok?: string;
}

export interface PasswordPayload {
    current_password: string;
    password: string;
    password_confirmation: string;
}

export interface MemberBank {
    id: number;
    bank_id: number;
    bank_code: string | null;
    bank_name: string | null;
    account_name: string;
    account_number: string;
    city: string | null;
    branch: string | null;
    is_active: boolean;
    is_default: boolean;
}

export interface MemberBankPayload {
    bank_id: number | null;
    account_name: string;
    account_number: string;
    city?: string;
    branch?: string;
    is_active?: boolean | number;
    is_default?: boolean | number;
}

export interface MemberAddress {
    id: number;
    label: string;
    recipient: string;
    phone: string;
    full_address: string;
    region: {
        province_id: string;
        province_name: string | null;
        city_id: string;
        city_name: string | null;
        city_type: string | null;
        district_id: string;
        district_name: string | null;
        subdistrict_id: number;
        subdistrict_name: string | null;
        postal_code: string | null;
        country_id: number;
        country_name: string | null;
    };
    is_default: boolean;
}

export interface MemberAddressPayload {
    label: string;
    recipient: string;
    phone: string;
    full_address: string;
    province_id?: number | string | null;
    city_id?: number | string | null;
    district_id?: number | string | null;
    subdistrict_id?: number | string | null;
    country_id?: number | string | null;
    is_default: boolean | number;
}

export interface ReferenceBank {
    id: number;
    code: string;
    name: string;
    logo: string;
}
