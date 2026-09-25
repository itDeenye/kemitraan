<template>
    <div class="screen-body">
        <div v-if="canViewPayable" class="reward-tabs">
            <button
                :class="['reward-tab', { active: activeTab === 'mine' }]"
                @click="selectTab('mine')"
            >
                Reward Anda
            </button>
            <button
                :class="['reward-tab', { active: activeTab === 'payable' }]"
                @click="selectTab('payable')"
            >
                Wajib Dibayar
                <span class="tab-count">{{ downlineActionCount }}</span>
            </button>
        </div>

        <div class="stat-grid reward-summary-grid">
            <div class="card hero-balance reward-card-primary">
                <div class="soft-label reward-soft-label">
                    {{
                        activeTab === "mine"
                            ? "Total Reward"
                            : "Total Kewajiban"
                    }}
                </div>
                <div class="money reward-money">
                    <span
                        v-if="isLoading"
                        class="skeleton-text skeleton-dark skeleton-money"
                    ></span>
                    <span v-else
                        >Rp{{
                            formatPrice(activeSummary.total_akumulasi)
                        }}</span
                    >
                </div>
            </div>

            <div class="card hero-balance reward-card-paid">
                <div class="soft-label reward-paid-label">
                    {{
                        activeTab === "mine"
                            ? "Total Ditransfer"
                            : "Sudah Dibayar"
                    }}
                </div>
                <div class="money reward-money">
                    <span
                        v-if="isLoading"
                        class="skeleton-text skeleton-dark skeleton-money"
                    ></span>
                    <span v-else
                        >Rp{{
                            formatPrice(activeSummary.total_dibayarkan)
                        }}</span
                    >
                </div>
            </div>
        </div>

        <div class="section-heading reward-heading">
            <div>
                <h3>
                    {{
                        activeTab === "mine"
                            ? "Riwayat Reward"
                            : "Reward Downline"
                    }}
                </h3>
            </div>
            <div class="year-filter">
                <v-select
                    v-model="selectedYear"
                    :items="yearOptions"
                    variant="outlined"
                    density="compact"
                    hide-details
                    @update:model-value="reloadCurrentTab"
                />
            </div>
        </div>

        <div class="card list-card mt-3">
            <div v-if="isLoading && !isLoadMore" class="empty-state">
                <v-progress-circular indeterminate color="primary" size="24" />
                <p>Memuat reward bulanan...</p>
            </div>

            <template v-else-if="activeItems.length > 0">
                <div class="pb-4">
                    <template v-if="activeTab === 'mine'">
                        <router-link
                            v-for="reward in personalRewards"
                            :key="reward.id"
                            class="list-row flow-link"
                            :to="`/member/rewards/monthly/${encodeRouteId(reward.id)}`"
                        >
                            <div class="row-icon">
                                <v-icon icon="mdi-wallet-outline" size="16" />
                            </div>
                            <div class="row-main">
                                <strong
                                    >{{ getMonthName(reward.month) }}
                                    {{ reward.year }}</strong
                                >
                                <span v-if="reward.reward_value > 0">
                                    {{ reward.total_points }} poin · Rp{{
                                        formatPrice(reward.reward_value)
                                    }}
                                </span>
                                <span v-else
                                    >{{ reward.total_points }} poin · Tidak
                                    terkualifikasi</span
                                >
                                <span class="reward-payer">
                                    Dibayar oleh:
                                    {{ reward.payment_responsibility.label }}
                                </span>
                            </div>
                            <div class="row-side">
                                <BaseBadge
                                    type="reward_status"
                                    :value="
                                        reward.reward_value <= 0
                                            ? 'unqualified'
                                            : reward.is_processed
                                              ? 'transferred'
                                              : 'pending'
                                    "
                                />
                            </div>
                        </router-link>
                    </template>

                    <template v-else>
                        <div
                            v-for="reward in downlineRewards"
                            :key="reward.id"
                            class="list-row payable-row"
                        >
                            <div class="row-icon">
                                <v-icon
                                    icon="mdi-account-cash-outline"
                                    size="17"
                                />
                            </div>
                            <div class="row-main">
                                <strong>{{ reward.member.name }}</strong>
                                <span class="d-flex align-center gap-2"
                                    >{{ reward.member.code }}
                                    <BaseBadge
                                        type="level"
                                        chip-class="!h-auto !py-[2px] !px-2 !text-[11px]"
                                        :value="reward.member.level.name"
                                        inline
                                    />
                                </span>
                                <span>
                                    {{ getMonthName(reward.month) }}
                                    {{ reward.year }} ·
                                    {{ reward.total_points }} poin · Rp{{
                                        formatPrice(reward.reward_value)
                                    }}
                                </span>
                            </div>
                            <div class="payable-action">
                                <BaseBadge
                                    type="reward_status"
                                    :value="
                                        reward.reward_value <= 0
                                            ? 'unqualified'
                                            : reward.is_processed
                                              ? 'transferred'
                                              : 'pending'
                                    "
                                />
                                <button
                                    v-if="
                                        reward.reward_value > 0 &&
                                        !reward.is_processed
                                    "
                                    class="approve-button"
                                    :disabled="
                                        approvingId === reward.id ||
                                        !reward.bank
                                    "
                                    :title="
                                        reward.bank
                                            ? 'Transfer reward'
                                            : 'Rekening mitra belum tersedia'
                                    "
                                    @click="approveReward(reward)"
                                >
                                    {{
                                        approvingId === reward.id
                                            ? "Memproses..."
                                            : "Transfer"
                                    }}
                                </button>
                            </div>
                        </div>
                    </template>

                    <div
                        v-if="pagination.current < pagination.total_page"
                        class="px-4 mt-4"
                    >
                        <v-btn
                            block
                            variant="outlined"
                            color="primary"
                            :loading="isLoadMore"
                            class="text-none font-weight-bold"
                            @click="loadMore"
                        >
                            Muat lainnya
                        </v-btn>
                    </div>
                </div>
            </template>

            <div v-else class="empty-state">
                <v-icon
                    icon="mdi-gift-outline"
                    size="32"
                    color="grey-lighten-1"
                />
                <p>
                    {{
                        activeTab === "mine"
                            ? "Belum ada riwayat reward"
                            : "Belum ada reward yang wajib dibayar"
                    }}
                </p>
            </div>
        </div>

        <MobileConfirm ref="confirmDialog" />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import MobileConfirm from "@/member/components/MobileConfirm.vue";
