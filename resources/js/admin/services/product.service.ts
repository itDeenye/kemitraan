import api from '@/shared/services/api';
import type { Product, ProductPayload } from '@/admin/types/product';

class ProductService {
    async getProduct(id: number): Promise<Product> {
        const { data } = await api.get(`/admin/products/${id}`);
        return data.data;
    }

    async createProduct(payload: ProductPayload): Promise<void> {
        await api.post('/admin/products', payload);
    }

    async updateProduct(id: number, payload: ProductPayload): Promise<void> {
        await api.put(`/admin/products/${id}`, payload);
    }

    async deleteProduct(id: number): Promise<void> {
        await api.delete(`/admin/products/${id}`);
    }

    async getPrices(params: any = {}) {
        return api.get('/admin/product/prices', { params });
    }

    async getPriceDetail(id: number) {
        return api.get(`/admin/product/prices/${id}`);
    }

    async updatePrice(id: number, payload: any): Promise<void> {
        await api.put(`/admin/product/prices/${id}`, payload);
    }

    async bulkUpdatePrices(payload: any[]): Promise<void> {
        await api.post(`/admin/product/prices/bulk`, payload);
    }
}

export default new ProductService();
