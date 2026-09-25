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

export interface Member {
    id: number;
    code: string;
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
