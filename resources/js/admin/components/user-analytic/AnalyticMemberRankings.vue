<template>
    <v-row>
        <v-col v-for="section in sections" :key="section.key" cols="12" md="6" lg="4">
            <v-card variant="outlined" class="pa-4 rounded-lg h-100">
                <div class="d-flex align-center ga-2 mb-3">
                    <v-icon :color="section.color">{{ section.icon }}</v-icon>
                    <h2 class="text-subtitle-1 font-weight-bold">{{ section.title }}</h2>
                </div>

                <div v-if="section.items.length === 0" class="text-center text-medium-emphasis py-6">
                    Belum ada data pada periode ini.
                </div>
                <v-list v-else density="compact" class="pa-0 bg-transparent">
                    <v-list-item
                        v-for="item in section.items"
                        :key="`${section.key}-${item.id}`"
                        class="px-0 border-bottom"
                    >
                        <template #prepend>
                            <v-avatar size="28" :color="section.color" variant="tonal">
                                <span class="text-caption font-weight-bold">{{ item.rank }}</span>
                            </v-avatar>
                        </template>
                        <v-list-item-title class="text-body-2 font-weight-medium">
                            {{ item.name }}
                        </v-list-item-title>
                        <v-list-item-subtitle>{{ item.code }}</v-list-item-subtitle>
                        <template #append>
                            <div class="text-right ml-3">
                                <div class="text-body-2 font-weight-bold">
                                    <template v-if="section.kind === 'sales'">
                                        Rp{{ formatPrice(item.turnover ?? 0) }}
                                    </template>
                                    <template v-else>
                                        {{ formatPrice(item.total_recruits ?? 0) }} mitra
                                    </template>
                                </div>
                                <div v-if="section.kind === 'sales'" class="text-caption text-medium-emphasis">
                                    {{ item.total_orders }} transaksi
                                </div>
                            </div>
                        </template>
                    </v-list-item>
                </v-list>
            </v-card>
        </v-col>
    </v-row>
</template>

<script setup lang="ts">
import { computed } from "vue";
import type {
    MemberSalesRankingsAnalytic,
    RecruitmentRankingsAnalytic,
} from "@/admin/types/analytics";
import { useFormatter } from "@/shared/composables/useFormatter";

const props = defineProps<{
    sales: MemberSalesRankingsAnalytic;
    recruitment: RecruitmentRankingsAnalytic;
}>();

const { formatPrice } = useFormatter();

interface RankingItem {
    rank: number;
    id: number;
    code: string;
    name: string;
    total_orders?: number;
    turnover?: number;
    total_recruits?: number;
}

interface RankingSection {
    key: string;
    title: string;
    kind: "sales" | "recruitment";
    icon: string;
    color: string;
    items: RankingItem[];
}

const sections = computed<RankingSection[]>(() => [
    { key: "sales-distributor", title: "Penjualan Distributor Terbanyak", kind: "sales", icon: "mdi-store", color: "primary", items: props.sales.distributors },
    { key: "sales-agent", title: "Penjualan Agen Utama Terbanyak", kind: "sales", icon: "mdi-account-star", color: "info", items: props.sales.agents },
    { key: "sales-reseller", title: "Penjualan Reseller Terbanyak", kind: "sales", icon: "mdi-account-group", color: "warning", items: props.sales.resellers },
    { key: "recruit-distributor", title: "Top Recruitment Distributor", kind: "recruitment", icon: "mdi-account-multiple-plus", color: "success", items: props.recruitment.distributors },
    { key: "recruit-agent", title: "Top Recruitment Agen Utama", kind: "recruitment", icon: "mdi-account-plus", color: "secondary", items: props.recruitment.agents },
]);
</script>

<style scoped>
.border-bottom:not(:last-child) {
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}
</style>
