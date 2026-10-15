<template>
    <div class="screen-body pb-16">
        <div v-if="isLoading" class="p-6 text-center">
            <v-progress-circular
                indeterminate
                color="primary"
                size="24"
            ></v-progress-circular>
            <p class="m-0 mt-3 text-[var(--muted)] text-[13px]">
                Memuat detail pesanan...
            </p>
        </div>

        <div v-else-if="order" class="pb-6">
            <div
                class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3"
            >
                <div class="flex-1 min-w-0">
                    <h2
                        class="text-base font-bold m-0 mt-1 break-words text-[var(--ink)]"
                    >
                        {{ order.code }}
                    </h2>
                    <div
                        class="text-[12px] text-[var(--muted)] mt-1 flex items-center gap-1.5"
                    >
                        <v-icon icon="mdi-calendar-clock" size="14" />
                        <span v-if="order.ordered_at">{{
                            formatDateTime(order.ordered_at)
                        }}</span>
                    </div>
                </div>
                <div
                    class="shrink-0 flex flex-col items-start sm:items-end gap-2"
                >
                    <BaseBadge
                        type="transaction_status"
                        :value="order.status?.code"
                        inline
                        chip-class="font-semibold"
                    />
                </div>
            </div>

            <PreorderOriginCard
                v-if="order.is_preorder"
                :preorder="order.preorder"
            />

            <OrderPartyInfo
                title="Data Pembeli"
                icon="mdi-account-outline"
                :party="order.buyer || order.customer"
                :phone="order.customer?.whatsapp || order.customer?.phone"
            />

            <OrderSellerInfo :seller="order.seller" />

            <OrderShippingInfo
                :method="order.shipping?.method || order.shipping_method"
                :shipping="order.shipping"
                :origin="order.seller?.origin"
                :destination="order.buyer?.destination"
                :pickup-location="pickupLocation"
                :transit="isUplineTransit"
            >
                <v-btn
                    v-if="order.actions?.can_confirm_arrival"
                    color="primary"
                    variant="flat"
                    size="small"
                    :loading="confirmingArrival"
                    @click="confirmArrival"
                >
                    Barang Sudah Tiba
                </v-btn>
                <template #delivery-note-action>
                    <v-btn
                        size="x-small"
                        variant="text"
                        color="primary"
                        class="!min-w-0 !px-1"
                        @click="copyToClipboard(order.shipping.delivery_note_number, 'Nomor surat jalan')"
                    >
                        <v-icon icon="mdi-content-copy" size="small" />
                    </v-btn>
                </template>
                <template #tracking-number-action>
                    <v-btn
                        size="x-small"
                        variant="text"
                        color="primary"
                        class="!min-w-0 !px-1"
                        @click="copyToClipboard(order.shipping.tracking_number, 'Nomor resi')"
                    >
                        <v-icon icon="mdi-content-copy" size="small" />
                    </v-btn>
                </template>
            </OrderShippingInfo>

            <div class="card" style="margin-bottom: 16px">
                <div
                    class="border-b border-[var(--line)]"
                    style="padding: 16px 20px"
                >
                    <div class="flex justify-between items-center">
                        <h3
                            class="flex items-center gap-2 m-0"
                            style="font-size: 14px"
                        >
                            <v-icon
                                icon="mdi-package-variant-closed"
                                size="18"
                            />
                            Daftar Produk
                        </h3>
                        <span
                            class="text-xs font-semibold text-[var(--primary)]"
                        >
                            {{ order.items?.length || 0 }} produk
                        </span>
                    </div>
                </div>

                <div style="padding: 12px 20px 16px 20px">
                    <div
                        class="flex gap-3 border-b border-[var(--line)] last:border-b-0"
                        style="padding-top: 12px; padding-bottom: 12px"
                        v-for="(item, index) in order.items"
                        :key="item.id"
                        :class="
                            index !== order.items.length - 1
                                ? 'border-dashed'
                                : ''
                        "
                    >
                        <div
                            class="w-[50px] h-[50px] rounded-lg bg-[#f5f5f5] flex items-center justify-center shrink-0 overflow-hidden"
                            style="border: 1px solid #eaeaea"
                        >
                            <v-img
                                v-if="item.product?.image"
                                :src="item.product.image"
                                cover
                            >
                                <template #error>
                                    <img
                                        src="/images/default-product.svg"
                                        alt="Default Product"
                                        style="
                                            width: 100%;
                                            height: 100%;
                                            object-fit: cover;
                                        "
                                    />
                                </template>
                            </v-img>
                            <img
                                v-else
                                src="/images/default-product.svg"
                                alt="Default Product"
                                style="
                                    width: 100%;
                                    height: 100%;
                                    object-fit: cover;
                                "
                            />
                        </div>
                        <div
                            class="flex-1 flex flex-col justify-center min-w-0 pr-2"
                        >
                            <strong
                                v-if="item.product?.code"
                                class="text-[13px] text-[var(--text)] block"
                            >
                                {{ item.product.code }}
                            </strong>
                            <div
                                class="text-[13px] text-[var(--text)] line-clamp-2 break-words"
                            >
                                {{ item.product?.name }}
                                <span
                                    v-if="item.preorder_quantity > 0"
                                    class="text-orange-500 font-bold ml-1"
                                    >(PO {{ item.preorder_quantity }})</span
                                >
                            </div>
                            <div class="text-[11px] text-[var(--muted)] mt-0.5">
                                BPOM: {{ item.product.bpom_number || "-" }}
                            </div>
                            <div class="text-[11px] text-[var(--muted)] mt-0.5">
                                Poin Bulanan: {{ formatPrice(item.monthly_points ?? 0) }} ·
                                Poin Tahunan: {{ formatPrice(item.annual_points ?? 0) }}
                            </div>
                            <span class="text-[12px]"
                                >{{ item.quantity }} x Rp{{
                                    formatPrice(item.net_price || item.price)
                                }}</span
                            >
                        </div>
                        <div class="flex items-center text-[13px]">
                            <strong>Rp{{ formatPrice(item.subtotal) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 16px">
                <div style="padding: 16px 20px">
                    <div class="flex justify-between items-center mb-4">
                        <h3
                            class="flex items-center gap-2 m-0"
                            style="font-size: 14px"
                        >
                            <v-icon icon="mdi-receipt-text-outline" size="18" />
                            Rincian Pembayaran
                        </h3>
                        <span
                            v-if="order.payment_method"
                            class="text-[11px] font-semibold text-[var(--primary)] px-2 py-1 rounded capitalize"
                            style="
                                background: rgba(var(--v-theme-primary), 0.1);
                            "
                        >
                            {{
                                order.payment_method === "transfer"
                                    ? "Transfer Bank"
                                    : order.payment_method.replace("_", " ")
                            }}
                        </span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="soft-label">Total Harga Produk</span>
                        <span class="text-[13px]"
                            >Rp{{
                                formatPrice(order.summary?.product_total || 0)
                            }}</span
                        >
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="soft-label">Total Poin Bulanan</span>
                        <span class="text-[13px]">{{ formatPrice(order.summary?.total_monthly_points ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="soft-label">Total Poin Tahunan</span>
                        <span class="text-[13px]">{{ formatPrice(order.summary?.total_annual_points ?? 0) }}</span>
                    </div>
                    <div
                        class="flex justify-between mb-2"
                        v-if="
                            order.shipping &&
                            order.shipping?.method !== 'pickup'
                        "
                    >
                        <span class="soft-label">Ongkos Kirim</span>
                        <span class="text-[13px]"
                            >Rp{{
                                formatPrice(order.summary?.shipping_cost || 0)
                            }}</span
                        >
                    </div>
                    <div
                        class="flex justify-between mb-2"
                        v-if="
                            order.shipping &&
                            order.shipping?.method !== 'pickup'
                        "
                    >
                        <span class="soft-label">Asuransi Pengiriman</span>
                        <span class="text-[13px]"
                            >Rp{{
                                formatPrice(
                                    order.summary?.shipping_cost_insurance || 0,
                                )
                            }}</span
                        >
                    </div>
                    <div
                        class="flex justify-between mb-2"
                        v-if="(order.summary?.total_discount || 0) > 0"
                    >
                        <span class="soft-label">Diskon</span>
                        <span style="color: var(--danger); font-size: 13px"
                            >- Rp{{
                                formatPrice(order.summary?.total_discount || 0)
                            }}</span
                        >
                    </div>
                    <div
                        class="flex justify-between mb-2"
                        v-if="(order.summary?.voucher_discount || 0) > 0"
                    >
                        <span class="soft-label">Potongan Voucher</span>
                        <span style="color: var(--danger); font-size: 13px"
                            >- Rp{{
                                formatPrice(
                                    order.summary?.voucher_discount || 0,
                                )
                            }}</span
                        >
                    </div>
                    <div
                        class="flex justify-between mb-2"
                        v-if="(order.summary?.payment_charge || 0) > 0"
                    >
                        <span class="soft-label">Biaya Layanan</span>
                        <span class="text-[13px]"
                            >Rp{{
                                formatPrice(order.summary?.payment_charge || 0)
                            }}</span
                        >
                    </div>

                    <div
                        v-if="order.payment && order.payment.bank"
                        class="mt-4 pt-4 border-t border-dashed border-[var(--line)]"
                    >
                        <div
                            class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1 sm:gap-3 w-full"
                        >
                            <span class="soft-label shrink-0 mt-[2px]"
                                >Transfer ke Bank</span
                            >
                            <div class="text-right min-w-0">
                                <strong class="text-[13px]">{{
                                    order.payment.bank.bank_name ||
                                    order.payment.bank.name ||
                                    "Bank"
                                }}</strong>
                                <div
                                    class="flex items-center gap-1 justify-end mt-1 text-[13px]"
                                    style="
                                        font-weight: 700;
                                        color: var(--primary);
                                    "
                                >
                                    {{ order.payment.bank.account_number }}
                                    <v-icon
                                        icon="mdi-content-copy"
                                        size="16"
                                        class="cursor-pointer active:opacity-50"
                                        @click.stop="
                                            copyToClipboard(
                                                order.payment.bank
                                                    .account_number,
                                                'Nomor rekening',
                                            )
                                        "
                                    />
                                </div>
                                <span
                                    class="text-[12px] text-[var(--muted)] font-normal block"
                                    >a.n.
                                    {{ order.payment.bank.account_name }}</span
                                >
                            </div>
                        </div>
                        <div
                            v-if="order.payment?.receipt_url"
                            class="payment-receipt-card mt-4"
                        >
                            <div class="payment-receipt-header">
                                <span>Bukti Pembayaran</span>
                                <BaseBadge
                                    type="payment_status"
                                    :value="order.payment?.status?.code"
                                    inline
                                />
                            </div>
                            <div
                                class="d-flex flex-column align-center gap-3 mt-2 text-center"
                            >
                                <div
                                    style="
                                        width: 200px;
                                        max-width: 100%;
                                        flex-shrink: 0;
                                    "
                                >
                                    <button
                                        type="button"
                                        class="payment-receipt-thumbnail m-0 w-100"
                                        @click="
                                            openImage(order.payment.receipt_url)
                                        "
                                    >
                                        <v-img
                                            :src="order.payment.receipt_url"
                                            width="100%"
                                            height="100%"
                                            class="payment-receipt-image"
                                            contain
                                        >
                                            <template v-slot:placeholder>
                                                <div
                                                    class="d-flex align-center justify-center fill-height"
                                                >
                                                    <v-progress-circular
                                                        color="primary"
                                                        indeterminate
                                                        size="22"
                                                        width="2"
                                                    ></v-progress-circular>
                                                </div>
                                            </template>
                                        </v-img>
                                    </button>
                                    <span
                                        class="text-[11px] text-[var(--muted)] mt-2 block leading-tight"
                                        >Ketuk gambar untuk memperbesar</span
                                    >
                                </div>
                                <div
                                    class="text-[12px] text-[var(--muted)] px-2"
                                >
                                    Bukti pembayaran yang dilampirkan oleh
                                    pelanggan.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="border-t border-dashed border-[var(--line)] bg-[#fafafa] rounded-b-xl"
                        style="padding: 16px 20px"
                    >
                        <div class="flex justify-between items-center">
                            <strong class="text-sm">Total Pembayaran</strong>
                            <strong class="text-base text-[var(--primary)]"
                                >Rp{{
                                    formatPrice(order.summary?.grand_total || 0)
                                }}</strong
                            >
                        </div>
                    </div>
                </div>
            </div>
            <v-dialog v-model="imageDialog" max-width="90vw" max-height="90vh">
                <v-card class="bg-transparent" elevation="0">
                    <v-img
                        :src="selectedImage"
                        max-height="90vh"
                        contain
                    ></v-img>
                    <v-btn
                        icon="mdi-close"
                        color="white"
                        variant="text"
                        class="position-absolute"
                        style="top: 10px; right: 10px; z-index: 2"
                        @click="imageDialog = false"
                    ></v-btn>
                </v-card>
            </v-dialog>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import saleService from "@/member/services/sale.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import OrderSellerInfo from "@/member/components/transactions/OrderSellerInfo.vue";
import OrderPartyInfo from "@/member/components/transactions/OrderPartyInfo.vue";
import OrderShippingInfo from "@/member/components/transactions/OrderShippingInfo.vue";
import PreorderOriginCard from "@/member/components/transactions/PreorderOriginCard.vue";
import { decodeRouteId } from "@/shared/utils/route-id";

import type { SaleOrderDetail } from "@/member/types/sale";

const route = useRoute();
const router = useRouter();
const snackbar = useSnackbarStore();
const { formatPrice, formatDateTime } = useFormatter();

const isLoading = ref(true);
const order = ref<SaleOrderDetail | null>(null);
const isUplineTransit = computed(
    () => order.value?.preorder_fulfillment?.mode === "upline_transit",
);
const pickupLocation = computed(() =>
    isUplineTransit.value
        ? order.value?.buyer?.destination
        : order.value?.seller?.origin,
);
const isPaymentDialogOpen = ref(false);
const imageDialog = ref(false);
const selectedImage = ref("");
const confirmingArrival = ref(false);

const fetchDetail = async () => {
    isLoading.value = true;
    try {
        const response = await saleService.getOrderDetail(
            decodeRouteId((route.params as Record<string, unknown>).id),
        );
        order.value = response.data;
    } catch (error: any) {
        console.error("Failed to fetch order detail:", error);
        snackbar.showMessage(
            error.response?.data?.message ||
                "Gagal memuat detail pesanan. Silakan coba lagi.",
            "error",
        );
        if (error.response?.status === 404) {
            router.replace("/member/transactions/sales");
        }
    } finally {
        isLoading.value = false;
    }
};

const copyToClipboard = (text: string, subject: string = "Teks") => {
    if (!text) return;
    navigator.clipboard
        .writeText(text)
        .then(() => {
            snackbar.showMessage(`${subject} berhasil disalin`, "success");
        })
        .catch(() => {
            snackbar.showMessage("Gagal menyalin", "error");
        });
};

const openImage = (url: string) => {
    selectedImage.value = url;
    imageDialog.value = true;
};

const confirmArrival = async () => {
    if (!order.value) return;
    confirmingArrival.value = true;
    try {
        const response = await saleService.confirmArrival(order.value.id);
        snackbar.showMessage(response.message, "success");
        order.value = response.data;
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Gagal mengonfirmasi kedatangan barang.",
            "error",
        );
    } finally {
        confirmingArrival.value = false;
    }
};

onMounted(() => {
    fetchDetail();
});
</script>
