import api from '@/shared/services/api';

class ReferenceService {
    /** Fetch reference banks list */
    async getBanks() {
        const { data } = await api.get('/references/banks');
        return data;
    }
}

export default new ReferenceService();
