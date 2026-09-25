import api from "@/shared/services/api";

class PartnershipSalesService {
    async getDetail(id: number | string) {
        const { data } = await api.get(
            `/admin/reports/partnership-sales/${id}`,
        );
        return data.data;
    }
}

export default new PartnershipSalesService();
