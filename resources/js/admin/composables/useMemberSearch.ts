import { ref } from "vue";
import api from "@/shared/services/api";

export interface UseMemberSearchOptions {
    limit?: number;
    debounceMs?: number;
    minQueryLength?: number;
    onDemand?: boolean;
}

export function useMemberSearch(options: UseMemberSearchOptions = {}) {
    const limit = options.limit ?? 20;
    const debounceMs = options.debounceMs ?? 350;
    const minQueryLength = options.minQueryLength ?? 3;
    const onDemand = options.onDemand ?? true;

    const members = ref<any[]>([]);
    const loadingMembers = ref(false);
    const searchQuery = ref("");
    let searchTimeout: ReturnType<typeof setTimeout> | null = null;
    let latestSearchId = 0;

    /**
     * Custom filter agar Vuetify tidak menyembunyikan hasil pencarian server
     * ketika query cocok dengan code, nama, email, atau no telepon mitra.
     */
    const customFilter = (_value: string, query: string, item: any) => {
        if (!query) return true;
        const q = query.toLowerCase().trim();
        if (!q) return true;

        const raw = item?.raw || item;
        const name = (raw.name || "").toLowerCase();
        const code = (raw.code || "").toLowerCase();
        const email = (raw.email || "").toLowerCase();
        const phone = (raw.mobile_phone || "").toLowerCase();

        const strippedCode = code.replace(/[^a-zA-Z0-9]/g, "");
        const strippedQ = q.replace(/[^a-zA-Z0-9]/g, "");

        return (
            name.includes(q) ||
            code.includes(q) ||
            (strippedQ.length > 0 && strippedCode.includes(strippedQ)) ||
            email.includes(q) ||
            phone.includes(q)
        );
    };

    /**
     * Memanggil API untuk mengambil data mitra
     */
    const fetchMembers = async (query = "", currentMember?: any) => {
        const requestId = ++latestSearchId;
        loadingMembers.value = true;
        try {
            const { data } = await api.get("/admin/partnership/members", {
                params: { search: query, limit },
            });

            // Abaikan jika ada request baru yang berjalan
            if (requestId !== latestSearchId) return;

            const results =
                data.data?.results ||
                (Array.isArray(data.data) ? data.data : []);

            // Pastikan mitra yang sedang terpilih tetap ada di options list
            if (
                currentMember &&
                !results.some((m: any) => m.id === currentMember.id)
            ) {
                members.value = [currentMember, ...results];
            } else {
                members.value = results;
            }
        } catch (e) {
            console.error("Failed to fetch members", e);
        } finally {
            if (requestId === latestSearchId) {
                loadingMembers.value = false;
            }
        }
    };

    /**
     * Mengosongkan daftar mitra (misal saat input di-clear)
     */
    const clearMembers = (keepMember?: any) => {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        loadingMembers.value = false;
        searchQuery.value = "";
        members.value = keepMember ? [keepMember] : [];
    };

    /**
     * Debounce pencarian mitra dengan visual loading state seketika
     */
    const searchMembers = (query: string | null, currentMember?: any) => {
        const q = query?.trim() ?? "";
        searchQuery.value = q;

        // Jangan re-fetch jika query hanya mencerminkan mitra yang baru saja dipilih
        if (
            currentMember &&
            (currentMember.name === query ||
                currentMember.code === query ||
                query === `${currentMember.name} (${currentMember.code})`)
        ) {
            return;
        }

        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        if (q.length === 0) {
            loadingMembers.value = false;
            if (onDemand) {
                members.value = currentMember ? [currentMember] : [];
            } else {
                fetchMembers("", currentMember);
            }
            return;
        }

        if (q.length < minQueryLength) {
            loadingMembers.value = false;
            return;
        }

        // Tampilkan indikator loading seketika saat user sedang mencari
        loadingMembers.value = true;
        searchTimeout = setTimeout(() => {
            fetchMembers(q, currentMember);
        }, debounceMs);
    };

    return {
        members,
        loadingMembers,
        searchQuery,
        fetchMembers,
        searchMembers,
        clearMembers,
        customFilter,
    };
}
