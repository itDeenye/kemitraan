export interface ShippingRatePayload {
    address_id: number;
    couriers: string[];
    items: {
        product_id: number;
        quantity: number;
    }[];
}

export interface ShippingRateLocation {
    province_id: number | null;
    province_name: string | null;
    city_id: number | null;
    city_name: string | null;
    city_type: string | null;
    district_id: number;
    district_name: string | null;
    subdistrict_id: number;
    subdistrict_name: string | null;
    postal_code: number | string | null;
    display_name: string | null;
    source_type?: string;
    source_id?: number;
    name?: string;
    address?: string;
}

export interface ShippingRateResult {
    courier_code: string;
    courier_name: string;
    service_type: string;
    cost: number;
    etd: string;
    drop_off_available: boolean;
    force_insurance: boolean;
    insurance: number;
    logo_url: string;
}
