export interface ConfigEntry {
    label: string;
    key: string;
    value: string | number | boolean;
}

export interface SystemConfig {
    id: number;
    label: string;
    key: string;
    scope: string;
    value: Record<string, any> | string | number | boolean | null;
    entries: ConfigEntry[];
    type: 'string' | 'integer' | 'json' | 'boolean';
    created_at: string;
    updated_at: string;
}

export interface SystemConfigPayload {
    type: string;
    value: Record<string, any> | string | number | boolean;
}
