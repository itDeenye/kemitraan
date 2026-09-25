<template>
    <div class="screen-body">
        <div class="card hero-balance">
            <div class="balance-top">
                <div>
                    <div class="soft-label">Reward bulanan</div>
                    <div class="money">
                        Rp{{
                            formatPrice(growthData?.summary.monthly_reward || 0)
                        }}
                    </div>
                </div>
                <v-icon icon="mdi-gift-outline" size="28" color="white" />
            </div>
            <div class="balance-bottom">
                <span>{{ rewardPointLabel }}</span>
                <small class="reward-minimum-note">
                    {{ rewardMinimumLabel }}
                </small>
            </div>
        </div>

        <div class="section-heading mt-6">
            <h3>Jenis reward</h3>
        </div>
        <div class="quick-grid">
            <button
                class="quick-item flow-link"
                type="button"
                @click="router.push('/member/rewards/monthly')"
            >
                <div class="quick-icon">
                    <v-icon icon="mdi-wallet-outline" size="20" />
                </div>
                Bulanan
            </button>
            <button
                class="quick-item flow-link"
                type="button"
                @click="router.push('/member/rewards/annual')"
            >
                <div class="quick-icon">
                    <v-icon icon="mdi-gift-outline" size="20" />
                </div>
                Tahunan
            </button>
            <button
                class="quick-item flow-link"
                type="button"
                @click="router.push('/member/rewards/stockist')"
            >
                <div class="quick-icon">
                    <v-icon icon="mdi-storefront-outline" size="20" />
                </div>
                Voucher
            </button>
            <button
                class="quick-item flow-link"
                type="button"
                @click="router.push('/member/rewards/sharing')"
            >
                <div class="quick-icon">
                    <v-icon icon="mdi-account-network-outline" size="20" />
                </div>
                Sharing Profit
            </button>
        </div>

        <div class="section-heading mt-6">
            <h3>Pertumbuhan reward</h3>
            <span v-if="!isLoading">{{
                growthData?.period.range_label || "-"
            }}</span>
            <span v-else>
                <v-skeleton-loader
                    type="text"
                    width="100"
                    class="d-inline-block bg-transparent"
                />
            </span>
        </div>
        <div
            class="card chart-card"
            style="padding-bottom: 30px; position: relative"
        >
            <div v-if="isLoading" class="pt-2">
                <div
                    class="d-flex justify-space-between align-center border-b pb-3 mb-4"
                >
                    <div>
                        <v-skeleton-loader
                            type="text"
                            width="80"
                            class="mb-1 bg-transparent"
                        />
                        <v-skeleton-loader
                            type="text"
                            width="60"
                            class="bg-transparent"
                        />
                    </div>
                    <v-skeleton-loader
                        type="chip"
                        width="60"
                        class="bg-transparent"
                    />
                </div>
                <div class="d-flex align-end gap-2" style="height: 100px">
                    <v-skeleton-loader
                        v-for="i in 8"
                        :key="i"
                        type="image"
                        class="flex-grow-1"
                        :style="{
                            height: Math.floor(Math.random() * 60 + 20) + 'px',
                            borderRadius: '4px 4px 0 0',
                        }"
                    />
                </div>
            </div>

            <template v-else>
                <div
                    class="balance-top"
                    style="
                        align-items: center;
                        border-bottom: 1px solid var(--border);
                        padding-bottom: 12px;
                        margin-bottom: 24px;
                    "
                >
                    <div>
                        <span class="soft-label">Reward bulanan</span>
                        <div class="money" style="font-size: 16px">
                            {{ growthPercentLabel }}
                        </div>
                    </div>
                    <span
                        class="badge"
                        :class="
                            growthData?.growth.status === 'down'
                                ? 'red'
                                : 'green'
                        "
                    >
                        {{ growthData?.growth.label || "Stabil" }}
                    </span>
                </div>

                <div
                    style="
                        display: flex;
                        gap: 6px;
                        height: 110px;
                        align-items: flex-end;
                        justify-content: center;
                    "
                >
                    <div
                        v-for="item in growthSeries"
                        :key="item.month"
                        @click="
                            activeMonth =
                                activeMonth === item.month ? null : item.month
                        "
                        style="
                            flex: 1;
                            height: 100%;
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            justify-content: flex-end;
                            position: relative;
                            cursor: pointer;
                        "
                    >
                        <!-- Tooltip Popup -->
                        <div
                            v-if="activeMonth === item.month"
                            class="bg-[var(--ink)] text-white rounded shadow-sm text-center"
                            style="
                                position: absolute;
                                bottom: calc(100% + 8px);
                                padding: 4px 6px;
                                font-size: 10px;
                                font-weight: 600;
                                white-space: nowrap;
                                z-index: 10;
                                transition: opacity 0.2s;
                            "
                        >
                            Rp{{ formatPrice(item.reward_value) }}
                            <div
                                style="
                                    position: absolute;
                                    bottom: -4px;
                                    left: 50%;
                                    transform: translateX(-50%);
                                    border-width: 4px;
                                    border-style: solid;
                                    border-color: var(--ink) transparent
                                        transparent transparent;
                                "
                            ></div>
                        </div>

                        <!-- Bar -->
                        <div
                            :style="{
                                width: '100%',
                                maxWidth: '40px',
                                height: item.heightPx + 'px',
                                borderRadius: '4px 4px 0 0',
                                background:
                                    item.month === growthData?.period.month
                                        ? 'linear-gradient(180deg, #d84260, var(--wine))'
                                        : item.reward_value > 0
                                          ? '#fca5a5'
                                          : '#f1f5f9',
                                opacity:
                                    activeMonth && activeMonth !== item.month
                                        ? 0.4
                                        : 1,
                                transition: 'all 0.3s ease',
                            }"
                        ></div>

                        <!-- Bottom Label -->
                        <div
                            style="
                                position: absolute;
                                bottom: -24px;
                                text-align: center;
                                width: 100%;
                            "
                        >
                            <span
                                :style="{
                                    fontSize: '10px',
                                    fontWeight:
                                        item.month === growthData?.period.month
                                            ? 700
                                            : 500,
                                    color:
                                        item.month === growthData?.period.month
                                            ? 'var(--ink)'
                                            : 'var(--muted)',
                                }"
                            >
                                {{ item.label }}
                            </span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import rewardService from "@/member/services/reward.service";
