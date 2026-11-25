<template>
    <div class="screen-body" v-if="order">
        <div class="card invoice-success" v-if="$route.query.success === '1' && !isPaymentExpired">
            <span class="row-icon">
                <v-icon icon="mdi-check-circle" color="white" size="24" />
            </span>
            <h2>Pesanan berhasil dibuat</h2>
            <p>Selesaikan pembayaran agar pesanan segera diverifikasi.</p>
        </div>

        <div
            v-if="order.is_preorder"
            class="card mt-3"
            style="padding: 14px; background: #fff7ec; border-color: #ffe0ad"
        >
            <div style="display: flex; gap: 10px; align-items: flex-start">
                <v-icon
                    icon="mdi-information-outline"
                    color="#c77700"
                    size="20"
                />
                <div>
                    <strong
                        style="display: block; font-size: 14px; color: #9a5a00"
                    >
                        Pre Order
                    </strong>
                    <span
                        style="
                            font-size: 12px;
                            color: #805000;
                            line-height: 1.5;
                        "
                    >
                        <template v-if="isIntermediatePickupPreorder">
                            Ini adalah tahap lanjutan PO. Pengambilan barang
                            mengikuti pesanan pembeli awal. Perkembangan
                            transaksi ini dapat dilihat di detail pesanan.
                        </template>
                        <template v-else>
                            Setelah pembayaran diverifikasi, pesanan akan
                            diproses hingga barang siap dikirim atau diambil.
                            Perkembangannya dapat dilihat di detail pesanan.
                        </template>
                    </span>
                </div>
            </div>
        </div>

        <div class="card invoice-card mt-3 mb-4">
            <div class="invoice-head">
                <div class="invoice-brand">
                    <strong>INVOICE</strong>
                    <span v-if="order.ordered_at">{{
                        formatDateTime(order.ordered_at)
                    }}</span>
                </div>
                <BaseBadge
                    type="transaction_status"
                    :value="isPaymentExpired ? 'expired' : order.status?.code"
                    inline
                    chip-class="font-semibold"
                />
            </div>

            <div class="invoice-meta" style="gap: 0px !important">
                <div class="invoice-meta-row">
                    <span>Kode Transaksi</span>
                    <strong>{{ order.code }}</strong>
                </div>
                <div class="invoice-meta-row">
                    <span>Jenis Pesanan</span>
                    <strong>{{
                        order.is_preorder ? "Pre Order" : "Reguler"
                    }}</strong>
                </div>
            </div>
            <div class="invoice-divider"></div>

            <OrderPartyInfo
                title="Data Pembeli"
                icon="mdi-account-outline"
                :party="order.buyer"
                embedded
            />
            <div class="invoice-divider"></div>
            <OrderSellerInfo :seller="order.seller" embedded />
            <div class="invoice-divider"></div>

            <OrderShippingInfo
                v-if="!isIntermediatePickupPreorder"
                :method="
                    isPickupOrder
                        ? 'pickup'
                        : order.shipping?.method || order.shipping_method
                "
                :shipping="order.shipping"
                :origin="order.seller?.origin"
                :destination="order.buyer?.destination"
                :pickup-location="pickupLocation"
                :transit="order.preorder_fulfillment?.mode === 'upline_transit'"
                embedded
            />
            <div
                v-if="!isIntermediatePickupPreorder"
                class="invoice-divider"
            ></div>

            <div class="invoice-meta">
                <div
                    style="
                        font-size: 14px;
                        font-weight: 700;
                        color: var(--text);
                        margin-bottom: 4px;
                    "
                >
                    Daftar Produk
                </div>
                <div
                    class="invoice-meta-row"
                    v-for="item in order.items"
                    :key="item.id"
                    style="margin-bottom: 4px"
                >
                    <div
                        style="
                            flex: 1;
                            text-align: left;
                            padding-right: 8px;
                            min-width: 0;
                        "
                    >
                        <div
                            v-if="item.product?.code"
                            style="
                                color: var(--text);
                                font-weight: 600;
                                font-size: 13px;
                            "
                        >
                            {{ item.product.code }}
                        </div>
                        <div
                            class="line-clamp-2 break-words"
                            style="color: var(--text); font-size: 13px"
                        >
                            {{ item.product?.name }}
                        </div>
                        <div class="text-[11px] text-[var(--muted)] mt-0.5">
                            Poin Bulanan:
                            {{ formatPrice(item.monthly_points ?? 0) }} · Poin
                            Tahunan: {{ formatPrice(item.annual_points ?? 0) }}
                        </div>
                        <div
                            style="
                                margin-top: 2px;
                                color: var(--text);
                                font-size: 12px;
                            "
                        >
                            {{ item.quantity }} x Rp{{
                                formatPrice(item.price)
                            }}
                        </div>
                    </div>
                    <strong
                        style="
                            align-self: center;
                            white-space: nowrap;
                            font-weight: bold;
                        "
                    >
                        Rp{{
                            formatPrice(
                                item.subtotal ?? item.quantity * item.price,
                            )
                        }}
                    </strong>
                </div>
            </div>
            <div class="invoice-divider"></div>

            <div class="invoice-meta">
                <div class="invoice-meta-row">
                    <span>Total Harga Produk</span>
                    <strong
                        >Rp{{
                            formatPrice(order.summary?.product_total || 0)
                        }}</strong
                    >
                </div>
                <div class="invoice-meta-row">
                    <span>Total Poin Bulanan</span>
                    <strong>{{
                        formatPrice(order.summary?.total_monthly_points ?? 0)
                    }}</strong>
                </div>
                <div class="invoice-meta-row">
                    <span>Total Poin Tahunan</span>
                    <strong>{{
                        formatPrice(order.summary?.total_annual_points ?? 0)
                    }}</strong>
                </div>
                <div
                    class="invoice-meta-row"
                    v-if="order.shipping && !isPickupOrder"
                >
                    <span>Ongkos Kirim</span>
                    <strong
                        >Rp{{ formatPrice(order.summary?.shipping_cost || 0) }}
                    </strong>
                </div>
                <div
                    class="invoice-meta-row"
                    v-if="order.shipping && !isPickupOrder"
                >
                    <span>Asuransi Pengiriman</span>
                    <strong>
                        Rp{{
                            formatPrice(
                                order.summary?.shipping_cost_insurance || 0,
                            )
                        }}
                    </strong>
                </div>
                <div
                    class="invoice-meta-row"
                    v-if="order.summary?.total_discount > 0"
                >
                    <span>Diskon</span>
                    <strong style="color: var(--danger)"
                        >-Rp{{
                            formatPrice(order.summary.total_discount)
                        }}</strong
                    >
                </div>
                <div
                    class="invoice-meta-row"
                    v-if="order.summary?.voucher_discount > 0"
                >
                    <span>Potongan Voucher</span>
                    <strong style="color: var(--danger)"
                        >-Rp{{
                            formatPrice(order.summary.voucher_discount)
                        }}</strong
                    >
                </div>
                <div
                    class="invoice-meta-row"
                    v-if="order.summary?.payment_charge > 0"
                >
                    <span>Biaya Layanan</span>
                    <strong
                        >Rp{{
                            formatPrice(order.summary.payment_charge)
                        }}</strong
                    >
                </div>

                <div
                    class="invoice-meta-row"
                    v-if="!isPaymentExpired && !isZeroPayment && order.payment && order.payment.bank"
                    style="
                        margin-top: 8px;
                        padding-top: 8px;
                        border-top: 1px dashed var(--line);
                    "
                >
                    <span>Transfer ke Bank</span>
                    <div class="flex flex-col items-end text-right pl-4">
                        <strong class="block" style="max-width: 100%">
                            {{
                                order.payment.bank.bank_name ||
                                order.payment.bank.name ||
                                "Bank"
                            }}
                        </strong>
                        <div
                            class="flex items-center gap-1 justify-end mt-1"
                            style="font-weight: 700; color: var(--primary)"
                        >
                            {{ order.payment.bank.account_number }}
                            <v-icon
                                icon="mdi-content-copy"
                                size="16"
                                class="cursor-pointer active:opacity-50"
                                @click.stop="
                                    copyToClipboard(
                                        order.payment.bank.account_number,
                                    )
                                "
                            />
                        </div>
                        <span
                            style="
                                font-size: 12px;
                                color: var(--muted);
                                font-weight: normal;
                            "
                            >a.n. {{ order.payment.bank.account_name }}</span
                        >
                    </div>
                </div>

                <!-- Spread Payment Bank Info -->
                <div
                    class="invoice-meta-row"
                    v-if="!isPaymentExpired && order.payment?.spread_payment?.bank"
                    style="
                        margin-top: 8px;
                        padding-top: 8px;
                        border-top: 1px dashed var(--line);
                    "
                >
                    <span>Bank Kemitraan</span>
                    <div class="flex flex-col items-end text-right pl-4">
                        <strong class="block" style="max-width: 100%">
                            {{
                                order.payment.spread_payment.bank.name || "Bank"
                            }}
                        </strong>
                        <div
                            class="flex items-center gap-1 justify-end mt-1"
                            style="font-weight: 700; color: var(--primary)"
                        >
                            {{
                                order.payment.spread_payment.bank.account_number
                            }}
                            <v-icon
                                icon="mdi-content-copy"
                                size="16"
                                class="cursor-pointer active:opacity-50"
                                @click.stop="
                                    copyToClipboard(
                                        order.payment.spread_payment.bank
                                            .account_number,
                                    )
                                "
                            />
                        </div>
                        <span
                            style="
                                font-size: 12px;
                                color: var(--muted);
                                font-weight: normal;
                            "
                            >a.n.
                            {{
                                order.payment.spread_payment.bank.account_name
                            }}</span
                        >
                    </div>
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

        <div
            v-if="showPaymentCountdown"
            class="card mb-4"
            style="padding: 18px; border-color: var(--primary)"
        >
            <div class="d-flex justify-space-between align-center ga-3">
                <span style="font-size: 13px">Sisa waktu upload bukti transfer</span>
                <strong style="font-size: 22px; font-variant-numeric: tabular-nums">{{ countdown }}</strong>
            </div>
            <p style="font-size: 12px; color: var(--muted); margin-top: 8px">
                Batas upload: {{ paymentDeadlineLabel }} WIB.
                Pesanan dibatalkan otomatis jika bukti belum dikirim sebelum batas waktu upload berakhir.
            </p>
        </div>

        <div v-if="isPaymentExpired" class="card mb-4" style="padding: 20px">
            <h3 style="font-size: 16px; margin-bottom: 8px">Batas Waktu Pembayaran Berakhir</h3>
            <p style="font-size: 14px; color: var(--muted); line-height: 1.5">
                Pesanan kedaluwarsa dan tidak dapat dilanjutkan. Silakan buat pesanan baru.
                Jika sudah melakukan transfer, hubungi admin dengan bukti transfer dan kode transaksi.
            </p>
            <router-link to="/member/transactions/orders" class="primary-button block mt-4">
                Kembali ke Pesanan
            </router-link>
        </div>
        <template v-else-if="order.actions?.waiting_for_stock_screening">
            <div
                class="card mt-4 mb-4"
                style="padding: 24px; text-align: center"
            >
                <v-icon
                    icon="mdi-clipboard-text-search-outline"
                    size="48"
                    color="orange-darken-2"
                    class="mb-2"
                />
                <h3 style="font-size: 16px; margin-bottom: 8px">
                    Menunggu Screening Stok
                </h3>
                <p
                    style="
                        font-size: 14px;
                        color: var(--muted);
                        line-height: 1.5;
                    "
                >
                    Pesanan Anda sedang dalam proses screening stok oleh pusat.
                    Silakan cek kembali secara berkala, Anda dapat melanjutkan
                    pembayaran setelah pesanan disetujui.
                </p>
                <router-link
                    :to="`/member/transactions/orders/${encodeRouteId(order.id)}`"
                    class="primary-button block mt-4"
                    style="text-decoration: none"
                >
                    Lihat Detail Pesanan
                </router-link>
            </div>
        </template>
        <template v-else>
            <div
                v-if="isZeroPayment"
                class="card mb-4"
                style="padding: 20px; text-align: center"
            >
                <v-icon
                    icon="mdi-ticket-confirmation-outline"
                    size="42"
                    color="primary"
                    class="mb-2"
                />
                <h3 style="font-size: 16px; margin-bottom: 6px">
                    Tagihan Lunas dengan Voucher
                </h3>
                <p
                    style="
                        font-size: 13px;
                        color: var(--muted);
                        line-height: 1.5;
                    "
                >
                    Total pembayaran Rp0. Anda tidak perlu mengunggah bukti
                    transfer. Konfirmasikan pembayaran agar pesanan masuk ke
                    verifikasi admin.
                </p>
            </div>

            <template v-else>
                <div
                    v-if="hasSpreadPayment"
                    style="
                        font-size: 13px;
                        font-weight: 700;
                        color: var(--text);
                        margin-bottom: 8px;
                    "
                >
                    Bukti Transfer Bank Pembayaran
                </div>
                <button
                    class="upload-proof"
                    type="button"
                    @click="fileInput?.click()"
                >
                    <div
                        v-if="previewUrl"
                        style="
                            width: 100%;
                            max-width: 250px;
                            margin: 0 auto 16px auto;
                            border-radius: 8px;
                            overflow: hidden;
                            border: 1px solid var(--line);
                        "
                    >
                        <img
                            :src="previewUrl"
                            alt="Preview"
                            style="width: 100%; height: auto; display: block"
                        />
                    </div>
                    <v-icon
                        v-if="!previewUrl"
                        icon="mdi-cloud-upload-outline"
                        size="32"
                        color="primary"
                        class="mb-2"
                    />
                    <span class="upload-texts" style="display: block">
                        <strong
                            style="
                                display: block;
                                font-size: 14px;
                                margin-bottom: 4px;
                            "
                            >{{
                                previewUrl
                                    ? "Ubah Bukti Transfer"
                                    : "Pilih Bukti Transfer"
                            }}</strong
                        >
                        <span
                            v-if="!selectedFile"
                            style="font-size: 12px; color: var(--muted)"
                            >JPG atau PNG · maksimal 5 MB</span
                        >
                        <span
                            v-else
                            class="text-primary"
                            style="font-size: 12px"
                            >{{ selectedFile.name }}</span
                        >
                    </span>
                    <input
                        type="file"
                        ref="fileInput"
                        class="d-none"
                        accept="image/jpeg,image/png"
                        @change="onFileSelected"
                    />
                </button>

                <template v-if="hasSpreadPayment">
                    <div
                        style="
                            font-size: 13px;
                            font-weight: 700;
                            color: var(--text);
                            margin-top: 16px;
                            margin-bottom: 8px;
                        "
                    >
                        Bukti Transfer Bank Kemitraan
                    </div>
                    <button
                        class="upload-proof"
                        type="button"
                        @click="spreadFileInput?.click()"
                    >
                        <div
                            v-if="spreadPreviewUrl"
                            style="
                                width: 100%;
                                max-width: 250px;
                                margin: 0 auto 16px auto;
                                border-radius: 8px;
                                overflow: hidden;
                                border: 1px solid var(--line);
                            "
                        >
                            <img
                                :src="spreadPreviewUrl"
                                alt="Preview Kemitraan"
                                style="
                                    width: 100%;
                                    height: auto;
                                    display: block;
                                "
                            />
                        </div>
                        <v-icon
                            v-if="!spreadPreviewUrl"
                            icon="mdi-cloud-upload-outline"
                            size="32"
                            color="primary"
                            class="mb-2"
                        />
                        <span class="upload-texts" style="display: block">
                            <strong
                                style="
                                    display: block;
                                    font-size: 14px;
                                    margin-bottom: 4px;
                                "
                                >{{
                                    spreadPreviewUrl
                                        ? "Ubah Bukti Transfer"
                                        : "Pilih Bukti Transfer"
                                }}</strong
                            >
                            <span
                                v-if="!spreadSelectedFile"
                                style="font-size: 12px; color: var(--muted)"
                                >JPG atau PNG · maksimal 5 MB</span
                            >
                            <span
                                v-else
                                class="text-primary"
                                style="font-size: 12px"
                                >{{ spreadSelectedFile.name }}</span
                            >
                        </span>
                        <input
                            type="file"
                            ref="spreadFileInput"
                            class="d-none"
                            accept="image/jpeg,image/png"
                            @change="onSpreadFileSelected"
                        />
                    </button>
                </template>
            </template>

            <button
                class="primary-button block mt-3"
                @click="submitPaymentProof"
                :disabled="
                    !canSubmit ||
                    isSubmitting ||
                    isUploading ||
                    spreadUpload.isUploading.value
                "
            >
                <v-progress-circular
                    v-if="
                        isSubmitting ||
                        isUploading ||
                        spreadUpload.isUploading.value
                    "
                    indeterminate
                    size="20"
                    width="2"
                    color="white"
                    class="mr-2"
                />
                {{
                    isUploading || spreadUpload.isUploading.value
                        ? `Mengirim (${uploadProgress}%)`
                        : isSubmitting
                          ? "Memproses..."
                          : isZeroPayment
                            ? "Konfirmasi Pembayaran"
                            : "Kirim Bukti Pembayaran"
                }}
                <v-icon
                    v-if="
                        !isSubmitting &&
                        !isUploading &&
                        !spreadUpload.isUploading.value
                    "
                    icon="mdi-arrow-right"
                    size="14"
                    class="ml-1"
                />
            </button>
        </template>
    </div>

    <div v-else-if="isLoading" style="padding: 40px; text-align: center">
        <v-progress-circular
            indeterminate
            color="primary"
        ></v-progress-circular>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import purchaseService from "@/member/services/purchase.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import OrderSellerInfo from "@/member/components/transactions/OrderSellerInfo.vue";
