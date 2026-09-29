<template>
    <div class="screen-body">
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
                <h3>Riwayat Sharing Profit</h3>
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
                    Memuat data sharing profit...
                </p>
            </div>

            <template v-else>
                <div
                    v-if="rewards.length === 0"
                    style="padding: 24px; text-align: center"
                >
                    <v-icon
                        icon="mdi-account-network-outline"
                        size="32"
                        color="grey-lighten-1"
                        class="mb-2"
                    ></v-icon>
                    <p style="margin: 0; color: var(--muted); font-size: 13px">
                        Belum ada riwayat sharing profit
                    </p>
                </div>
                <template v-else>
                    <router-link
                        v-for="reward in rewards"
                        :key="reward.id"
                        class="d-flex py-4 px-4 border-b border-[var(--line)] last:border-b-0 flow-link align-start text-decoration-none"
                        :to="`/member/rewards/sharing/${encodeRouteId(reward.id)}`"
                    >
                        <div
                            class="row-icon shrink-0 mr-3 mt-1 w-[32px] h-[32px] rounded-md bg-[#fef2f2] flex items-center justify-center"
                        >
                            <v-icon
                                icon="mdi-account-network-outline"
                                size="16"
                                color="#ef4444"
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start">
                                <div class="flex-1 min-w-0">
                                    <strong
                                        class="block truncate text-[13px] text-[var(--text)]"
                                    >
                                        {{ reward.buyer.name }}
                                    </strong>
                                    <span
                                        class="block text-[12px] font-normal text-[var(--muted)] truncate mt-1"
                                    >
                                        <template
                                            v-if="reward.transaction?.code"
                                            >{{
                                                reward.transaction.code
                                            }}
                                            &bull;
                                        </template>
                                        {{ formatDate(reward.created_at) }}
                                    </span>
                                </div>
                                <div
                                    class="flex flex-col items-end shrink-0 ml-3"
                                >
                                    <BaseBadge
                                        type="sharing_reward_status"
                                        :value="reward.status"
                                    />
                                </div>
                            </div>

                            <div
                                class="flex justify-between items-center mt-3 pt-3 border-t border-dashed border-[var(--line)]"
                            >
                                <span class="text-[12px] text-[var(--muted)]">
                                    Belanja
                                    <span class="text-[var(--text)] font-medium"
                                        >Rp{{
                                            formatPrice(
                                                reward.transaction.amount,
                                            )
                                        }}</span
                                    >
                                </span>
                                <span
                                    class="text-[12px] text-primary font-bold"
                                >
                                    Komisi Rp{{ formatPrice(reward.amount) }}
                                </span>
                            </div>
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
import { ref, onMounted } from "vue";
import rewardService from "@/member/services/reward.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import type {
    SharingProfitItem,
    DataTablePagination,
} from "@/member/types/reward";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { encodeRouteId } from "@/shared/utils/route-id";

const { formatPrice, formatDate } = useFormatter();

const rewards = ref<SharingProfitItem[]>([]);
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
        const response = await rewardService.getSharingProfits({
            page: pagination.value.current,
            limit: 10,
        });

        if (loadMoreData) {
            rewards.value.push(...response.data.results);
        } else {
            rewards.value = response.data.results;
        }

        if (response.data.pagination) {
            pagination.value = response.data.pagination;
        }
    } catch (error) {
        console.error("Failed to fetch sharing profits:", error);
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
