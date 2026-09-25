import api from '@/shared/services/api';
import type {
    Member,
    GenealogyNode,
    MemberDeactivationOptions,
} from '@/admin/types/member';

class MemberService {
    async getMember(id: number): Promise<Member> {
        const { data } = await api.get(`/admin/partnership/members/${id}`);
        return data.data;
    }

    async updateMember(id: number, payload: any): Promise<Member> {
        const { data } = await api.put(`/admin/partnership/members/${id}`, payload);
        return data.data;
    }

    async getDeactivationOptions(id: number): Promise<MemberDeactivationOptions> {
        const { data } = await api.get(
            `/admin/partnership/members/${id}/deactivation-options`,
            { params: { limit: 50 } },
        );
        return data.data;
    }

    async deactivateMember(
        id: number,
        payload: {
            replacement_sponsor_id: number | null;
            cancel_active_transactions: boolean;
            note?: string;
        },
    ) {
        const { data } = await api.post(
            `/admin/partnership/members/${id}/deactivate`,
            payload,
        );
        return data;
    }

    async resetPassword(id: number) {
        const { data } = await api.post(
            `/admin/partnership/members/${id}/reset-password`,
        );
        return data;
    }

    async getGenealogy(memberId?: number, depth: number = 3): Promise<GenealogyNode[]> {
        const params = new URLSearchParams({ depth: depth.toString() });
        if (memberId) {
            params.append('member_id', memberId.toString());
        }
        
        const { data } = await api.get(`/admin/partnership/genealogy?${params.toString()}`);
        return data.data.results;
    }
}

export default new MemberService();