import OrderPartyInfo from "@/member/components/transactions/OrderPartyInfo.vue";
import OrderShippingInfo from "@/member/components/transactions/OrderShippingInfo.vue";
import { decodeRouteId, encodeRouteId } from "@/shared/utils/route-id";

import { useMediaUpload } from "@/shared/composables/useMediaUpload";
import { usePaymentUploadCountdown } from "@/member/composables/usePaymentUploadCountdown";

const router = useRouter();
const route = useRoute();
const { formatPrice, formatDateTime } = useFormatter();
const snackbar = useSnackbarStore();
const { isUploading, progress, upload } = useMediaUpload();
const spreadUpload = useMediaUpload();

const orderId = decodeRouteId(route.query.id);

const order = ref<any>(null);
const { countdown, expired, hasDeadline, setDeadline, tick } = usePaymentUploadCountdown();
const isPaymentExpired = computed(() => order.value?.status?.code === "expired"
    || (order.value?.status?.code === "waiting_payment" && expired.value));
const showPaymentCountdown = computed(() => hasDeadline.value
    && order.value?.status?.code === "waiting_payment" && !isPaymentExpired.value);
const paymentDeadlineLabel = computed(() => hasDeadline.value
    ? new Intl.DateTimeFormat("id-ID", {
        timeZone: "Asia/Jakarta", dateStyle: "long", timeStyle: "medium",
    }).format(new Date(order.value.payment_upload_expires_at)) : "-");
