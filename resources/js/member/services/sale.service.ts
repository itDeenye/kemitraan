import api from "@/shared/services/api";
import type {
    SaleOrder,
    SaleOrderDetail,
    ProductCatalog,
    SaleFormOptions,
    SaleOrderPayload,
    SalePaymentApprovePayload,
    SalePaymentRejectPayload,
    SaleShipPayload,
    ShippingRatePayload,
    SaleCustomerPayload,
    PaginatedResponse,
    SingleResponse,
} from "../types/sale";


const getOrders = async (
    params: Record<string, any> = {},
): Promise<PaginatedResponse<SaleOrder>> => {
    const response = await api.get(`/member/sales/orders`, { params });
    return response.data;
};

const getOrderDetail = async (id: number | string): Promise<SingleResponse<SaleOrderDetail>> => {
    const response = await api.get(`/member/sales/orders/${id}`);
    return response.data;
};

const getOrderSummary = async (): Promise<SingleResponse<any>> => {
    const response = await api.get(`/member/sales/orders/summary`);
    return response.data;
};

const getFormOptions = async (): Promise<SingleResponse<SaleFormOptions>> => {
    const response = await api.get(`/member/sales/options`);
    return response.data;
};

const getCustomerOptions = async (
    params: Record<string, any> = {},
): Promise<SingleResponse<any>> => {
    const response = await api.get(`/member/sales/customers/options`, { params });
    return response.data;
};

const createCustomer = async (payload: SaleCustomerPayload): Promise<SingleResponse<any>> => {
    const response = await api.post(`/member/sales/customers`, payload);
    return response.data;
};

const getCatalog = async (
    params: Record<string, any> = {},
): Promise<PaginatedResponse<ProductCatalog>> => {
    const response = await api.get(`/member/sales/catalog/products`, { params });
    return response.data;
};

const createOrder = async (payload: SaleOrderPayload): Promise<SingleResponse<SaleOrderDetail>> => {
    const response = await api.post(`/member/sales/orders`, payload);
    return response.data;
};

const cancelOrder = async (id: number | string): Promise<SingleResponse<null>> => {
    const response = await api.post(`/member/sales/orders/${id}/cancel`);
    return response.data;
};

const approvePayment = async (
    id: number | string,
    payload: SalePaymentApprovePayload,
): Promise<SingleResponse<SaleOrderDetail>> => {
    const response = await api.post(`/member/sales/orders/${id}/payment/approve`, payload);
    return response.data;
};

const rejectPayment = async (
    id: number | string,
    payload: SalePaymentRejectPayload,
): Promise<SingleResponse<SaleOrderDetail>> => {
    const response = await api.post(`/member/sales/orders/${id}/payment/reject`, payload);
    return response.data;
};

const shipOrder = async (
    id: number | string,
    payload: SaleShipPayload,
): Promise<SingleResponse<SaleOrderDetail>> => {
    const response = await api.post(`/member/sales/orders/${id}/ship`, payload);
    return response.data;
};

const getExpressRates = async (payload: ShippingRatePayload): Promise<SingleResponse<any>> => {
    const response = await api.post(`/member/shipping/express/rates`, payload);
    return response.data;
};

export default {
    getOrders,
    getOrderDetail,
    getOrderSummary,
    getFormOptions,
    getCustomerOptions,
    createCustomer,
    getCatalog,
    createOrder,
    cancelOrder,
    approvePayment,
    rejectPayment,
    shipOrder,
    getExpressRates,
};
