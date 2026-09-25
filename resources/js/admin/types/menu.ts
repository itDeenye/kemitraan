export interface ApiMenuItem {
    id: number;
    title: string;
    description: string;
    route: string;
    icon: string;
    css_class: string;
    sort_order: number;
    children: ApiMenuItem[];
}

export interface AdminMenu {
    id: number;
    parent_id: number;
    title: string;
    description: string;
    link: string;
    icon: string;
    class: string;
    order: number;
    is_active: boolean;
    children: AdminMenu[];
}

export interface AdminMenuPayload {
    parent_id: number | null;
    title: string;
    description: string;
    link: string;
    icon: string;
    css_class: string;
    order: number;
    is_active: boolean;
}

export interface SidebarMenuItem {
    id: number;
    title: string;
    icon: string;
    to?: string;
    href?: string;
    target?: string;
    badge?: number;
    children?: SidebarMenuItem[];
}