import rewardService from "@/member/services/reward.service";
import { useAuthStore } from "@/shared/stores/auth";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useFormatter } from "@/shared/composables/useFormatter";
import { encodeRouteId } from "@/shared/utils/route-id";
import type {
    DataTablePagination,
    DownlineMonthlyRewardItem,
    MonthlyRewardItem,
    MonthlyRewardSummary,
} from "@/member/types/reward";

type RewardTab = "mine" | "payable";

const authStore = useAuthStore();
const snackbar = useSnackbarStore();
const { formatPrice, getMonthName } = useFormatter();
const currentYear = new Date().getFullYear();
const canViewPayable = computed(() =>
    ["DST", "AGT"].includes(authStore.user?.member?.level?.code || ""),
);
const activeTab = ref<RewardTab>("mine");
const selectedYear = ref<string | number>(currentYear);
const personalYears = ref<number[]>([]);
const downlineYears = ref<number[]>([]);
const personalRewards = ref<MonthlyRewardItem[]>([]);
const downlineRewards = ref<DownlineMonthlyRewardItem[]>([]);
const personalSummary = ref<MonthlyRewardSummary>({
    total_akumulasi: 0,
    total_dibayarkan: 0,
});
const downlineSummary = ref<MonthlyRewardSummary>({
    total_akumulasi: 0,
    total_dibayarkan: 0,
});
const downlineActionCount = ref(0);
const currentPage = ref(1);
const isLoading = ref(true);
const isLoadMore = ref(false);
let fetchSequence = 0;
const approvingId = ref<number | null>(null);
const confirmDialog = ref<InstanceType<typeof MobileConfirm> | null>(null);
const pagination = ref<DataTablePagination>({
    total_data: 0,
    total_page: 0,
    total_display: 0,
    first_page: true,
    last_page: true,
    prev: 0,
    current: 1,
    next: 0,
    detail: [],
    start: 0,
    end: 0,
});

