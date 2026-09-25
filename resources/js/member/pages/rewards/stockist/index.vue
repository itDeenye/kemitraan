<template>
    <div class="screen-body">
        <div
            class="stat-grid"
            style="grid-template-columns: repeat(2, 1fr); margin-bottom: 24px"
        >
            <div
                class="card hero-balance"
                style="
                    margin-top: 0;
                    background: linear-gradient(
                        125deg,
                        #8a5a0d,
                        #c8902f 65%,
                        #e2b85e
                    );
                "
            >
                <div class="balance-top" style="margin-bottom: 0">
                    <div>
                        <div class="soft-label" style="color: #f9e8bd">
                            Total Pembelanjaan
                            {{
                                selectedYear === "all" ? "Semua" : selectedYear
                            }}
                        </div>
                        <div class="money" style="font-size: 18px">
                            <span
                                v-if="isLoading && !isLoadMore"
                                class="skeleton-text skeleton-dark skeleton-money"
                            ></span>
                            <span v-else
                                >Rp{{
                                    formatPrice(summary.total_pembelanjaan)
                                }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="card hero-balance"
                style="
                    margin-top: 0;
                    background: linear-gradient(
                        125deg,
                        #064e3b,
                        #047857 70%,
                        #059669
                    );
                "
            >
                <div class="balance-top" style="margin-bottom: 0">
                    <div>
                        <div class="soft-label" style="color: #a7f3d0">
                            Total Voucher
                        </div>
                        <div class="money" style="font-size: 18px">
                            <span
                                v-if="isLoading && !isLoadMore"
                                class="skeleton-text skeleton-dark skeleton-money"
                            ></span>
                            <span v-else
                                >Rp{{ formatPrice(summary.total_voucher) }}</span
                            >
                        </div>
                    </div>
                </div>
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
                <h3>Riwayat Voucher</h3>
            </div>
            <div style="width: 150px">
                <v-select
                    v-model="selectedYear"
                    :items="yearOptions"
                    variant="outlined"
                    density="compact"
                    hide-details
                    @update:model-value="
                        pagination.current = 1;
                        fetchData();
                    "
                ></v-select>
            </div>
        </div>

        <div class="card list-card mt-3">
            <div
                v-if="isLoading && !isLoadMore"
                style="padding: 24px; text-align: center"
            >
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
                    Memuat data reward stokis...
                </p>
            </div>
            <template v-else>
                <div
                    v-if="rewards.length === 0"
                    style="padding: 24px; text-align: center"
                >
                    <v-icon
                        icon="mdi-storefront-outline"
                        size="32"
                        color="grey-lighten-1"
                        class="mb-2"
                    ></v-icon>
                    <p style="margin: 0; color: var(--muted); font-size: 13px">
                        Belum ada riwayat reward stokis
                    </p>
                </div>
                <template v-else>
                    <router-link
                        v-for="reward in rewards"
                        :key="reward.id"
                        class="list-row flow-link"
                        :to="`/member/rewards/stockist/${encodeRouteId(reward.id)}`"
                    >
                        <div class="row-icon">
                            <v-icon icon="mdi-receipt-text-outline" size="16" />
                        </div>
                        <div class="row-main">
                            <strong
                                >{{ getMonthName(reward.month) }}
                                {{ reward.year }}</strong
                            >
                            <span
                                >Belanja Rp{{
                                    formatPrice(reward.total_spending)
                                }}</span
                            >
                        </div>
                        <div class="row-side">
                            <span
                                class="badge"
                                :class="
                                    reward.voucher_value > 0
                                        ? reward.status === 'Digunakan'
                                            ? 'red'
                                            : 'green'
                                        : 'orange'
                                "
                            >
                                {{
                                    reward.voucher_value === 0
                                        ? "Tidak Qualified"
                                        : `Rp${formatPrice(reward.voucher_value)}`
                                }}
                            </span>
                        </div>
                    </router-link>

                    <!-- Pagination / Load More -->
                    <div
                        class="d-flex justify-center mt-3 mb-4"
                        v-if="
                            pagination &&
                            pagination.current < pagination.total_page
                        "
                    >
                        <v-btn
                            variant="outlined"
                            color="primary"
                            :loading="isLoadMore"
                            @click="loadMore"
                            rounded
                        >
                            Muat Lainnya
                        </v-btn>
                    </div>
                </template>
            </template>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import rewardService from "@/member/services/reward.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import { encodeRouteId } from "@/shared/utils/route-id";
import type {
    StockistRewardItem,
    StockistRewardSummary,
    DataTablePagination,
} from "@/member/types/reward";

const { getMonthName, formatPrice } = useFormatter();

const currentYear = new Date().getFullYear();
const selectedYear = ref<string | number>(currentYear);
const availableYears = ref<number[]>([]);

const yearOptions = computed(() => {
    return [
        { title: "Semua Periode", value: "all" },
        ...(availableYears.value.length > 0 ? availableYears.value : [currentYear]).map((year) => ({
            title: String(year),
            value: year,
        })),
    ];
});

const rewards = ref<StockistRewardItem[]>([]);
const summary = ref<StockistRewardSummary>({
    total_pembelanjaan: 0,
    total_voucher: 0,
    persentase_voucher: 0,
});
const isLoading = ref(true);
const isLoadMore = ref(false);

const pagination = ref<DataTablePagination>({
    total_data: 0,
    total_page: 0,
    total_display: 0,
    first_page: false,
    last_page: false,
    prev: 0,
    current: 1,
    next: 0,
    detail: [],
    start: 0,
    end: 0,
});

const fetchData = async (loadMoreData = false) => {
    if (loadMoreData) {
        isLoadMore.value = true;
    } else {
        isLoading.value = true;
    }

    try {
        const response = await rewardService.getStockistRewards({
            page: pagination.value.current,
            limit: 10,
            year: selectedYear.value === "all" ? undefined : selectedYear.value,
        });
        availableYears.value = response.available_years ?? [];

        if (
            !loadMoreData &&
            selectedYear.value !== "all" &&
            availableYears.value.length > 0 &&
            !availableYears.value.includes(Number(selectedYear.value))
        ) {
            selectedYear.value = availableYears.value[0];
            pagination.value.current = 1;
            await fetchData();

            return;
        }

        if (loadMoreData) {
            rewards.value.push(...response.data.results);
        } else {
            rewards.value = response.data.results;
        }

        pagination.value = response.data.pagination;
        if (response.summary) {
            summary.value = response.summary;
        }
    } catch (error) {
        console.error("Failed to fetch stockist rewards:", error);
    } finally {
        isLoading.value = false;
        isLoadMore.value = false;
    }
};

const loadMore = () => {
    pagination.value.current++;
    fetchData(true);
};

onMounted(() => {
    fetchData();
});
</script>
