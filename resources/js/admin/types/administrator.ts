export interface AdministratorRole {
    id: number;
    title: string;
    type: string;
}

export interface Administrator {
    id: number;
    username: string;
    name: string;
    email: string;
    image_url?: string;
    role: AdministratorRole;
    is_active: boolean;
    last_login: string | null;
    locked_until: string | null;
}

export interface AdministratorPayload {
    role_id: number | null;
    username: string;
    name: string;
    email: string;
    image_url?: string;
    is_active: boolean;
    password?: string;
    password_confirmation?: string;
}

export interface AdministratorPasswordPayload {
    password?: string;
    password_confirmation?: string;
}
