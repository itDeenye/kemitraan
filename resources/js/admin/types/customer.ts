export interface CustomerLevel {
    id: number;
    code: string;
    name: string;
}

export interface CustomerMember {
    id: number;
    code: string;
    name: string;
    level: CustomerLevel;
}

export interface CustomerRegion {
    province_id: number | null;
    province_name: string | null;
    city_id: number | null;
    city_name: string | null;
    district_id: number | null;
    district_name: string | null;
    subdistrict_id: number | null;
    subdistrict_name: string | null;
}

export interface Customer {
    id: number;
    name: string;
    whatsapp: string | null;
    phone: string | null;
    gender: string | null;
    birth_date: string | null;
    address: string | null;
    region: CustomerRegion;
    member: CustomerMember;
    created_at: string;
}
