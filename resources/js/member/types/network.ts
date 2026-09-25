export interface GenealogyParams {
    parent_id?: number;
    page?: number;
    limit?: number;
    search?: string;
}

export interface RegistrationListParams {
    page?: number;
    limit?: number;
    search?: string;
    pagination?: boolean;
}

export interface MemberLevel {
    id: number;
    code: string;
    name: string;
}

export interface GenealogyMember {
    id: number;
    code: string;
    name: string;
    image: string;
    image_url: string;
    level: MemberLevel;
    joined_at: string;
    total_direct_downlines: number;
    has_downlines: boolean;
}

export interface GenealogyContextMember {
    id: number;
    code: string;
    name: string;
    level: MemberLevel;
}

export interface GenealogyContext {
    root_member: GenealogyContextMember;
    viewing_member: GenealogyContextMember;
    breadcrumbs: GenealogyContextMember[];
    can_register: boolean;
}

export interface DataTablePagination {
    total_data: number;
    total_page: number;
    total_display: number;
    first_page: boolean;
    last_page: boolean;
    prev: number;
    current: number;
    next: number;
    detail: any[];
    start: number;
    end: number;
}

export interface GenealogyResponse {
    context: GenealogyContext;
    results: GenealogyMember[];
    pagination: DataTablePagination;
}

export interface RegistrationSponsor {
    id: number;
    code: string;
    name: string;
    level_code: string;
}

export interface RegistrationOptions {
    target_level: MemberLevel;
    sponsor: RegistrationSponsor;
    genders: string[];
    identity_types: string[];
}

export interface RegistrationStatus {
    code: string;
    label: string;
}

export interface RegistrationListItem {
    id: number;
    level: MemberLevel;
    sponsor: RegistrationSponsor | null;
    applicant: {
        name: string;
        email: string | null;
        mobile_phone: string;
        username: string;
    };
    status: RegistrationStatus;
    processed_by: { id: number; name: string } | null;
    submitted_at: string;
    processed_at: string | null;
}

export interface RegistrationDetail extends RegistrationListItem {
    upline: RegistrationSponsor | null;
    applicant: {
        name: string;
        email: string | null;
        mobile_phone: string;
        username: string;
        gender: string;
        birth_date: string;
    };
    identity: {
        type: string;
        number: string;
        nib: string | null;
    };
    address: {
        address: string;
        province_id: number;
        province_name: string;
        city_id: number;
        city_name: string;
        district_id: number;
        district_name: string;
        subdistrict_id: number;
        subdistrict_name: string;
        postal_code: number;
        country_id: number;
        country_name: string;
    };
    bank: {
        id: number;
        code: string;
        name: string;
        account_name: string;
        account_number: string;
    } | null;
    note: string | null;
}

export interface RegistrationListResponse {
    results: RegistrationListItem[];
    pagination: DataTablePagination;
}

export interface RegistrationPayload {
    name: string;
    email: string | null;
    mobile_phone: string;
    gender: string;
    birth_date: string;
    address: string;
    province_id: string;
    city_id: string;
    district_id: string;
    subdistrict_id: number | null;
    country_id: number;
    bank_id: number | null;
    bank_account_name: string | null;
    bank_account_number: string | null;
    identity_type: string;
    identity_no: string;
    nib: string | null;
}
