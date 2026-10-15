<template>
    <div class="screen-body" v-if="order">
        <div class="card invoice-card mt-3 mb-4">
            <div class="invoice-head">
                <div class="invoice-brand">
                    <strong>VERIFIKASI PEMBAYARAN</strong>
                    <span v-if="order.ordered_at">{{
                        formatDateTime(order.ordered_at)
                    }}</span>
                </div>
                <BaseBadge
                    type="transaction_status"
                    :value="order.status?.code"
                    inline
                    chip-class="font-semibold"
                />
            </div>

            <div class="invoice-meta">
                <div class="invoice-meta-row">
                    <span>Kode Transaksi</span>
                    <strong>{{ order.code }}</strong>
                </div>
            </div>
            <div class="invoice-divider"></div>

            <OrderPartyInfo
                title="Data Pembeli"
                icon="mdi-account-outline"
                :party="order.buyer || order.customer"
                :phone="order.customer?.whatsapp || order.customer?.phone"
                embedded
            />
            <div class="invoice-divider"></div>
            <OrderSellerInfo :seller="order.seller" embedded />
            <div class="invoice-divider"></div>
            <OrderShippingInfo
                :method="order.shipping?.method || order.shipping_method"
                :shipping="order.shipping"
                :origin="order.seller?.origin"
                :destination="order.buyer?.destination"
                :pickup-location="pickupLocation"
                :transit="isUplineTransit"
                embedded
            />
            <div class="invoice-divider"></div>
            <div class="invoice-total">
                <span>Total Pembayaran</span>
                <strong>Rp{{ formatPrice(order.summary?.grand_total || 0) }}</strong>
            </div>
        </div>

        <div class="card mb-4 detail-card">
            <div class="detail-section">
                <h3 class="detail-title">
                    <v-icon icon="mdi-package-variant-closed" size="18" />
                    Daftar Produk
                </h3>
                <div
                    v-for="item in order.items || []"
                    :key="item.id || item.product?.id"
                    class="product-row"
                >
                    <v-img
                        v-if="item.product?.image"
                        :src="item.product.image"
                        width="48"
                        height="48"
                        cover
                        class="product-image"
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
                    <div v-else class="product-image product-placeholder">
                        <img
                            src="/images/default-product.svg"
                            alt="Default Product"
                            style="width: 100%; height: 100%; object-fit: cover"
                        />
                    </div>
                    <div class="product-info min-w-0">
                        <strong class="text-[13px] text-[var(--text)] block">{{
                            item.product?.code || "Produk"
                        }}</strong>
                        <div
                            class="text-[13px] text-[var(--text)] line-clamp-2"
                        >
                            {{ item.product?.name || "Produk" }}
                        </div>
                        <span class="text-[11px] text-[var(--muted)] mt-0.5">
                            BPOM: {{ item.product?.bpom_number || "-" }}
                        </span>
                        <span class="text-[11px] text-[var(--muted)] mt-0.5">
                            Poin Bulanan: {{ formatPrice(item.monthly_points ?? 0) }} ·
                            Poin Tahunan: {{ formatPrice(item.annual_points ?? 0) }}
                        </span>
                        <span>
                            {{ item.quantity }} × Rp{{
                                formatPrice(item.net_price || item.price || 0)
                            }}
                        </span>
                    </div>
                    <strong class="product-subtotal">
                        Rp{{ formatPrice(item.subtotal || 0) }}
                    </strong>
                </div>
            </div>
        </div>

        <div class="card mb-4 detail-card">
            <div class="detail-section">
                <h3 class="detail-title">
                    <v-icon icon="mdi-receipt-text-outline" size="18" />
                    Rincian Pembayaran
                </h3>
                <div class="detail-row">
                    <span>Total Harga Produk</span>
                    <strong
                        >Rp{{
                            formatPrice(order.summary?.product_total || 0)
                        }}</strong
                    >
                </div>
                <div class="detail-row">
                    <span>Total Poin Bulanan</span>
                    <strong>{{ formatPrice(order.summary?.total_monthly_points ?? 0) }}</strong>
                </div>
                <div class="detail-row">
                    <span>Total Poin Tahunan</span>
                    <strong>{{ formatPrice(order.summary?.total_annual_points ?? 0) }}</strong>
                </div>
                <div
                    v-if="(order.summary?.total_discount || 0) > 0"
                    class="detail-row discount-row"
                >
                    <span>Diskon Produk</span>
                    <strong
                        >- Rp{{
                            formatPrice(order.summary.total_discount)
                        }}</strong
                    >
                </div>
                <div
                    v-if="(order.summary?.voucher_discount || 0) > 0"
                    class="detail-row discount-row"
                >
                    <span>Potongan Voucher</span>
                    <strong
                        >- Rp{{
                            formatPrice(order.summary.voucher_discount)
                        }}</strong
                    >
                </div>
                <div
                    v-if="order.shipping?.method !== 'pickup'"
                    class="detail-row"
                >
                    <span>Ongkos Kirim</span>
                    <strong
                        >Rp{{
                            formatPrice(order.summary?.shipping_cost || 0)
                        }}</strong
                    >
                </div>
                <div
                    v-if="
                        order.shipping?.method !== 'pickup' &&
                        (order.summary?.shipping_cost_insurance || 0) > 0
                    "
                    class="detail-row"
                >
                    <span>Asuransi Pengiriman</span>
                    <strong
                        >Rp{{
                            formatPrice(order.summary.shipping_cost_insurance)
                        }}</strong
                    >
                </div>
                <div
                    v-if="(order.summary?.payment_charge || 0) > 0"
                    class="detail-row"
                >
                    <span>Biaya Layanan</span>
                    <strong
                        >Rp{{
                            formatPrice(order.summary.payment_charge)
                        }}</strong
                    >
                </div>
                <div class="detail-row grand-total-row">
                    <span>Total Pembayaran</span>
                    <strong
                        >Rp{{
                            formatPrice(order.summary?.grand_total || 0)
                        }}</strong
                    >
                </div>
            </div>
        </div>

        <div class="card mb-4" style="padding: 16px">
            <p class="text-[13px] text-[var(--muted)] mb-4">
                Verifikasi pembayaran untuk pesanan ini. Pastikan Anda telah
                menerima dana sebesar
                <strong
                    >Rp{{
                        formatPrice(order.summary?.grand_total || 0)
                    }}</strong
                >
                di rekening Anda.
            </p>

            <div class="flex flex-col gap-4">
                <div v-if="isCustomer">
                    <div class="mb-1 text-[13px] text-[var(--text)]">
                        Bukti Transfer (Opsional)
                    </div>
                    <div
                        class="upload-box"
                        :class="{ 'has-image': !!receiptUrl }"
                        @click="triggerFileInput"
                    >
                        <input
                            type="file"
                            ref="fileInputRef"
                            class="d-none"
                            accept="image/*"
                            @change="onFileChange"
                        />
                        <template v-if="receiptUrl">
                            <img :src="receiptUrl" class="upload-preview" />
                            <div class="upload-overlay">
                                <v-icon
                                    icon="mdi-camera-retake"
                                    color="white"
                                    size="24"
                                />
                                <span class="text-white text-[12px] mt-1"
                                    >Ganti Foto</span
                                >
                            </div>
                        </template>
                        <template v-else>
                            <v-icon
                                icon="mdi-cloud-upload-outline"
                                size="28"
                                color="var(--primary)"
                                class="mb-2"
                            />
                            <span
                                class="text-[13px] font-weight-medium text-[var(--primary)]"
                                >Unggah Bukti Transfer</span
                            >
                            <span class="text-[11px] text-[var(--muted)]"
                                >Format: JPG, PNG, Max 2MB</span
                            >
                        </template>
                    </div>
                </div>
                <div v-else>
                    <div class="mb-1 text-[13px] text-[var(--text)]">
                        Bukti Transfer
                    </div>
                    <div
                        v-if="order.payment?.receipt_url"
                        class="d-flex flex-column align-center gap-3 mt-4 text-center"
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
                                @click="openImage(order.payment.receipt_url)"
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
                    </div>
                    <div
                        v-else
                        class="text-[13px] text-[var(--muted)] italic p-4 text-center border border-dashed rounded-lg bg-gray-50"
                    >
                        Pembeli belum/tidak menyertakan bukti transfer.
                    </div>
                </div>
            </div>
        </div>

        <div class="payment-actions mt-2">
            <button
                type="button"
                class="reject-payment-button"
                :disabled="submitting"
                @click="confirmSubmit('rejected')"
            >
                <v-progress-circular
                    v-if="submittingAction === 'rejected'"
                    indeterminate
                    size="18"
                    width="2"
                    color="currentColor"
                    class="mr-2"
                />
                Tolak Pembayaran
            </button>
            <button
                type="button"
                class="approve-payment-button"
                :disabled="submitting"
                @click="confirmSubmit('approved')"
            >
                <v-progress-circular
                    v-if="submittingAction === 'approved'"
                    indeterminate
                    size="18"
                    width="2"
                    color="white"
                    class="mr-2"
                />
                Setujui Pembayaran
            </button>
        </div>

        <v-dialog v-model="confirmDialog" max-width="500" persistent>
            <v-card class="rounded-xl">
                <v-card-title class="payment-confirmation__header">
                    <div class="d-flex align-center ga-3 min-w-0">
                        <div
                            class="payment-confirmation__icon"
                            :class="
                                confirmAction === 'approved'
                                    ? 'payment-confirmation__icon--approve'
                                    : 'payment-confirmation__icon--reject'
                            "
                        >
                            <v-icon
                                :icon="
                                    confirmAction === 'approved'
                                        ? 'mdi-check'
                                        : 'mdi-close'
                                "
                                size="22"
                            />
                        </div>
                        <div class="min-w-0">
                            <div class="text-subtitle-1 font-weight-bold">
                                {{
                                    confirmAction === "approved"
                                        ? "Setujui Pembayaran"
                                        : "Tolak Pembayaran"
                                }}
                            </div>
                            <div
                                class="text-caption text-medium-emphasis text-truncate"
                            >
                                {{ order?.code || "Pembayaran Pesanan" }}
                            </div>
                        </div>
                    </div>
                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        density="comfortable"
                        :disabled="submitting"
                        aria-label="Tutup"
                        @click="closeConfirmDialog"
                    />
                </v-card-title>

                <v-divider />

                <v-card-text class="pa-5 pa-sm-6">
                    <v-alert
                        :color="
                            confirmAction === 'approved' ? 'success' : 'error'
                        "
                        variant="tonal"
                        density="compact"
                        class="mb-5"
                    >
                        {{
                            confirmAction === "approved"
                                ? "Pastikan pembayaran telah diterima dan nominalnya sesuai sebelum menyetujui pembayaran."
                                : "Pembayaran akan ditolak. Sertakan alasan agar pembeli mengetahui apa penyebab pembayaran ditolak."
                        }}
                    </v-alert>

                    <v-textarea
                        v-model="form.note"
                        :label="
                            confirmAction === 'rejected'
                                ? 'Alasan Penolakan *'
                                : 'Catatan Persetujuan (Opsional)'
                        "
                        :placeholder="
                            confirmAction === 'rejected'
                                ? 'Contoh: Nominal transfer belum sesuai'
                                : 'Contoh: Pembayaran sudah diterima'
                        "
                        rows="3"
                        variant="outlined"
                        density="comfortable"
                        auto-grow
                        maxlength="1000"
                        counter="1000"
                        :error-messages="confirmNoteError"
                        @update:model-value="confirmNoteError = ''"
                    />
                </v-card-text>

                <v-divider />

                <v-card-actions class="pa-4 pa-sm-5">
                    <v-spacer />
                    <v-btn
                        variant="outlined"
                        color="medium-emphasis"
                        class="text-none"
                        :disabled="submitting"
                        @click="closeConfirmDialog"
                    >
                        Batal
                    </v-btn>
                    <v-btn
                        :color="
                            confirmAction === 'approved' ? 'primary' : 'error'
                        "
                        variant="flat"
                        class="text-none px-5"
                        :loading="submitting"
                        @click="executeSubmit"
                    >
                        {{
                            confirmAction === "approved"
                                ? "Ya, Setujui"
                                : "Ya, Tolak"
                        }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="imageDialog" max-width="90vw" max-height="90vh">
            <v-card class="bg-transparent" elevation="0">
                <v-img :src="selectedImage" max-height="90vh" contain></v-img>
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
    <div v-else-if="isLoading" style="padding: 40px; text-align: center">
        <v-progress-circular
            indeterminate
            color="primary"
        ></v-progress-circular>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import saleService from "@/member/services/sale.service";
import purchaseService from "@/member/services/purchase.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import OrderSellerInfo from "@/member/components/transactions/OrderSellerInfo.vue";
import OrderPartyInfo from "@/member/components/transactions/OrderPartyInfo.vue";
import OrderShippingInfo from "@/member/components/transactions/OrderShippingInfo.vue";
import { decodeRouteId, encodeRouteId } from "@/shared/utils/route-id";

const router = useRouter();
const route = useRoute();
const { formatPrice, formatDateTime } = useFormatter();
const snackbar = useSnackbarStore();

const orderId = decodeRouteId(route.query.id);
const order = ref<any>(null);
const isUplineTransit = computed(
    () => order.value?.preorder_fulfillment?.mode === "upline_transit",
);
const pickupLocation = computed(() =>
    isUplineTransit.value
        ? order.value?.buyer?.destination
        : order.value?.seller?.origin,
);
const isLoading = ref(true);
const submitting = ref(false);
const submittingAction = ref<"approved" | "rejected" | null>(null);

const confirmDialog = ref(false);
const confirmAction = ref<"approved" | "rejected" | null>(null);
const confirmNoteError = ref("");

const imageDialog = ref(false);
const selectedImage = ref("");

const isCustomer = computed(() => order.value?.buyer?.type === "customer");
const form = reactive({
    note: "",
});

const nextPreorderPurchaseId = (updatedOrder: any): number | null => {
    if (!updatedOrder?.is_preorder || !updatedOrder?.preorder?.chain) {
        return null;
    }

    const chain = updatedOrder.preorder.chain as any[];
    const currentIndex = chain.findIndex(
        (step) => Number(step.transaction?.id) === Number(orderId),
    );
    if (currentIndex < 0) {
        return null;
    }

    const nextTransaction = chain[currentIndex + 1]?.transaction;

    return Number(nextTransaction?.id) > 0 ? Number(nextTransaction.id) : null;
};

const nextPreorderPurchaseStatus = async (
    updatedOrder: any,
): Promise<{
    id: number;
    canPay: boolean;
    waitingScreening: boolean;
} | null> => {
    const nextOrderId = nextPreorderPurchaseId(updatedOrder);
    if (!nextOrderId) return null;

    try {
        const response = await purchaseService.getOrderDetail(nextOrderId);

        if (!response.success || !response.data) return null;

        return {
            id: nextOrderId,
            canPay: !!response.data.actions?.can_upload_payment,
            waitingScreening:
                !!response.data.actions?.waiting_for_stock_screening,
        };
    } catch (error) {
        console.error("Failed to check next PO status", error);
        return null;
    }
};

const receiptFile = ref<File | null>(null);
const receiptUrl = ref("");
const fileInputRef = ref<HTMLInputElement | null>(null);

const triggerFileInput = () => {
    fileInputRef.value?.click();
};

const onFileChange = (e: any) => {
    if (e.target.files && e.target.files[0]) {
        receiptFile.value = e.target.files[0];
        receiptUrl.value = URL.createObjectURL(e.target.files[0]);
    }
};

const fetchOrder = async () => {
    if (!orderId) {
        router.replace("/member/transactions/sales");
        return;
    }
    isLoading.value = true;
    try {
        const response = await saleService.getOrderDetail(orderId);
        if (response.success && response.data) {
            order.value = response.data;
        } else {
            snackbar.showMessage("Gagal memuat pesanan", "error");
            router.replace("/member/transactions/sales");
        }
    } catch (error) {
        console.error("Failed to fetch order", error);
        snackbar.showMessage("Gagal memuat pesanan", "error");
        router.replace("/member/transactions/sales");
    } finally {
        isLoading.value = false;
    }
};

const confirmSubmit = (status: "approved" | "rejected") => {
    confirmAction.value = status;
    form.note = "";
    confirmNoteError.value = "";
    confirmDialog.value = true;
};

const closeConfirmDialog = () => {
    if (submitting.value) {
        return;
    }

    confirmDialog.value = false;
};

const executeSubmit = async () => {
    const status = confirmAction.value;
    if (!status) return;

    if (status === "rejected" && !form.note.trim()) {
        confirmNoteError.value = "Alasan penolakan wajib diisi.";
        return;
    }

    submitting.value = true;
    submittingAction.value = status;
    confirmNoteError.value = "";
    try {
        if (status === "approved") {
            const payload: any = {
                note: form.note,
            };

            if (isCustomer.value) {
                payload.receipt_url =
                    receiptUrl.value ||
                    "/storage/media/development/dny-development.png";
            } else {
                payload.receipt_url =
                    order.value?.payment?.receipt_url ||
                    "/storage/media/development/dny-development.png";
            }

            const res = await saleService.approvePayment(orderId, payload);
            if (res.success) {
                confirmDialog.value = false;
                snackbar.showMessage(res.message, "success");
                if (res.data.actions?.can_ship) {
                    router.replace(
                        `/member/transactions/sales/shipping?id=${encodeRouteId(orderId)}`,
                    );
                    return;
                }

                const nextPO = await nextPreorderPurchaseStatus(res.data);
                if (nextPO) {
                    if (nextPO.canPay) {
                        router.replace(
                            `/member/transactions/orders/payment?id=${encodeRouteId(nextPO.id)}`,
                        );
                        return;
                    }
                    if (nextPO.waitingScreening) {
                        router.replace(
                            `/member/transactions/orders/payment?id=${encodeRouteId(nextPO.id)}`,
                        );
                        return;
                    }
                    router.replace(
                        `/member/transactions/orders/${encodeRouteId(nextPO.id)}`,
                    );
                    return;
                }

                router.replace(
                    `/member/transactions/sales/${encodeRouteId(orderId)}`,
                );
            }
        } else {
            const payload: any = {
                note: form.note,
            };

            const res = await saleService.rejectPayment(orderId, payload);
            if (res.success) {
                confirmDialog.value = false;
                snackbar.showMessage(res.message, "success");
                router.replace(
                    `/member/transactions/sales/${encodeRouteId(orderId)}`,
                );
            }
        }
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Gagal memverifikasi pembayaran",
            "error",
        );
    } finally {
        submitting.value = false;
        submittingAction.value = null;
    }
};

const openImage = (url: string) => {
    selectedImage.value = url;
    imageDialog.value = true;
};

onMounted(() => {
    fetchOrder();
});
</script>

<style scoped>
.payment-confirmation__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
}

.payment-confirmation__icon {
    display: grid;
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    place-items: center;
    border-radius: 50%;
}

.payment-confirmation__icon--approve {
    color: #15803d;
    background: #dcfce7;
}

.payment-confirmation__icon--reject {
    color: #b91c1c;
    background: #fee2e2;
}
</style>