const isPickupOrder = computed(
    () =>
        order.value?.shipping?.method === "pickup" ||
        (order.value?.shipping_method === "pickup" &&
            Number(order.value?.parent_transaction_id || 0) === 0),
);
const pickupLocation = computed(
    () => order.value?.pickup_location || order.value?.seller?.origin,
);
const isIntermediatePickupPreorder = computed(
    () =>
        order.value?.is_preorder === true &&
        Number(order.value?.parent_transaction_id) > 0 &&
        order.value?.shipping?.method === "pickup",
);
const isLoading = ref(true);
const isSubmitting = ref(false);

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const previewUrl = ref<string | null>(null);

const spreadFileInput = ref<HTMLInputElement | null>(null);
const spreadSelectedFile = ref<File | null>(null);
const spreadPreviewUrl = ref<string | null>(null);

const hasSpreadPayment = computed(() => {
    return order.value?.payment?.spread_payment != null;
});

const isZeroPayment = computed(() => {
    const billAmount = Number(
        order.value?.summary?.bill_amount ??
            order.value?.payment?.bill_amount ??
            order.value?.summary?.grand_total ??
            0,
    );

    return billAmount <= 0;
});

const canSubmit = computed(() => {
    if (isPaymentExpired.value || order.value?.actions?.can_upload_payment === false) return false;
    if (isZeroPayment.value) return true;
    if (!selectedFile.value) return false;
    if (hasSpreadPayment.value && !spreadSelectedFile.value) return false;
    return true;
});

