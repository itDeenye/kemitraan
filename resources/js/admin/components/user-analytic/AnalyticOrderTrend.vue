<template>
    <v-card variant="outlined" class="pa-4 h-100 rounded-lg">
        <h2 class="text-h6 font-weight-bold mb-4">Trend Penjualan & Omzet</h2>

        <div
            v-if="!trends.length"
            class="d-flex align-center justify-center text-medium-emphasis py-10"
        >
            Tidak ada data tren penjualan.
        </div>

        <div v-else class="chart-container">
            <Bar :data="chartData" :options="chartOptions" />
        </div>
    </v-card>
</template>

<script setup lang="ts">
import { computed } from "vue";
import type { SalesTrendAnalytic } from "@/admin/types/analytics";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useTheme } from "vuetify";
import { Bar } from "vue-chartjs";
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
} from "chart.js";
import type { ChartOptions } from "chart.js";

ChartJS.register(
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
);

const props = defineProps<{
    trends: SalesTrendAnalytic[];
}>();

const { formatPrice } = useFormatter();
const theme = useTheme();

const formatDateShort = (dateStr: string) => {
    const d = new Date(dateStr);
    const months = [
        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "Mei",
        "Jun",
        "Jul",
        "Ags",
        "Sep",
        "Okt",
        "Nov",
        "Des",
    ];
    return `${d.getDate().toString().padStart(2, "0")} ${months[d.getMonth()]}`;
};

const chartData = computed(() => {
    return {
        labels: props.trends.map((t) => formatDateShort(t.date)),
        datasets: [
            {
                label: "Omzet",
                backgroundColor:
                    theme.current.value.colors.primary || "#1976D2",
                data: props.trends.map((t) => t.turnover),
                borderRadius: 4,
                barPercentage: 0.6,
                hoverBackgroundColor:
                    theme.current.value.colors["primary-darken-1"] || "#1565C0",
            },
        ],
    };
});

const chartOptions = computed<ChartOptions<"bar">>(() => {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                backgroundColor: "rgba(0, 0, 0, 0.8)",
                titleFont: { size: 13 },
                bodyFont: { size: 13 },
                padding: 10,
                cornerRadius: 4,
                callbacks: {
                    label: (context) => {
                        const index = context.dataIndex;
                        const item = props.trends[index];
                        return [
                            `Omzet: Rp${formatPrice(item.turnover)}`,
                            `Transaksi: ${item.total_orders}`,
                        ];
                    },
                },
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                border: { dash: [4, 4], display: false },
                grid: {
                    color: "rgba(0, 0, 0, 0.05)",
                },
                ticks: {
                    callback: (value) => {
                        return "Rp" + formatPrice(Number(value));
                    },
                },
            },
            x: {
                grid: {
                    display: false,
                },
            },
        },
    };
});
</script>

<style scoped>
.chart-container {
    height: 300px;
    width: 100%;
    position: relative;
}
</style>
