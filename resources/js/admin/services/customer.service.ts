import api from "@/shared/services/api";
import type { Customer } from "@/admin/types/customer";

class CustomerService {
    async getCustomer(id: number | string): Promise<Customer> {
        const { data } = await api.get(`/admin/customers/${id}`);
        return data.data;
    }
}

export default new CustomerService();
