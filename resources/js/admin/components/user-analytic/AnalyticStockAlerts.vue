<template>
    <v-card variant="outlined" class="pa-4 h-100 rounded-lg">
        <h2 class="text-h6 font-weight-bold mb-4">Peringatan Stok Gudang</h2>

        <v-divider></v-divider>

        <v-table density="comfortable" height="400" fixed-header class="bg-transparent">
            <thead>
                <tr>
                    <th class="font-weight-bold text-left">Produk</th>
                    <th class="font-weight-bold text-center">Stok Saat Ini</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="alerts.length === 0">
                    <td
                        colspan="3"
                        class="text-center text-medium-emphasis py-4"
                    >
                        Semua stok produk dalam kondisi aman.
                    </td>
                </tr>
                <tr v-for="alert in alerts" :key="alert.id">
                    <td>
                        <div class="text-body-2">
                            {{ alert.name }}
                        </div>
                        <div class="text-caption font-weight-bold">
                            {{ alert.code }}
                        </div>
                    </td>
                    <td class="text-center">
                        <span
                            class="text-body-2 font-weight-bold"
                            :class="
                                alert.balance <= 0
                                    ? 'text-error'
                                    : 'text-warning'
                            "
                        >
                            {{ alert.balance }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </v-table>
    </v-card>
</template>

<script setup lang="ts">
import type { StockAlertAnalytic } from "@/admin/types/analytics";
import BaseBadge from "@/shared/components/BaseBadge.vue";

defineProps<{
    alerts: StockAlertAnalytic[];
}>();
</script>
