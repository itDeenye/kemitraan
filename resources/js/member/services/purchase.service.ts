import api from '@/shared/services/api';

class PurchaseService {
    async getOrders(params: any = {}) {
        const { data } = await api.get('/member/purchases/orders', { params });
        return data;
    }

    async getOrderSummary() {
        const { data } = await api.get('/member/purchases/orders/summary');
        return data;
    }

    async getOrderDetail(id: string | number) {
        const { data } = await api.get(`/member/purchases/orders/${id}`);
        return data;
    }

    async cancelOrder(id: string | number) {
        const { data } = await api.post(`/member/purchases/orders/${id}/cancel`);
        return data;
    }

    async payOrder(id: string | number, payload: any) {
        const { data } = await api.post(`/member/purchases/orders/${id}/payment`, payload);
        return data;
    }

    async createOrder(payload: any) {
        const { data } = await api.post('/member/purchases/orders', payload);
        return data;
    }

    async getGoodsReceipts(params: any = {}) {
        const { data } = await api.get('/member/purchases/goods-receipts', { params });
        return data;
    }

    async getGoodsReceiptDetail(id: string | number) {
        const { data } = await api.get(`/member/purchases/goods-receipts/${id}`);
        return data;
    }

    async confirmGoodsReceipt(id: string | number, payload: any) {
        const { data } = await api.post(`/member/purchases/goods-receipts/${id}/confirm`, payload);
        return data;
    }

    async getCatalogProducts(params: any = {}) {
        const { data } = await api.get('/member/purchases/catalog/products', { params });
        return data;
    }

    async getCatalogCategories() {
        const { data } = await api.get('/member/purchases/catalog/categories');
        return data;
    }

    async getCatalogProductDetail(id: string | number) {
        const { data } = await api.get(`/member/purchases/catalog/products/${id}`);
        return data;
    }

    async getCheckoutOptions(items: Array<{ product_id: number; quantity: number }> = []) {
        const { data } = await api.get('/member/purchases/options', {
            params: items.length > 0 ? { items } : undefined,
        });
        return data;
    }
}

export default new PurchaseService();