const activeSummary = computed(() =>
    activeTab.value === "mine" ? personalSummary.value : downlineSummary.value,
);
const activeItems = computed(() =>
    activeTab.value === "mine" ? personalRewards.value : downlineRewards.value,
);
const activeYears = computed(() =>
    activeTab.value === "mine" ? personalYears.value : downlineYears.value,
);
const yearOptions = computed(() => [
    { title: "Semua Tahun", value: "all" },
    ...(activeYears.value.length > 0 ? activeYears.value : [currentYear]).map(
        (year) => ({
            title: String(year),
            value: year,
        }),
    ),
]);

function selectTab(tab: RewardTab) {
    if (activeTab.value === tab) return;
    activeTab.value = tab;
    selectedYear.value = currentYear;
    reloadCurrentTab();
}

function reloadCurrentTab() {
    currentPage.value = 1;
    void fetchData();
}

function loadMore() {
    if (currentPage.value >= pagination.value.total_page) return;
    currentPage.value += 1;
    void fetchData(true);
}

async function fetchData(loadMoreData = false) {
    const requestId = ++fetchSequence;
    const tab = activeTab.value;

    if (loadMoreData) {
        isLoadMore.value = true;
    } else {
        isLoading.value = true;
    }
    const params = {
        page: currentPage.value,
        limit: 10,
        year: selectedYear.value === "all" ? undefined : selectedYear.value,
    };

    try {
        if (tab === "payable") {
            const response =
                await rewardService.getDownlineMonthlyRewards(params);
            if (requestId !== fetchSequence) return;
            downlineRewards.value = loadMoreData
                ? mergeUniqueById(downlineRewards.value, response.data.results)
                : mergeUniqueById([], response.data.results);
            downlineSummary.value = response.summary;
            downlineActionCount.value = response.action_count || 0;
            downlineYears.value = response.available_years || [];
            pagination.value = response.data.pagination;
        } else {
            const response = await rewardService.getMonthlyRewards(params);
            if (requestId !== fetchSequence) return;
            personalRewards.value = loadMoreData
                ? mergeUniqueById(personalRewards.value, response.data.results)
                : mergeUniqueById([], response.data.results);
            personalSummary.value = response.summary_total || response.summary;
            personalYears.value = response.available_years || [];
            pagination.value = response.data.pagination;
        }
    } catch (error: any) {
        if (requestId !== fetchSequence) return;
        snackbar.showMessage(
            error.response?.data?.message || "Gagal memuat reward bulanan.",
            "error",
        );
    } finally {
        if (requestId === fetchSequence) {
            isLoading.value = false;
            isLoadMore.value = false;
        }
    }
}

function mergeUniqueById<T extends { id: number }>(
    current: T[],
    incoming: T[],
): T[] {
    const byId = new Map<number, T>();
    [...current, ...incoming].forEach((item) => byId.set(item.id, item));
    return [...byId.values()];
}

async function fetchActionCount() {
    if (!canViewPayable.value) return;
    try {
        const response = await rewardService.getDownlineMonthlyRewards({
            limit: 1,
        });
        downlineActionCount.value = response.action_count || 0;
        downlineYears.value = response.available_years || [];
    } catch (error) {
        console.error("Failed to load payable reward count:", error);
    }
}

async function approveReward(reward: DownlineMonthlyRewardItem) {
    if (!confirmDialog.value) return;
    const confirmed = await confirmDialog.value.open({
        title: "Transfer Pembayaran Reward",
        message: transferMessage(reward),
        confirmText: "Transfer",
        icon: "mdi-check-decagram-outline",
    });
    if (!confirmed) return;

    approvingId.value = reward.id;
    try {
        const response = await rewardService.approveDownlineMonthlyReward(
            reward.id,
        );
        snackbar.showMessage(
            response.message || "Reward berhasil disetujui.",
            "success",
        );
        currentPage.value = 1;
        await fetchData();
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Gagal menyetujui reward.",
            "error",
        );
    } finally {
        approvingId.value = null;
    }
}