const uploadProgress = computed(() => {
    if (hasSpreadPayment.value) {
        return Math.round((progress.value + spreadUpload.progress.value) / 2);
    }
    return progress.value;
});

const fetchOrder = async () => {
    if (!orderId) {
        router.replace("/member/transactions/orders");
        return;
    }
    isLoading.value = true;
    try {
        const response = await purchaseService.getOrderDetail(orderId);
        if (response.success && response.data) {
            setDeadline(response.data.payment_upload_expires_at, response.data.server_time);
            if (
                !response.data.actions?.can_upload_payment &&
                !response.data.actions?.waiting_for_stock_screening &&
                response.data.status?.code !== "expired" &&
                !(response.data.status?.code === "waiting_payment" && expired.value)
            ) {
                snackbar.showMessage(
                    "Bukti pembayaran tidak perlu diunggah kembali.",
                    "info",
                );
                router.replace(
                    `/member/transactions/orders/${encodeRouteId(orderId)}`,
                );
                return;
            }

            order.value = response.data;
        } else {
            snackbar.showMessage("Gagal memuat pesanan", "error");
            router.replace("/member/transactions/orders");
        }
    } catch (error) {
        console.error("Failed to fetch order", error);
        snackbar.showMessage("Gagal memuat pesanan", "error");
        router.replace("/member/transactions/orders");
    } finally {
        isLoading.value = false;
    }
};

