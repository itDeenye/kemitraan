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
    sort?: string;
    "filter[distributor_approval_status]"?:
        | "pending"
        | "approved"
        | "rejected";
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
    level_name?: string;
}

export interface RegistrationOptions {
    target_level: MemberLevel;
    levels: MemberLevel[];
    sponsor: RegistrationSponsor;
    agents: RegistrationSponsor[];
    genders: string[];
    identity_types: string[];
}

export interface RegistrationStatus {
    code: string;
    label: string;
}

export interface RegistrationDistributorApproval {
    status: RegistrationStatus;
    distributor: {
        id: number;
        code: string;
        name: string;
    } | null;
    note: string | null;
    processed_at: string | null;
}

export interface RegistrationIdentity {
    type: string;
    number: string;
    image_url: string | null;
    nib: string | null;
    npwp: string | null;
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
    image_url: string | null;
    payment_proof_image_url: string | null;
    identity: RegistrationIdentity;
    status: RegistrationStatus;
    next_approval: "distributor" | "admin" | null;
    distributor_approval: RegistrationDistributorApproval;
    processed_by: { id: number; name: string } | null;
    submitted_at: string;
    processed_at: string | null;
}

export interface RegistrationBankAccount {
    bank_id: number;
    account_name: string;
    account_number: string;
    city: string | null;
    branch: string | null;
    is_active: boolean;
    is_default: boolean;
}

export interface RegistrationDetail extends RegistrationListItem {
    upline: RegistrationSponsor | null;
    submitted_by: RegistrationSponsor | null;
    network_distributor: RegistrationSponsor | null;
    applicant: {
        name: string;
        email: string | null;
        mobile_phone: string;
        username: string;
        gender: string;
        birth_date: string;
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
        city?: string | null;
        branch?: string | null;
    } | null;
    bank_accounts: RegistrationBankAccount[];
    nib: string | null;
    npwp: string | null;
    note: string | null;
}

export interface RegistrationListResponse {
    results: RegistrationListItem[];
    pagination: DataTablePagination;
}

export interface RegistrationPayload {
    level_id: number;
    upline_member_id: number | null;
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
    identity_image_url: string;
    image_url: string;
    payment_proof_image_url: string;
    nib: string | null;
}

export interface RegistrationApprovalPayload {
    note: string | null;
}
