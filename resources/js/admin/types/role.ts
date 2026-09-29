/** Role dari API /admin/system/roles */
export interface Role {
    id: number;
    title: string;
    type: 'administrator' | 'superuser';
    administrator_count: number;
    is_active: boolean;
}

/** Payload untuk create/update role */
export interface RolePayload {
    title: string;
    type: 'administrator' | 'superuser';
    is_active: boolean;
}

/** Privilege item dari API /admin/system/roles/:id/privileges */
export interface RolePrivilege {
    menu_id: number;
}

/** Payload untuk simpan privileges */
export interface RolePrivilegePayload {
    menus: RolePrivilege[];
}
