<template>
    <v-card variant="outlined" class="pa-4 h-100 rounded-lg">
        <h2 class="text-h6 font-weight-bold mb-4">Status Transaksi</h2>
        <v-list density="compact" class="pa-0 bg-transparent">
            <v-list-item
                v-for="(status, index) in sortedStatuses"
                :key="index"
                class="px-0 mb-2"
            >
                <div class="d-flex align-center justify-space-between mb-1">
                    <div class="text-body-2">{{ status.label }}</div>
                    <div
                        class="text-body-2 font-weight-bold"
                        :class="`text-${getColor(status.code)}`"
                    >
                        {{ status.total }}
                    </div>
                </div>
                <v-progress-linear
                    :model-value="getPercentage(status.total)"
                    :color="getColor(status.code)"
                    height="4"
                    rounded
                ></v-progress-linear>
            </v-list-item>
        </v-list>
    </v-card>
</template>

<script setup lang="ts">
import { computed } from "vue";
import type { OrderStatusAnalytic } from "@/admin/types/analytics";

const props = defineProps<{
    statuses: OrderStatusAnalytic[];
}>();

const sortedStatuses = computed(() => {
    const filtered = props.statuses.filter((s) => s.total > 0);
    if (filtered.length > 0) {
        return filtered.sort((a, b) => b.total - a.total);
    }
    return props.statuses.slice(0, 5);
});

const maxTotal = computed(() => {
    if (!sortedStatuses.value.length) return 0;
    return Math.max(...sortedStatuses.value.map((s) => s.total));
});

const getPercentage = (val: number) => {
    if (maxTotal.value === 0) return 0;
    return (val / maxTotal.value) * 100;
};

const getColor = (code: string) => {
    const colors: Record<string, string> = {
        waiting_stock_screening: "warning",
        waiting_payment: "warning",
        waiting_payment_approval: "info",
        processing: "info",
        shipped: "primary",
        reship_required: "error",
        ready_to_pickup: "primary",
        received: "success",
        completed: "success",
        cancelled: "error",
        rejected: "error",
    };
    return colors[code] || "grey";
};
</script>