function transferMessage(reward: DownlineMonthlyRewardItem) {
    if (!reward.bank) {
        return `Rekening transfer untuk ${escapeHtml(reward.member.name)} belum tersedia.`;
    }

    const bankName = reward.bank.name || reward.bank.code || "Bank";
    const accountNumber = reward.bank.account_number || "-";
    const accountName = reward.bank.account_name || reward.member.name;

    return `<div style="width:100%;text-align:left"><p style="margin:0 0 10px;text-align:center">Silakan transfer <strong>Rp${formatPrice(reward.reward_value)}</strong> ke rekening berikut:</p><div style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#f9fafb"><div style="display:flex;gap:8px;padding:8px 10px;border-bottom:1px solid #e5e7eb"><span style="width:110px;color:#6b7280">Bank</span><strong style="flex:1;color:#1f2937">${escapeHtml(bankName)}</strong></div><div style="display:flex;gap:8px;padding:8px 10px;border-bottom:1px solid #e5e7eb"><span style="width:110px;color:#6b7280">Nomor Rekening</span><strong style="flex:1;color:#1f2937;word-break:break-all">${escapeHtml(accountNumber)}</strong></div><div style="display:flex;gap:8px;padding:8px 10px"><span style="width:110px;color:#6b7280">Nama Rekening</span><strong style="flex:1;color:#1f2937">${escapeHtml(accountName)}</strong></div></div><p style="margin:10px 0 0;text-align:center">untuk ${escapeHtml(reward.member.name)}. Lanjutkan?</p></div>`;
}

function escapeHtml(value: string) {
    return value.replace(/[&<>'"]/g, (character) => {
        const entities: Record<string, string> = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            "'": "&#039;",
            '"': "&quot;",
        };

        return entities[character];
    });
}

onMounted(() => {
    void fetchData();
    void fetchActionCount();
});
</script>

<style scoped>
.reward-tabs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 5px;
    margin-bottom: 18px;
    padding: 5px;
    border: 1px solid #f0c9d1;
    border-radius: 14px;
    background: #fff1f4;
    width: 100%;
}

.reward-tab {
    min-height: 38px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: var(--wine);
    font-weight: 700;
    font-size: 13px;
}

.reward-tab.active {
    background: var(--wine);
    color: #fff;
    box-shadow: 0 5px 12px rgba(169, 0, 40, 0.2);
}

.tab-count {
    display: inline-flex;
    min-width: 20px;
    height: 20px;
    align-items: center;
    justify-content: center;
    margin-left: 5px;
    padding: 0 6px;
    border-radius: 999px;
    background: rgba(169, 0, 40, 0.1);
    color: var(--wine);
    font-size: 11px;
}

.reward-tab.active .tab-count {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}

.reward-summary-grid {
    grid-template-columns: repeat(2, 1fr);
    margin-bottom: 24px;
}

.reward-summary-grid .hero-balance {
    margin-top: 0;
}

.reward-card-primary {
    background: linear-gradient(125deg, #8d001f, #b20d35 70%, #c72f50);
}

.reward-card-paid {
    background: linear-gradient(125deg, #064e3b, #047857 70%, #059669);
}

.reward-soft-label {
    color: #f1cbd3;
}
.reward-paid-label {
    color: #a7f3d0;
}
.reward-money {
    margin-top: 7px;
    font-size: 18px;
}
.reward-payer {
    color: var(--muted);
    font-size: 12px;
}

.reward-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.year-filter {
    width: 125px;
}
.empty-state {
    padding: 28px 20px;
    text-align: center;
}
.empty-state p {
    margin: 10px 0 0;
    color: var(--muted);
    font-size: 13px;
}
.payable-row {
    align-items: flex-start;
}
.payable-action {
    display: flex;
    min-width: 96px;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.approve-button {
    border: 1px solid var(--wine);
    border-radius: 8px;
    background: var(--wine);
    color: #fff;
    padding: 7px 12px;
    font-size: 12px;
    font-weight: 700;
}

.approve-button:disabled {
    opacity: 0.6;
}

@media (max-width: 420px) {
    .reward-summary-grid {
        gap: 8px;
    }
    .reward-summary-grid .hero-balance {
        padding: 14px;
    }
    .payable-row {
        flex-wrap: wrap;
    }
    .payable-action {
        width: 100%;
        flex-direction: row;
        justify-content: space-between;
    }
}
</style>
