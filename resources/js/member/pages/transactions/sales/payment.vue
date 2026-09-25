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

            <div class="invoice-total">
                <span>Total Pembayaran</span>
                <strong
                    >Rp{{
                        formatPrice(order.summary?.grand_total || 0)
                    }}</strong
                >
            </div>
        </div>

        <div class="card mb-4 detail-card">
            <div class="detail-section">
                <h3 class="detail-title">
                    <v-icon icon="mdi-account-outline" size="18" />
                    Data Pembeli
                </h3>
                <strong class="text-[13px]">{{
                    order.customer?.name || order.buyer?.name || "-"
                }}</strong>
                <p v-if="order.customer?.whatsapp" class="detail-muted mt-1">
                    {{ order.customer.whatsapp }}
                </p>
            </div>
            <div class="detail-section detail-divider">
                <h3 class="detail-title">
                    <v-icon icon="mdi-truck-outline" size="18" />
                    {{
                        order.shipping?.method === "pickup"
                            ? "Informasi Pengambilan"
                            : "Informasi Pengiriman"
                    }}
                </h3>
                <div class="detail-row">
                    <span>Metode</span>
                    <strong>{{ shippingLabel }}</strong>
                </div>
                <div class="detail-row" v-if="order.seller?.origin">
                    <span>{{ order.shipping?.method === "pickup" ? "Lokasi Pengambilan" : "Asal Barang" }}</span>
                    <strong>{{ order.seller.origin.name || "-" }}</strong>
                </div>
                <div
                    v-if="order.shipping?.method !== 'pickup'"
                    class="detail-row"
                >
                    <span>Kurir</span>
                    <strong>
                        <span class="uppercase"
                            >{{ order.shipping?.courier || "-" }} -
                        </span>
                        {{ order.shipping?.service || "" }}
                    </strong>
                </div>
                <div v-if="order.shipping?.tracking_number" class="detail-row">
                    <span>Nomor Resi</span>
                    <strong>{{ order.shipping.tracking_number }}</strong>
                </div>
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
                    />
                    <div v-else class="product-image product-placeholder">
                        <v-icon icon="mdi-image-outline" size="22" />
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

        <v-dialog v-model="confirmDialog" max-width="400">
            <v-card>
                <v-card-title class="text-h6 font-weight-bold pt-4 px-4">
                    Konfirmasi
                    {{
                        confirmAction === "approved"
                            ? "Persetujuan"
                            : "Penolakan"
                    }}
                </v-card-title>
                <v-card-text class="px-4">
                    <p class="mb-4 text-[14px]">
                        Apakah Anda yakin ingin
                        {{
                            confirmAction === "approved"
                                ? "menyetujui"
                                : "menolak"
                        }}
                        pembayaran ini?
                    </p>
                    <v-textarea
                        v-model="form.note"
                        :label="
                            confirmAction === 'rejected'
                                ? 'Catatan (Wajib)'
                                : 'Catatan (Opsional)'
                        "
                        rows="3"
                        variant="outlined"
                        density="compact"
                        hide-details="auto"
                    ></v-textarea>
                </v-card-text>
                <v-card-actions class="pb-4 px-4">
                    <v-spacer></v-spacer>
                    <v-btn
                        color="grey-darken-1"
                        variant="outlined"
                        @click="confirmDialog = false"
                        :disabled="submitting"
                        >Batal</v-btn
                    >
                    <v-btn
                        :color="
                            confirmAction === 'approved' ? 'primary' : 'error'
                        "
                        variant="flat"
                        :loading="submitting"
                        @click="executeSubmit"
                    >
                        Ya,
                        {{ confirmAction === "approved" ? "Setujui" : "Tolak" }}
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
import { decodeRouteId, encodeRouteId } from "@/shared/utils/route-id";

const router = useRouter();
const route = useRoute();
const { formatPrice, formatDateTime } = useFormatter();
const snackbar = useSnackbarStore();

const orderId = decodeRouteId(route.query.id);
const order = ref<any>(null);
const isLoading = ref(true);
const submitting = ref(false);
const submittingAction = ref<"approved" | "rejected" | null>(null);

const confirmDialog = ref(false);
const confirmAction = ref<"approved" | "rejected" | null>(null);

const imageDialog = ref(false);
const selectedImage = ref("");

const isCustomer = computed(() => order.value?.buyer?.type === "customer");
const shippingLabel = computed(() => {
    const method =
        order.value?.shipping?.method || order.value?.shipping_method;
    const labels: Record<string, string> = {
        pickup: "Diambil di Tempat",
        courier_manual: "Kurir",
        courier_express: "Kurir Ekspres",
        courier_instant: "Kurir Instan",
    };

    return labels[method] || "-";
});

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
    confirmDialog.value = true;
};

const executeSubmit = async () => {
    const status = confirmAction.value;
    if (!status) return;

    if (status === "rejected" && !form.note.trim()) {
        snackbar.showMessage("Catatan penolakan wajib diisi.", "error");
        return;
    }

    submitting.value = true;
    submittingAction.value = status;
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
