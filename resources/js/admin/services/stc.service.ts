import api from "@/shared/services/api";
import type { 
    StcBalance, 
    StcMutation, 
    StcTopUp, 
    StcTopUpOption,
    TopUpOptionsResponse
} from "../types/stc";

class StcService {
    /**
     * Get STC Balance
     */
    async getBalance(): Promise<StcBalance> {
        const response = await api.get("/admin/stc/balance");
        return response.data?.data || { balance: 0 };
    }

    /**
     * Get list of top up options/instructions
     */
    async getTopUpOptions(): Promise<TopUpOptionsResponse> {
        const response = await api.get("/admin/stc/top-up-options");
        return response.data?.data || { banks: [], top_up_code: "" };
    }
}

export default new StcService();
