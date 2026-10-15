import api from '@/shared/services/api';
import type { Category, CategoryPayload } from '@/admin/types/category';

class CategoryService {
    async getCategories(params: any = {}): Promise<any> {
        const { data } = await api.get('/admin/product/categories', { params });
        return data.data?.results || data.data || [];
    }

    async getCategory(id: number): Promise<Category> {
        const { data } = await api.get(`/admin/product/categories/${id}`);
        return data.data;
    }

    async createCategory(payload: CategoryPayload): Promise<void> {
        await api.post('/admin/product/categories', payload);
    }

    async updateCategory(id: number, payload: CategoryPayload): Promise<void> {
        await api.put(`/admin/product/categories/${id}`, payload);
    }

    async moveCategoryUp(id: number): Promise<void> {
        await api.patch(`/admin/product/categories/${id}/move-up`);
    }

    async moveCategoryDown(id: number): Promise<void> {
        await api.patch(`/admin/product/categories/${id}/move-down`);
    }

    async deleteCategory(id: number): Promise<void> {
        await api.delete(`/admin/product/categories/${id}`);
    }
}

export default new CategoryService();
