import api from '@/shared/services/api';
import type { AdminMenu, AdminMenuPayload } from '@/admin/types/menu';

class MenuService {
    /** Fetch menu tree for management page */
    async getMenuTree(): Promise<AdminMenu[]> {
        const { data } = await api.get('/admin/system/menus/tree');
        return data.data;
    }

    /** Fetch single menu detail */
    async getMenuDetail(id: number): Promise<AdminMenu> {
        const { data } = await api.get(`/admin/system/menus/${id}`);
        return data.data;
    }

    /** Create new menu */
    async createMenu(payload: AdminMenuPayload): Promise<void> {
        await api.post('/admin/system/menus', payload);
    }

    /** Update existing menu */
    async updateMenu(id: number, payload: AdminMenuPayload): Promise<void> {
        await api.put(`/admin/system/menus/${id}`, payload);
    }

    /** Delete menu */
    async deleteMenu(id: number): Promise<void> {
        await api.delete(`/admin/system/menus/${id}`);
    }
}

export default new MenuService();
