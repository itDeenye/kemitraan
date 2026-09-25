<template>
    <v-card variant="outlined" class="pa-4 h-100 rounded-lg">
        <h2 class="text-h6 font-weight-bold mb-4">Status Transaksi Terbaru</h2>

        <v-table
            density="compact"
            height="410"
            fixed-header
            class="bg-transparent"
        >
            <thead>
                <tr>
                    <th class="font-weight-bold text-no-wrap">
                        Kode Transaksi
                    </th>
                    <th class="font-weight-bold text-no-wrap">Mitra</th>
                    <th class="font-weight-bold text-no-wrap text-right">Nominal (Rp)</th>
                    <th class="font-weight-bold text-center text-no-wrap">
                        Status
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="orders.length === 0">
                    <td
                        colspan="4"
                        class="text-center text-medium-emphasis py-4"
                    >
                        Belum ada order terbaru.
                    </td>
                </tr>
                <tr v-for="o in orders" :key="o.id">
                    <td class="text-no-wrap">
                        <div class="text-caption font-weight-bold mb-1">
                            {{ o.code }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ formatShortDateTime(o.ordered_at) }}
                        </div>
                    </td>
                    <td>
                        <div class="text-body-3 text-no-wrap">
                            {{ o.buyer.name }}
                        </div>
                        <div class="mt-1">
                            <BaseBadge
                                type="level"
                                :value="o.buyer.type"
                                inline
                                size="x-small"
                            />
                        </div>
                    </td>
                    <td class="text-body-2 font-weight-bold text-no-wrap text-right">
                        {{ formatPrice(o.total) }}
                    </td>
                    <td class="text-center">
                        <div class="d-flex justify-center">
                            <BaseBadge
                                type="transaction_status"
                                :value="o.status.code"
                                :text="o.status.label"
                                inline
                            />
                        </div>
                    </td>
                </tr>
            </tbody>
        </v-table>
    </v-card>
</template>

<script setup lang="ts">
import type { RecentOrderAnalytic } from "@/admin/types/analytics";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseBadge from "@/shared/components/BaseBadge.vue";

defineProps<{
    orders: RecentOrderAnalytic[];
}>();

const { formatPrice, formatShortDateTime } = useFormatter();
</script>
