<template>
    <div class="screen-body">
        <div
            class="card hero-balance"
            style="
                margin-top: 0;
                margin-bottom: 24px;
                background: linear-gradient(
                    125deg,
                    #8a5a0d,
                    #c8902f 65%,
                    #e2b85e
                );
            "
        >
            <div class="balance-top">
                <div>
                    <div class="soft-label" style="color: #f9e8bd">
                        Total Poin Tahun {{ selectedYear }}
                    </div>
                    <div class="money">
                        <span
                            v-if="isLoading"
                            class="skeleton-text skeleton-dark skeleton-money"
                        ></span>
                        <span v-else
                            >{{
                                formatPrice(data?.total_points || 0)
                            }}
                            Poin</span
                        >
                    </div>
                </div>
                <v-icon icon="mdi-gift-outline" size="28" color="white" />
            </div>
            <div class="balance-bottom">
                <span
                    v-if="isLoading"
                    class="skeleton-text skeleton-dark"
                    style="width: 150px; height: 16px"
                ></span>
                <template v-else>
                    <span
                        >Januari–Desember {{ data?.year || selectedYear }}</span
                    >
                </template>
            </div>
        </div>

        <div
            class="section-heading"
            style="
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
            "
        >
            <div style="flex: 1">
                <h3>Akumulasi Per Bulan</h3>
            </div>
            <div style="width: 120px">
                <v-select
                    v-model="selectedYear"
                    :items="yearOptions"
                    variant="outlined"
                    density="compact"
                    hide-details
                    @update:model-value="fetchData"
                ></v-select>
            </div>
        </div>

        <div class="card list-card mt-3">
            <div v-if="isLoading" style="padding: 24px; text-align: center">
                <v-progress-circular
                    indeterminate
                    color="primary"
                    size="24"
                ></v-progress-circular>
                <p
                    style="
                        margin: 12px 0 0;
                        color: var(--muted);
                        font-size: 13px;
                    "
                >
                    Memuat data reward tahunan...
                </p>
            </div>
            <template v-else>
                <div
                    v-if="!data?.months?.length"
                    style="padding: 24px; text-align: center"
                >
                    <v-icon
                        icon="mdi-gift-outline"
                        size="32"
                        color="grey-lighten-1"
                        class="mb-2"
                    ></v-icon>
                    <p style="margin: 0; color: var(--muted); font-size: 13px">
                        Belum ada riwayat akumulasi poin tahun ini
                    </p>
                </div>
                <div
                    v-else
                    class="list-row"
                    v-for="month in data.months"
                    :key="month.month"
                >
                    <div class="row-icon">
                        <v-icon icon="mdi-gift-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong
                            >{{ getMonthName(month.month) }}
                            {{ data.year }}</strong
                        >
                        <span>Total poin transaksi yang terkumpul</span>
                    </div>
                    <div
                        class="row-side"
                        :style="{
                            color:
                                month.total_points > 0
                                    ? 'var(--green)'
                                    : 'var(--muted)',
                            fontWeight: 700,
                        }"
                    >
                        +{{ formatPrice(month.total_points) }}
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import rewardService from "@/member/services/reward.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import type { AnnualRewardData } from "@/member/types/reward";

const { getMonthName, formatPrice } = useFormatter();

const currentYear = new Date().getFullYear();
const selectedYear = ref<string | number>(currentYear);
const availableYears = ref<number[]>([]);

const yearOptions = computed(() => {
    return (availableYears.value.length > 0 ? availableYears.value : [currentYear]).map((year) => ({
        title: String(year),
        value: year,
    }));
});

const data = ref<AnnualRewardData | null>(null);
const isLoading = ref(true);

const fetchData = async () => {
    isLoading.value = true;
    try {
        const response = await rewardService.getAnnualRewards({
            year: selectedYear.value,
        });
        availableYears.value = response.available_years ?? [];

        if (
            availableYears.value.length > 0 &&
            !availableYears.value.includes(Number(selectedYear.value))
        ) {
            selectedYear.value = availableYears.value[0];
            await fetchData();

            return;
        }

        data.value = response.data;
    } catch (error) {
        console.error("Failed to fetch annual rewards:", error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchData();
});
</script>