const copyToClipboard = (text: string) => {
    navigator.clipboard
        .writeText(text)
        .then(() => {
            snackbar.showMessage("Nomor rekening berhasil disalin", "success");
        })
        .catch(() => {
            snackbar.showMessage("Gagal menyalin", "error");
        });
};

const onFileSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        selectedFile.value = target.files[0];
        previewUrl.value = URL.createObjectURL(target.files[0]);
    }
};

const onSpreadFileSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        spreadSelectedFile.value = target.files[0];
        spreadPreviewUrl.value = URL.createObjectURL(target.files[0]);
    }
};

const submitPaymentProof = async () => {
    tick();
    if (!order.value || !canSubmit.value) return;

    try {
        isSubmitting.value = true;
        const payload: Record<string, any> = {
            note: isZeroPayment.value
                ? "Pembayaran dikonfirmasi menggunakan voucher penuh."
                : undefined,
        };

        if (!isZeroPayment.value && selectedFile.value) {
            const uploadPromises: Promise<string>[] = [
                upload(selectedFile.value, "payment_receipts"),
            ];
            if (hasSpreadPayment.value && spreadSelectedFile.value) {
                uploadPromises.push(
                    spreadUpload.upload(
                        spreadSelectedFile.value,
                        "payment_receipts",
                    ),
                );
            }

            const urls = await Promise.all(uploadPromises);
            tick();
            if (isPaymentExpired.value) {
                snackbar.showMessage("Batas waktu upload bukti transfer telah berakhir.", "error");
                return;
            }
            payload.receipt_url = urls[0];

            if (hasSpreadPayment.value && urls[1]) {
                payload.spread_receipt_url = urls[1];
            }
        }

        const response = await purchaseService.payOrder(orderId, payload);

        snackbar.showMessage(
            response.message ||
                (isZeroPayment.value
                    ? "Pembayaran berhasil dikonfirmasi"
                    : "Bukti pembayaran berhasil diunggah"),
            "success",
        );
        router.push(`/member/transactions/orders/${encodeRouteId(orderId)}`);
    } catch (error: any) {
        console.error("Upload failed", error);
        snackbar.showMessage(
            error.response?.data?.message ||
                (isZeroPayment.value
                    ? "Gagal mengonfirmasi pembayaran"
                    : "Gagal mengunggah bukti pembayaran"),
            "error",
        );
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    fetchOrder();
});
</script>
