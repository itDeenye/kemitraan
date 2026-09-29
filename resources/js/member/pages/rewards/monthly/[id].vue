<template>
    <div class="screen-body">
        <div v-if="isLoading" style="padding: 24px; text-align: center">
            <v-progress-circular
                indeterminate
                color="primary"
                size="24"
            ></v-progress-circular>
            <p style="margin: 12px 0 0; color: var(--muted); font-size: 13px">
                Memuat detail reward...
            </p>
        </div>

        <div v-else-if="reward">
            <div
                class="card hero-balance"
                style="
                    background: linear-gradient(
                        125deg,
                        #064e3b,
                        #047857 70%,
                        #059669
                    );
                    color: white;
                "
            >
                <div class="balance-top">
                    <div>
                        <div class="soft-label" style="color: #a7f3d0">
                            Nilai Reward
                        </div>
                        <div class="money">
                            Rp{{ formatPrice(reward.reward_value) }}
                        </div>
                    </div>
                    <v-icon icon="mdi-gift-outline" size="28" color="white" />
                </div>
                <div class="balance-bottom">
                    <span
                        >{{ getMonthName(reward.month) }}
                        {{ reward.year }}</span
                    >
                    <span>{{ reward.total_points }} poin</span>
                </div>
            </div>

            <div class="section-heading mt-6">
                <h3>Informasi Reward</h3>
            </div>
            <div class="card list-card">
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-account-cash-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Upline Penanggung</strong>
                        <span>Pihak yang wajib membayar reward</span>
                    </div>
                    <div class="row-side">
                        <span style="font-weight: 600">{{
                            reward.payment_responsibility.label
                        }}</span>
                    </div>
                </div>
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-calendar-month-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Periode Reward</strong>
                        <span>Bulan validasi poin</span>
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
                        <v-icon icon="mdi-star-four-points-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Total Poin</strong>
                        <span>Poin terkumpul di periode ini</span>
                    </div>
                    <div class="row-side">
                        <span style="font-weight: 600"
                            >{{ formatPrice(reward.total_points) }} Poin</span
                        >
                    </div>
                </div>
            </div>

            <div class="section-heading mt-6">
                <h3>Status Pembayaran</h3>
            </div>
            <div class="card list-card">
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-bank-transfer" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Status</strong>
                        <span>Pencairan reward</span>
                    </div>
                    <div class="row-side">
                        <BaseBadge
                            type="reward_status"
                            :value="
                                reward.reward_value <= 0
                                    ? 'unqualified'
                                    : reward.is_processed
                                      ? 'transferred'
                                      : 'waiting'
                            "
                        />
                    </div>
                </div>
                <div class="list-row" v-if="reward.is_processed">
                    <div class="row-icon">
                        <v-icon icon="mdi-calendar-check-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Tanggal Diproses</strong>
                        <span>Waktu pembayaran diselesaikan</span>
                    </div>
                    <div class="row-side">
                        <span style="font-weight: 600">{{
                            formatDateTime(reward.processed_at)
                        }}</span>
                    </div>
                </div>
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
                Data reward tidak ditemukan.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import rewardService from "@/member/services/reward.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import type { MonthlyRewardItem } from "@/member/types/reward";
import { decodeRouteId } from "@/shared/utils/route-id";

const route = useRoute();
const { formatPrice, formatDateTime, getMonthName } = useFormatter();

const reward = ref<MonthlyRewardItem | null>(null);
const isLoading = ref(true);

const fetchDetail = async () => {
    isLoading.value = true;
    try {
        const id = decodeRouteId(
            (route.params as Record<string, unknown>).id,
        );
        const response = await rewardService.getMonthlyReward(id);
        if (response.data) {
            reward.value = response.data;
        }
    } catch (error) {
        console.error("Failed to fetch monthly reward detail:", error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchDetail();
});
</script>
