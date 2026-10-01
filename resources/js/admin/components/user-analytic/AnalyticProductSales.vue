<template>
    <v-card variant="outlined" class="pa-4 rounded-lg h-100">
        <div class="d-flex align-center justify-space-between mb-4">
            <div>
                <h2 class="text-h6 font-weight-bold">Performa Penjualan Produk</h2>
                <div class="text-caption text-medium-emphasis">
                    Diurutkan dari produk paling laku hingga slow moving.
                </div>
            </div>
            <v-icon color="primary">mdi-chart-bar-stacked</v-icon>
        </div>

        <div v-if="products.length === 0" class="text-center text-medium-emphasis py-8">
            Belum ada produk aktif.
        </div>

        <v-table v-else density="compact" class="ranking-table">
            <thead>
                <tr>
                    <th class="text-center">#</th>
                    <th>Produk</th>
                    <th class="text-right">Terjual</th>
                    <th class="text-right">Omzet</th>
                    <th class="text-center">Pergerakan</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="product in products" :key="product.id">
                    <td class="text-center font-weight-bold">{{ product.rank }}</td>
                    <td>
                        <div class="font-weight-medium">{{ product.name }}</div>
                        <div class="text-caption text-medium-emphasis">{{ product.code }}</div>
                    </td>
                    <td class="text-right">{{ formatPrice(product.total_quantity) }}</td>
                    <td class="text-right">Rp{{ formatPrice(product.turnover) }}</td>
                    <td class="text-center">
                        <v-chip
                            size="x-small"
                            :color="product.movement === 'slow_moving' ? 'warning' : 'success'"
                            variant="tonal"
                        >
                            {{ product.movement === "slow_moving" ? "Slow Moving" : "Terjual" }}
                        </v-chip>
                    </td>
                </tr>
            </tbody>
        </v-table>
    </v-card>
</template>

<script setup lang="ts">
import type { ProductSalesAnalytic } from "@/admin/types/analytics";
import { useFormatter } from "@/shared/composables/useFormatter";

defineProps<{ products: ProductSalesAnalytic[] }>();

const { formatPrice } = useFormatter();
</script>

<style scoped>
.ranking-table {
    max-height: 420px;
    overflow-y: auto;
}
</style>
