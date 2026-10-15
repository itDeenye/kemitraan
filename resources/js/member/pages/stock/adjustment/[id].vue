<template>
    <div class="screen-body adjustment-detail-page">
        <div v-if="isLoading" class="adjustment-detail-state">
            <v-progress-circular indeterminate color="primary" size="26" />
            <p>Memuat detail penyesuaian stok...</p>
        </div>

        <template v-else-if="adjustment">
            <section class="adjustment-summary">
                <div class="adjustment-summary__header">
                    <div class="adjustment-summary__icon">
                        <v-icon icon="mdi-package-variant-minus" size="24" />
                    </div>
                    <div class="adjustment-summary__identity">
                        <strong>{{ adjustment.code }}</strong>
                        <span>
                            <v-icon
                                icon="mdi-calendar-clock-outline"
                                size="14"
                            />
                            {{ formatDateTime(adjustment.happened_at) }}
                        </span>
                    </div>
                    <span class="adjustment-summary__type">
                        Pengurangan Stok
                    </span>
                </div>
            </section>

            <section class="adjustment-section">
                <h2 class="adjustment-section__title">
                    <v-icon icon="mdi-text-box-outline" size="19" />
                    Alasan Pengurangan Stok
                </h2>
                <div class="adjustment-reason-card">
                    {{
                        adjustment.note || "Tidak ada alasan yang dicantumkan."
                    }}
                </div>
            </section>

            <section class="adjustment-section">
                <h2 class="adjustment-section__title">
                    <v-icon icon="mdi-package-variant-closed" size="19" />
                    Rincian Produk
                </h2>

                <div class="adjustment-product-list">
                    <article
                        v-for="item in adjustment.details || []"
                        :key="item.id"
                        class="adjustment-product-card"
                    >
                        <div class="adjustment-product-card__top">
                            <div class="adjustment-product-card__icon">
                                <v-icon icon="mdi-package-variant" size="21" />
                            </div>
                            <div class="adjustment-product-card__identity">
                                <strong>{{
                                    item.product.name || "Produk"
                                }}</strong>
                                <span>{{ item.product.code || "-" }}</span>
                            </div>
                            <strong class="adjustment-product-card__quantity">
                                −{{ item.quantity }}
                                {{ item.product.unit || "pcs" }}
                            </strong>
                        </div>

                        <div class="adjustment-product-card__price">
                            <span>Harga saat penyesuaian</span>
                            <strong
                                >Rp{{ formatPrice(item.unit_price) }}</strong
                            >
                        </div>
                    </article>
                </div>
            </section>
        </template>

        <div v-else class="card adjustment-detail-state">
            <v-icon icon="mdi-file-search-outline" size="36" />
            <strong>Detail penyesuaian tidak ditemukan</strong>
            <p>Data mungkin sudah tidak tersedia atau tidak dapat diakses.</p>
            <v-btn
                color="primary"
                variant="outlined"
                class="text-none mt-2"
                @click="router.replace('/member/stock/adjustment')"
            >
                Kembali ke Riwayat
            </v-btn>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import inventoryService from "@/member/services/inventory.service";
import type { StockAdjustmentItem } from "@/member/types/inventory";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { decodeRouteId } from "@/shared/utils/route-id";

const route = useRoute();
const router = useRouter();
const snackbar = useSnackbarStore();
const { formatDateTime, formatPrice } = useFormatter();

const adjustment = ref<StockAdjustmentItem | null>(null);
const isLoading = ref(true);

async function fetchAdjustmentDetail() {
    const params = route.params as Record<string, string | string[]>;
    const rawId = Array.isArray(params.id) ? params.id[0] : params.id;
    const routeId = decodeRouteId(rawId);

    if (!routeId || Number.isNaN(Number(routeId))) {
        await router.replace("/member/stock/adjustment");
        return;
    }

    isLoading.value = true;
    try {
        adjustment.value = await inventoryService.getAdjustment(
            Number(routeId),
        );
    } catch (error: any) {
        adjustment.value = null;
        snackbar.showMessage(
            error.response?.data?.message ||
                "Gagal memuat detail penyesuaian stok.",
            "error",
        );
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    void fetchAdjustmentDetail();
});
</script>

<style scoped>
.adjustment-detail-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding-bottom: 28px;
}

.adjustment-detail-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 180px;
    padding: 28px 20px;
    color: var(--muted);
    text-align: center;
}

.adjustment-detail-state strong {
    margin-top: 10px;
    color: var(--ink);
    font-size: 14px;
}

.adjustment-detail-state p {
    margin: 10px 0 0;
    font-size: 13px;
}

.adjustment-summary {
    border: 1px solid #eadde0;
    border-radius: 14px;
    background: var(--surface);
}

.adjustment-summary__header {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 16px;
}

.adjustment-summary__icon {
    display: grid;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    place-items: center;
    border-radius: 11px;
    color: var(--wine);
    background: var(--rose-2);
}

.adjustment-summary__identity {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    gap: 2px;
}

.adjustment-summary__identity span {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--muted);
    font-size: 11px;
}

.adjustment-summary__identity strong {
    overflow-wrap: anywhere;
    color: var(--ink);
    font-size: 14px;
}

.adjustment-summary__type {
    flex: 0 0 auto;
    padding: 5px 8px;
    border-radius: 999px;
    color: var(--wine);
    background: var(--rose-2);
    font-size: 10px;
    font-weight: 700;
}

.adjustment-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.adjustment-section__title {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 0;
    color: var(--ink);
    font-size: 14px;
    font-weight: 700;
}

.adjustment-section__title .v-icon {
    color: var(--wine);
}

.adjustment-reason-card {
    padding: 14px 15px;
    border: 1px solid #bfdbfe;
    border-radius: 12px;
    color: #1e3a8a;
    background: #eff6ff;
    font-size: 12px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.adjustment-product-list {
    display: grid;
    gap: 10px;
}

.adjustment-product-card {
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: var(--surface);
}

.adjustment-product-card__top {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px;
}

.adjustment-product-card__icon {
    display: grid;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    place-items: center;
    border-radius: 10px;
    color: var(--wine);
    background: var(--rose);
}

.adjustment-product-card__identity {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    gap: 3px;
}

.adjustment-product-card__identity strong {
    color: var(--ink);
    font-size: 12px;
    line-height: 1.4;
}

.adjustment-product-card__identity span {
    color: var(--muted);
    font-size: 10px;
    overflow-wrap: anywhere;
}

.adjustment-product-card__quantity {
    flex: 0 0 auto;
    color: var(--orange);
    font-size: 13px;
    font-variant-numeric: tabular-nums;
}

.adjustment-product-card__price {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 10px 13px;
    border-top: 1px solid var(--line);
    background: #fffbfc;
}

.adjustment-product-card__price span {
    color: var(--muted);
    font-size: 10px;
}

.adjustment-product-card__price strong {
    color: var(--ink);
    font-size: 11px;
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
}

@media (max-width: 420px) {
    .adjustment-summary__header {
        align-items: flex-start;
    }

    .adjustment-summary__type {
        max-width: 90px;
        text-align: center;
    }
}
</style>
