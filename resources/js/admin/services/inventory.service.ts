import api from '@/shared/services/api';
import type { WarehouseStock, StockAdjustment } from '../types/inventory';

export default {
    getStocks(params: any = {}) {
        return api.get('/admin/inventory/stocks', { params });
    },
    getStockDetail(id: number) {
        return api.get(`/admin/inventory/stocks/${id}`);
    },
    
    // Adjustments
    getAdjustments(params: any = {}) {
        return api.get('/admin/inventory/adjustments', { params });
    },
    getAdjustmentDetail(id: number) {
        return api.get(`/admin/inventory/adjustments/${id}`);
    },
    createAdjustment(data: StockAdjustment) {
        return api.post('/admin/inventory/adjustments', data);
    },

    // Shipping
    getShipments(params: any = {}) {
        return api.get('/admin/inventory/shipments', { params });
    },
    getShipmentDetail(id: number) {
        return api.get(`/admin/inventory/shipments/${id}`);
    },
    trackExpressShipment(id: number) {
        return api.post(`/admin/inventory/shipments/${id}/tracking`);
    },
    getReshipCouriers(id: number, data: { couriers: string[] }) {
        return api.post(`/admin/inventory/shipments/${id}/couriers`, data);
    },
    shipOrder(id: number, data: any = {}) {
        return api.post(`/admin/inventory/shipments/${id}/ship`, data);
    },
    getExpressSchedules() {
        return api.get('/admin/inventory/shipments/express/schedules');
    },
    simulateStcFinishedPackage(data: {
        order_id: string;
        awb: string;
        date: string;
        finished_at: string;
    }) {
        return api.post('/callbacks/stc/shipping', {
            method: 'finished_packages',
            data: [data],
        });
    }
}