import type { MonthlyRewardGrowthResponse } from "@/member/types/reward";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const router = useRouter();
const { formatPrice } = useFormatter();
const snackbar = useSnackbarStore();
const growthData = ref<MonthlyRewardGrowthResponse["data"] | null>(null);
const isLoading = ref(true);
const activeMonth = ref<number | null>(null);

const rewardPointLabel = computed(() => {
    return `${growthData.value?.summary.total_points || 0} poin terkumpul`;
});

const rewardMinimumLabel = computed(() => {
    const summary = growthData.value?.summary;
    if (!summary) {
        return "Minimum reward dimuat...";
    }

    const levelName = summary.member_level.name || "level ini";
    const minimum = summary.minimum_points || 0;

    if (summary.is_qualified) {
        return `Minimum ${levelName} ${minimum} poin sudah terpenuhi`;
    }

    return `Minimum ${levelName} ${minimum} poin • kurang ${summary.remaining_points} poin lagi`;
});

const growthPercentLabel = computed(() => {
    const percent = growthData.value?.growth.percent || 0;

    if (percent > 0) {
        return `+${percent.toLocaleString("id-ID")}%`;
    }

    return `${percent.toLocaleString("id-ID")}%`;
});

const CHART_MAX_HEIGHT = 100; // px

const growthSeries = computed(() => {
    const series = growthData.value?.series || [];
    const maxValue = Math.max(...series.map((item) => item.reward_value), 0);

    return series.map((item) => ({
        ...item,
        heightPx:
            maxValue === 0
                ? 8
                : Math.max(
                      8,
                      Math.round(
                          (item.reward_value / maxValue) * CHART_MAX_HEIGHT,
                      ),
                  ),
    }));
});

const fetchGrowth = async () => {
    isLoading.value = true;
    try {
        const response = await rewardService.getMonthlyRewardGrowth();
        if (response.success) {
            growthData.value = response.data;
        }
    } catch {
        snackbar.showMessage("Gagal memuat pertumbuhan reward", "error");
    } finally {
        isLoading.value = false;
    }
};

onMounted(fetchGrowth);
</script>
