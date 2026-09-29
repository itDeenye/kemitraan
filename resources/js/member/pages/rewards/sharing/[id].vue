<template>
    <div class="screen-body">
        <div v-if="isLoading" style="padding: 24px; text-align: center">
            <v-progress-circular
                indeterminate
                color="primary"
                size="24"
            ></v-progress-circular>
            <p style="margin: 12px 0 0; color: var(--muted); font-size: 13px">
                Memuat data detail sharing profit...
            </p>
        </div>

        <div v-else-if="reward">
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
                <div class="balance-top">
                    <div>
                        <div class="soft-label" style="color: #a7f3d0">
                            Nilai Komisi
                        </div>
                        <div class="money">
                            Rp{{ formatPrice(reward.amount) }}
                        </div>
                    </div>
                    <v-icon
                        icon="mdi-account-network-outline"
                        size="28"
                        color="white"
                    />
                </div>
                <div class="balance-bottom">
                    <span
                        >Transaksi: {{ reward.transaction.code }} (Rp{{
                            formatPrice(reward.transaction.amount)
                        }})</span
                    >
                    <span>{{ reward.percentage }}%</span>
                </div>
            </div>

            <div class="section-heading mt-6">
                <h3>Status Pencairan</h3>
            </div>

            <div class="card list-card">
                <div class="list-row align-start">
                    <div class="row-icon pt-1">
                        <v-icon icon="mdi-ticket-percent-outline" size="16" />
                    </div>
                    <div class="row-main pr-3">
                        <strong>Status Komisi</strong>
                        <span>Status sharing profit saat ini</span>
                    </div>
                    <div
                        class="row-side text-right whitespace-normal max-w-[55%] break-words"
                    >
                        <BaseBadge
                            type="sharing_reward_status"
                            :value="reward.status"
                        />
                    </div>
                </div>
            </div>

            <div class="section-heading mt-6">
                <h3>Informasi Pembeli</h3>
            </div>

            <div class="card list-card mt-3">
                <div class="list-row align-start">
                    <div class="row-icon pt-1">
                        <v-icon icon="mdi-account-outline" size="16" />
                    </div>
                    <div class="row-main pr-3">
                        <strong>Kode Pembeli</strong>
                        <span>Kode mitra pembeli</span>
                    </div>
                    <div
                        class="row-side text-right whitespace-normal max-w-[55%] break-words"
                    >
                        <span style="font-weight: 600">{{
                            reward.buyer.code
                        }}</span>
                    </div>
                </div>
                <div class="list-row align-start">
                    <div class="row-icon pt-1">
                        <v-icon icon="mdi-account-box-outline" size="16" />
                    </div>
                    <div class="row-main pr-3">
                        <strong>Nama Pembeli</strong>
                        <span>Nama lengkap pembeli</span>
                    </div>
                    <div
                        class="row-side text-right whitespace-normal max-w-[55%] break-words"
                    >
                        <span style="font-weight: 600">{{
                            reward.buyer.name
                        }}</span>
                    </div>
                </div>
            </div>

            <div class="section-heading mt-6">
                <h3>Informasi Pembayaran</h3>
            </div>

            <div class="card list-card mt-3">
                <div class="list-row align-start">
                    <div class="row-icon pt-1">
                        <v-icon icon="mdi-bank-outline" size="16" />
                    </div>
                    <div class="row-main pr-3">
                        <strong>Bank Tujuan</strong>
                        <span>Akun rekening kemitraan perusahaan</span>
                    </div>
                    <div
                        class="row-side text-right whitespace-normal max-w-[55%] break-words"
                    >
                        <span style="font-weight: 600">
                            <template v-if="reward.bank">
                                {{ reward.bank.account_name }}<br />
                                {{ reward.bank.account_number }}
                            </template>
                            <template v-else>-</template>
                        </span>
                    </div>
                </div>
                <div class="list-row align-start" v-if="reward.created_at">
                    <div class="row-icon pt-1">
                        <v-icon icon="mdi-calendar-plus-outline" size="16" />
                    </div>
                    <div class="row-main pr-3">
                        <strong>Tanggal Dibuat</strong>
                        <span>Waktu komisi dicatat</span>
                    </div>
                    <div
                        class="row-side text-right whitespace-normal max-w-[55%] break-words"
                    >
                        <span style="font-weight: 600">{{
                            formatDateTime(reward.created_at)
                        }}</span>
                    </div>
                </div>
                <div class="list-row align-start" v-if="reward.approved_at">
                    <div class="row-icon pt-1">
                        <v-icon icon="mdi-calendar-check-outline" size="16" />
                    </div>
                    <div class="row-main pr-3">
                        <strong>Tanggal Disetujui</strong>
                        <span>Waktu komisi divalidasi</span>
                    </div>
                    <div
                        class="row-side text-right whitespace-normal max-w-[55%] break-words"
                    >
                        <span style="font-weight: 600">{{
                            formatDateTime(reward.approved_at)
                        }}</span>
                    </div>
                </div>
                <div class="list-row align-start" v-if="reward.paid_at">
                    <div class="row-icon pt-1">
                        <v-icon icon="mdi-cash-check" size="16" />
                    </div>
                    <div class="row-main pr-3">
                        <strong>Tanggal Dibayarkan</strong>
                        <span>Waktu komisi ditransfer</span>
                    </div>
                    <div
                        class="row-side text-right whitespace-normal max-w-[55%] break-words"
                    >
                        <span style="font-weight: 600">{{
                            formatDateTime(reward.paid_at)
                        }}</span>
                    </div>
                </div>
                <div class="list-row align-start" v-if="reward.note">
                    <div class="row-icon pt-1">
                        <v-icon icon="mdi-note-text-outline" size="16" />
                    </div>
                    <div class="row-main pr-3">
                        <strong>Catatan Tambahan</strong>
                        <span>Catatan pencairan komisi sharing profit</span>
                    </div>
                    <div
                        class="row-side text-right whitespace-normal max-w-[55%] break-words"
                    >
                        <span style="font-weight: 600">{{ reward.note }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div v-else style="padding: 24px; text-align: center" class="card mt-3">
            <v-icon
                icon="mdi-alert-circle-outline"
                size="32"
                color="orange"
                class="mb-2"
            ></v-icon>
            <p style="margin: 0; color: var(--muted); font-size: 13px">
                Data detail sharing profit tidak ditemukan.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import rewardService from "@/member/services/reward.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import type { SharingProfitItem } from "@/member/types/reward";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { decodeRouteId } from "@/shared/utils/route-id";

const route = useRoute();
const { formatPrice, formatDateTime } = useFormatter();

const reward = ref<SharingProfitItem | null>(null);
const isLoading = ref(true);

const fetchDetail = async () => {
    isLoading.value = true;
    try {
        const id = decodeRouteId((route.params as Record<string, unknown>).id);
        const response = await rewardService.getSharingProfit(id);
        if (response.data) {
            reward.value = response.data;
        }
    } catch (error) {
        console.error("Failed to fetch sharing profit detail:", error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchDetail();
});
</script>
