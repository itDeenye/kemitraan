import api from '@/shared/services/api';
import type { Role, RolePayload, RolePrivilege, RolePrivilegePayload } from '@/admin/types/role';
import type { AdminMenu } from '@/admin/types/menu';

class RoleService {
    /** Fetch list of roles */
    async getRoles(): Promise<Role[]> {
        const { data } = await api.get('/admin/system/roles');
        return data.data.results;
    }

    /** Fetch single role detail */
    async getRole(id: number): Promise<Role> {
        const { data } = await api.get(`/admin/system/roles/${id}`);
        return data.data;
    }

    /** Create new role */
    async createRole(payload: RolePayload): Promise<void> {
        await api.post('/admin/system/roles', payload);
    }

    /** Update existing role */
    async updateRole(id: number, payload: RolePayload): Promise<void> {
        await api.put(`/admin/system/roles/${id}`, payload);
    }

    /** Delete role */
    async deleteRole(id: number): Promise<void> {
        await api.delete(`/admin/system/roles/${id}`);
    }

    /** Fetch privileges for a role */
    async getPrivileges(roleId: number): Promise<RolePrivilege[]> {
        const { data } = await api.get(`/admin/system/roles/${roleId}/privileges`);
        return data.data;
    }

    /** Save privileges for a role */
    async savePrivileges(roleId: number, payload: RolePrivilegePayload): Promise<void> {
        await api.put(`/admin/system/roles/${roleId}/privileges`, payload);
    }

    /** Fetch the complete menu tree for privilege assignment. */
    async getMenus(): Promise<AdminMenu[]> {
        const { data } = await api.get('/admin/system/menus/tree');
        return data.data;
    }
}

export default new RoleService();
