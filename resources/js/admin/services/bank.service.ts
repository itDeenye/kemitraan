import api from '@/shared/services/api';
import type { CompanyBank, CompanyBankPayload } from '@/admin/types/bank';

class BankService {
    async getBank(id: number): Promise<CompanyBank> {
        const { data } = await api.get(`/admin/company/banks/${id}`);
        return data.data;
    }

    async createBank(payload: CompanyBankPayload): Promise<void> {
        await api.post('/admin/company/banks', payload);
    }

    async updateBank(id: number, payload: CompanyBankPayload): Promise<void> {
        await api.put(`/admin/company/banks/${id}`, payload);
    }

    async deleteBank(id: number): Promise<void> {
        await api.delete(`/admin/company/banks/${id}`);
    }
}

export default new BankService();
