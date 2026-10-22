export interface MemberStatus {
    code: number;
    label: string;
}

export interface MemberAddress {
    id: number;
    label: string | null;
    recipient: string;
    phone: string;
    full_address: string;
    region: any;
    is_default: boolean;
}

export interface MemberBank {
    id: number;
    bank_id: number;
    bank_code?: string | null;
    bank_name?: string | null;
    account_name: string;
    account_number: string;
    city: string | null;
    branch: string | null;
    is_active: boolean;
    is_default: boolean;
}

export interface MemberStats {
    omzet: number;
    point: number;
}

export interface MemberNetwork {
    total_direct_downlines: number;
    [key: string]: any;
}

export interface MemberIdentity {
    type: string | null;
    number: string | null;
    image: string | null;
    image_filename: string | null;
}

export interface Member {
    id: number;
    code: string;
    customer_number?: string | null;
    can_sync_supply_chain?: boolean;
    can_change_level?: boolean;
    name: string;
    email: string;
    mobile_phone: string;
    gender: string;
    birth_date: string;
    level:
        | {
              id?: number;
              code?: string;
              name: string;
          }
        | any;
    status: MemberStatus;
    joined_at: string;
    image: string | null;
    image_filename?: string | null;
    identity?: MemberIdentity | null;
    payment_proof_image_url?: string | null;
    can_update_payment_proof?: boolean;
    nib?: string | null;
    npwp?: string | null;
    social_media?: {
        instagram: string | null;
        facebook: string | null;
        tiktok: string | null;
    };
    username: string | null;
    parent:
        | {
              id?: number;
              code?: string;
              name: string;
          }
        | any;
    addresses?: MemberAddress[];
    bank_accounts?: MemberBank[];
    stats?: MemberStats;
    network?: MemberNetwork;
}

export interface GenealogyNode {
    id: number;
    code: string;
    name: string;
    image: string;
    image_url: string;
    level: {
        id: number;
        code: string;
        name: string;
    };
    status: number;
    parent_id: number | null;
    total_direct_downlines: number;
    downlines: GenealogyNode[];
}

export interface MemberOption {
    id: number;
    code: string;
    name: string;
    label: string;
    level: {
        id: number;
        code: string;
        name: string;
    };
}

export interface MemberDeactivationOptions {
    member: MemberOption;
    summary: {
        direct_downline_count: number;
        outstanding_reward_count: number;
        active_transaction_count: number;
        cancellable_transaction_count: number;
        blocking_transaction_count: number;
        active_return_count: number;
        has_pending_network_change: boolean;
    };
    requires_replacement_sponsor: boolean;
    requires_transaction_cancellation: boolean;
    can_deactivate: boolean;
    results: MemberOption[];
}
