<template>
    <div class="screen-body">
        <div v-if="isLoading" style="padding: 24px; text-align: center">
            <v-progress-circular
                indeterminate
                color="primary"
                size="24"
            ></v-progress-circular>
            <p style="margin: 12px 0 0; color: var(--muted); font-size: 13px">
                Memuat data detail reward...
            </p>
        </div>

        <div v-else-if="reward">
            <div
                class="card hero-balance"
                style="
                    background: linear-gradient(
                        125deg,
                        #72500e,
                        #b47b16 65%,
                        #d4a33e
                    );
                "
            >
                <div class="balance-top">
                    <div>
                        <div class="soft-label" style="color: #f9e8bd">
                            Total Voucher {{ getMonthName(reward.month) }}
                            {{ reward.year }}
                        </div>
                        <div class="money">
                            {{
                                reward.voucher_value > 0
                                    ? `Rp${formatPrice(reward.voucher_value)}`
                                    : "Rp0"
                            }}
                        </div>
                    </div>
                    <v-icon
                        icon="mdi-storefront-outline"
                        size="28"
                        color="white"
                    />
                </div>
                <div class="balance-bottom">
                    <span v-if="reward.percentage > 0"
                        >Persentase {{ reward.percentage }}%</span
                    >
                    <span v-else>Persentase —</span>
                </div>
            </div>

            <div class="section-heading mt-6">
                <h3>Informasi Reward</h3>
            </div>
            <div class="card list-card">
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-calendar-month-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Periode Reward</strong>
                        <span>Perhitungan pembelanjaan stokis</span>
                    </div>
                    <div class="row-side">
                        <span style="font-weight: 600"
                            >{{ getMonthName(reward.month) }}
                            {{ reward.year }}</span
                        >
                    </div>
                </div>
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-cart-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Total Pembelanjaan</strong>
                        <span>Transaksi tervalidasi selama periode</span>
                    </div>
                    <div class="row-side">
                        <span style="font-weight: 600"
                            >Rp{{ formatPrice(reward.total_spending) }}</span
                        >
                    </div>
                </div>
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-percent-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Persentase Reward</strong>
                        <span>Sesuai ketentuan level stokis</span>
                    </div>
                    <div class="row-side">
                        <span style="font-weight: 600"
                            >{{ reward.percentage }}%</span
                        >
                    </div>
                </div>
            </div>

            <div class="section-heading mt-6">
                <h3>Status Penggunaan</h3>
            </div>
            <div class="card list-card">
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-ticket-percent-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Status Voucher</strong>
                        <span>Status penggunaan saat ini</span>
                    </div>
                    <div class="row-side">
                        <BaseBadge
                            type="stockist_reward_status"
                            :value="
                                reward.voucher_value === 0
                                    ? 'expired'
                                    : reward.status === 'Digunakan' ||
                                        reward.status === 'used'
                                      ? 'used'
                                      : 'available'
                            "
                        />
                    </div>
                </div>
            </div>

            <div class="role-note mt-4" v-if="reward.voucher_value > 0">
                <v-icon icon="mdi-information-outline" size="14"></v-icon>
                Voucher hanya dapat digunakan satu kali pada satu transaksi.
            </div>
        </div>

        <div v-else style="padding: 24px; text-align: center" class="card mt-3">
            <v-icon
                icon="mdi-alert-circle-outline"
                size="32"
                color="grey-lighten-1"
                class="mb-2"
            ></v-icon>
            <p style="margin: 0; color: var(--muted); font-size: 13px">
                Data voucher tidak ditemukan.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import rewardService from "@/member/services/reward.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import type { StockistRewardItem } from "@/member/types/reward";
import { decodeRouteId } from "@/shared/utils/route-id";

const route = useRoute();
const { formatPrice, getMonthName } = useFormatter();

const reward = ref<StockistRewardItem | null>(null);
const isLoading = ref(true);

const fetchDetail = async () => {
    isLoading.value = true;
    try {
        const id = decodeRouteId(
            (route.params as Record<string, unknown>).id,
        );
        const response = await rewardService.getStockistReward(id);
        if (response.data) {
            reward.value = response.data;
        }
    } catch (error) {
        console.error("Failed to fetch stockist reward detail:", error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchDetail();
});
</script>
