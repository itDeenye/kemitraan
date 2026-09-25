<template>
    <div class="analytic-dashboard">
        <div class="d-flex align-center justify-space-between mb-4">
            <div>
                <h1 class="text-h5 font-weight-bold">Analitik User</h1>
                <p class="text-body-2 text-medium-emphasis mt-1">
                    Halaman untuk memantau aktivitas operasional, performa
                    penjualan, dan kemitraan.
                </p>
            </div>
        </div>

        <!-- Filter Section -->
        <AnalyticFilter @filter="handleFilter" />

        <div
            v-if="isLoading"
            class="d-flex flex-column align-center justify-center py-16"
        >
            <v-progress-circular
                indeterminate
                color="primary"
                size="48"
                width="4"
            ></v-progress-circular>
            <div class="mt-4 text-medium-emphasis text-body-2">
                Menarik data analitik terbaru...
            </div>
        </div>

        <template v-else-if="analyticsData">
            <!-- KPI Cards -->
            <v-row class="mb-4">
                <v-col
                    v-for="card in kpiCards"
                    :key="card.title"
                    cols="12"
                    sm="6"
                    md="4"
                    lg="2"
                >
                    <AnalyticKpiCard
                        :title="card.title"
                        :value="card.value"
                        :color="card.color"
                    />
                </v-col>
            </v-row>

            <v-row class="mb-4">
                <!-- Trend Chart -->
                <v-col cols="12" md="8">
                    <AnalyticOrderTrend :trends="analyticsData.sales_trend" />
                </v-col>

                <!-- Order Statuses -->
                <v-col cols="12" md="4">
                    <AnalyticOrderStatus
                        :statuses="analyticsData.order_statuses"
                    />
                </v-col>
            </v-row>

            <v-row class="mb-4">
                <!-- Recent Orders -->
                <v-col cols="12" md="7">
                    <AnalyticRecentOrders
                        :orders="analyticsData.recent_orders"
                    />
                </v-col>

                <!-- Members Overview -->
                <v-col cols="12" md="5">
                    <AnalyticMembersOverview
                        :levels="analyticsData.level_distribution"
                        :members="analyticsData.highlighted_members"
                    />
                </v-col>
            </v-row>

            <v-row class="mb-8">
                <!-- Stock Alerts -->
                <v-col cols="12">
                    <AnalyticStockAlerts :alerts="analyticsData.stock_alerts" />
                </v-col>
            </v-row>
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from "vue";
import { useAnalytics } from "../../composables/useAnalytics";
import { useFormatter } from "@/shared/composables/useFormatter";
import AnalyticFilter from "../../components/user-analytic/AnalyticFilter.vue";
import AnalyticKpiCard from "../../components/user-analytic/AnalyticKpiCard.vue";
import AnalyticOrderTrend from "../../components/user-analytic/AnalyticOrderTrend.vue";
import AnalyticOrderStatus from "../../components/user-analytic/AnalyticOrderStatus.vue";
import AnalyticRecentOrders from "../../components/user-analytic/AnalyticRecentOrders.vue";
import AnalyticMembersOverview from "../../components/user-analytic/AnalyticMembersOverview.vue";
import AnalyticStockAlerts from "../../components/user-analytic/AnalyticStockAlerts.vue";

const { isLoading, analyticsData, fetchAnalytics } = useAnalytics();
const { formatPrice } = useFormatter();

onMounted(() => {
    fetchAnalytics();
});

const handleFilter = (filterData: { date_from?: string; date_to?: string }) => {
    fetchAnalytics(filterData);
};

const kpiCards = computed(() => {
    if (!analyticsData.value) return [];
    const summary = analyticsData.value.summary;
    return [
        {
            title: "Total Mitra",
            value: formatPrice(summary.total_partners),
            color: "primary",
        },
        {
            title: "Total Transaksi",
            value: formatPrice(summary.total_orders),
            color: "info",
        },
        {
            title: "Transaksi Aktif",
            value: formatPrice(summary.active_orders),
            color: "warning",
        },
        {
            title: "Produk Terjual",
            value: formatPrice(summary.products_sold),
            color: "success",
        },
        {
            title: "Stok Gudang",
            value: formatPrice(summary.warehouse_stock),
            color: "grey-darken-1",
        },
        {
            title: "Omzet",
            value: `Rp${formatPrice(summary.turnover)}`,
            color: "success",
        },
    ];
});
</script>

<style scoped>
.analytic-dashboard {
    padding-bottom: 2rem;
}
</style>
