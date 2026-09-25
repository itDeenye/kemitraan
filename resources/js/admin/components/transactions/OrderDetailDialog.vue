<template>
    <v-dialog v-model="dialog" max-width="800" scrollable persistent>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-avatar color="primary" variant="tonal" size="48">
                        <v-icon>mdi-receipt-text-outline</v-icon>
                    </v-avatar>
                    <div>
                        <div class="text-h6 font-weight-bold">
                            Detail Pesanan Penjualan
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ order?.code || "Memuat..." }}
                        </div>
                    </div>
                </div>
                <div class="d-flex align-center ga-2">
                    <v-btn
                        v-if="order"
                        color="primary"
                        prepend-icon="mdi-printer"
                        variant="tonal"
                        size="small"
                        class="text-none font-weight-bold rounded-lg"
                        :loading="printing === 'po'"
                        @click="printDocument('po')"
                    >
                        Cetak PO
                    </v-btn>
                    <v-btn
                        v-if="order"
                        color="info"
                        prepend-icon="mdi-printer"
                        variant="tonal"
                        size="small"
                        class="text-none font-weight-bold rounded-lg"
                        :loading="printing === 'invoice'"
                        @click="printDocument('invoice')"
                    >
                        Cetak Faktur
                    </v-btn>
                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        size="small"
                        @click="close"
                    />
                </div>
            </v-card-title>

            <v-card-text class="pa-4" style="max-height: 70vh">
                <div v-if="loading" class="d-flex justify-center py-12">
                    <v-progress-circular
                        indeterminate
                        color="primary"
                        size="48"
                        width="4"
                    />
                </div>
                <div
                    v-else-if="!order"
                    class="text-center py-12 text-medium-emphasis"
                >
                    <v-icon size="48" class="mb-2"
                        >mdi-alert-circle-outline</v-icon
                    >
                    <div>Gagal memuat detail pesanan</div>
                </div>
                <div v-else class="d-flex flex-column ga-4">
                    <div
                        class="d-flex align-center justify-space-between flex-wrap ga-4"
                    >
                        <div class="d-flex align-center ga-4">
                            <div>
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    Status Pesanan
                                </div>
                                <BaseBadge
                                    type="transaction_status"
                                    :value="order.status"
                                />
                            </div>
                            <v-divider
                                vertical
                                class="mx-2"
                                style="height: 32px"
                            />
                            <div>
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    Tipe Pesanan
                                </div>
                                <BaseBadge
                                    type="transaction_type"
                                    :value="order.is_preorder"
                                />
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-caption text-medium-emphasis mb-1">
                                Tanggal Pesanan
                            </div>
                            <div class="text-body-2 font-weight-medium">
                                {{ formatDateTime(order.ordered_at) }}
                            </div>
                        </div>
                    </div>

                    <PreorderChainInfo :preorder="order.preorder" />

                    <v-row>
                        <v-col cols="12" sm="6">
                            <v-card
                                variant="outlined"
                                class="rounded-lg h-100 pa-4"
                            >
                                <h3
                                    class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center ga-2 text-primary"
                                >
                                    <v-icon size="small"
                                        >mdi-store-outline</v-icon
                                    >
                                    Penjual
                                </h3>
                                <div
                                    class="text-body-2 font-weight-medium mb-7"
                                >
                                    {{ order.seller?.name || "-" }}
                                </div>
                                <div
                                    v-if="order.seller?.origin?.address"
                                    class="text-caption text-medium-emphasis mt-2 mb-1 d-flex"
                                >
                                    <v-icon size="x-small" class="mr-1 mt-1"
                                        >mdi-map-marker-outline</v-icon
                                    >
                                    <div>
                                        <div>
                                            Asal barang:
                                            {{
                                                order.seller?.origin?.name ||
                                                "-"
                                            }}
                                        </div>
                                        {{
                                            formatLocation(order.seller?.origin)
                                        }}
                                    </div>
                                </div>
                                <v-chip
                                    size="x-small"
                                    variant="tonal"
                                    color="primary"
                                    class="mt-1 font-weight-medium text-capitalize"
                                >
                                    {{ order.seller?.type || "Unknown" }}
                                </v-chip>
                            </v-card>
                        </v-col>
                        <v-col cols="12" sm="6">
                            <v-card
                                variant="outlined"
                                class="rounded-lg h-100 pa-4"
                            >
                                <h3
                                    class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center ga-2 text-info"
                                >
                                    <v-icon size="small"
                                        >mdi-account-outline</v-icon
                                    >
                                    Pembeli
                                </h3>
                                <div class="text-body-2 font-weight-medium">
                                    {{ order.buyer?.name || "-" }}
                                </div>
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    {{ order.buyer?.code || "-" }}
                                </div>
                                <div
                                    v-if="order.buyer?.destination?.address"
                                    class="text-caption text-medium-emphasis mt-2 mb-1 d-flex"
                                >
                                    <v-icon size="x-small" class="mr-1 mt-1"
                                        >mdi-map-marker-outline</v-icon
                                    >
                                    <div>
                                        Tujuan barang:
                                        {{
                                            order.buyer?.destination?.name ||
                                            "-"
                                        }}
                                        <br />
                                        {{
                                            formatLocation(
                                                order.buyer?.destination,
                                            )
                                        }}
                                    </div>
                                </div>
                                <v-chip
                                    size="x-small"
                                    variant="tonal"
                                    color="info"
                                    class="mt-1 font-weight-medium text-capitalize"
                                >
                                    {{ order.buyer?.type || "Unknown" }}
                                </v-chip>
                            </v-card>
                        </v-col>
                    </v-row>

                    <div>
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="primary"
                                >mdi-package-variant-closed</v-icon
                            >
                            Rincian Produk
                        </h3>
                        <v-card
                            variant="outlined"
                            class="rounded-lg overflow-hidden"
                        >
                            <v-table density="comfortable">
                                <thead class="">
                                    <tr>
                                        <th class="font-weight-bold text-left">
                                            Produk
                                        </th>
                                        <th class="text-right font-weight-bold">
                                            Harga (Rp)
                                        </th>
                                        <th
                                            class="text-right font-weight-bold"
                                            style="width: 80px"
                                        >
                                            Qty
                                        </th>
                                        <th class="text-right font-weight-bold">
                                            Subtotal (Rp)
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in order.details"
                                        :key="item.id"
                                    >
                                        <td class="py-3">
                                            <div
                                                class="font-weight-medium text-body-2 text-truncate"
                                                style="max-width: 280px"
                                                :title="item.product?.name"
                                            >
                                                {{ item.product?.name }}
                                            </div>
                                            <div
                                                class="text-caption text-medium-emphasis"
                                            >
                                                {{ item.product?.code }}
                                            </div>
                                        </td>
                                        <td class="text-right text-body-2">
                                            {{ formatPrice(item.price) }}
                                        </td>
                                        <td class="text-right text-body-2">
                                            {{ item.quantity }}
                                        </td>
                                        <td
                                            class="text-right font-weight-medium text-body-2"
                                        >
                                            {{ formatPrice(item.subtotal) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>
                            <v-divider />
                            <div class="pa-4">
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                >
                                    <span class="text-medium-emphasis"
                                        >Total Harga Produk</span
                                    >
                                    <span class="font-weight-medium"
                                        >Rp{{
                                            formatPrice(
                                                order.totals?.product_total ||
                                                    0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (order.totals?.discount_value || 0) > 0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Diskon</span
                                    >
                                    <span class="font-weight-medium text-error"
                                        >-Rp
                                        {{
                                            formatPrice(
                                                order.totals?.discount_value ||
                                                    0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (order.totals?.voucher_value || 0) > 0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Potongan Voucher</span
                                    >
                                    <span class="font-weight-medium text-error"
                                        >-Rp
                                        {{
                                            formatPrice(
                                                order.totals?.voucher_value ||
                                                    0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (order.totals?.shipping_cost || 0) > 0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Ongkos Kirim</span
                                    >
                                    <span class="font-weight-medium"
                                        >Rp{{
                                            formatPrice(
                                                order.totals?.shipping_cost ||
                                                    0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (order.totals
                                            ?.shipping_cost_insurance || 0) > 0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Asuransi Pengiriman</span
                                    >
                                    <span class="font-weight-medium"
                                        >Rp{{
                                            formatPrice(
                                                order.totals
                                                    ?.shipping_cost_insurance ||
                                                    0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <v-divider class="my-2" />
                                <div
                                    class="d-flex justify-space-between text-subtitle-1 font-weight-bold"
                                >
                                    <span>Total Pembayaran</span>
                                    <span class="text-primary"
                                        >Rp{{
                                            formatPrice(
                                                order.totals?.grand_total || 0,
                                            )
                                        }}</span
                                    >
                                </div>
                            </div>
                        </v-card>
                    </div>

                    <v-row>
                        <v-col cols="12" sm="6" class="d-flex flex-column">
                            <h3
                                class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                            >
                                <v-icon size="small" color="primary"
                                    >mdi-credit-card-outline</v-icon
                                >
                                Informasi Pembayaran
                            </h3>
                            <v-card
                                variant="outlined"
                                class="rounded-lg pa-4 flex-grow-1"
                            >
                                <div
                                    class="d-flex flex-column ga-2 text-body-2"
                                >
                                    <div
                                        class="d-flex justify-space-between"
                                        v-if="order.payment"
                                    >
                                        <span class="text-medium-emphasis"
                                            >Status Pembayaran</span
                                        >
                                        <BaseBadge
                                            type="payment_status"
                                            :value="order.payment.status"
                                            align="end"
                                        />
                                    </div>
                                    <template v-if="order.payment?.bank">
                                        <div
                                            class="d-flex justify-space-between mt-2 pt-2 border-t"
                                        >
                                            <span class="text-medium-emphasis"
                                                >Bank Tujuan</span
                                            >
                                            <span class="font-weight-medium">{{
                                                order.payment.bank.name || "-"
                                            }}</span>
                                        </div>
                                        <div
                                            class="d-flex justify-space-between"
                                        >
                                            <span class="text-medium-emphasis"
                                                >Rekening Tujuan</span
                                            >
                                            <span
                                                class="font-weight-medium text-right"
                                            >
                                                <div>
                                                    {{
                                                        order.payment.bank
                                                            .account_number ||
                                                        "-"
                                                    }}
                                                </div>
                                                <div
                                                    class="text-caption text-medium-emphasis"
                                                >
                                                    a/n
                                                    {{
                                                        order.payment.bank
                                                            .account_name || "-"
                                                    }}
                                                </div>
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </v-card>
                        </v-col>

                        <v-col cols="12" sm="6" class="d-flex flex-column">
                            <h3
                                class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                            >
                                <v-icon size="small" color="primary">{{
                                    isPickup
                                        ? "mdi-store-outline"
                                        : "mdi-truck-delivery-outline"
                                }}</v-icon>
                                {{
                                    isPickup
                                        ? "Informasi Pengambilan"
                                        : "Informasi Pengiriman"
                                }}
                            </h3>
                            <v-card
                                variant="outlined"
                                class="rounded-lg pa-4 flex-grow-1"
                            >
                                <div
                                    class="d-flex flex-column ga-2 text-body-2"
                                >
                                    <template v-if="isPickup">
                                        <div
                                            class="d-flex flex-column ga-1 mt-1"
                                        >
                                            <div
                                                class="font-weight-medium text-body-2"
                                            >
                                                {{
                                                    order.seller?.origin
                                                        ?.name ||
                                                    order.seller?.name ||
                                                    "-"
                                                }}
                                            </div>
                                            <div
                                                class="text-medium-emphasis text-body-2 line-height-relaxed"
                                                style="line-height: 1.5"
                                            >
                                                {{
                                                    formatLocation(
                                                        order.seller?.origin,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div
                                            class="d-flex justify-space-between"
                                        >
                                            <span class="text-medium-emphasis"
                                                >Metode Kirim</span
                                            >
                                            <BaseBadge
                                                type="shipping_method"
                                                :value="
                                                    order.shipping?.method ||
                                                    order.shipping_method
                                                "
                                                align="end"
                                            />
                                        </div>
                                        <div
                                            class="d-flex justify-space-between"
                                            v-if="order.shipping?.courier"
                                        >
                                            <span class="text-medium-emphasis"
                                                >Kurir</span
                                            >
                                            <span
                                                class="font-weight-medium text-capitalize text-truncate text-right"
                                                style="max-width: 180px"
                                                :title="
                                                    order.shipping.courier +
                                                    (order.shipping.service
                                                        ? ` (${order.shipping.service})`
                                                        : '')
                                                "
                                            >
                                                {{ order.shipping.courier }}
                                                {{
                                                    order.shipping.service
                                                        ? `(${order.shipping.service})`
                                                        : ""
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="d-flex justify-space-between"
                                            v-if="order.shipping?.pickup_method"
                                        >
                                            <span class="text-medium-emphasis"
                                                >Tipe Pickup Kurir</span
                                            >
                                            <BaseBadge
                                                type="pickup_method"
                                                :value="
                                                    order.shipping.pickup_method
                                                "
                                                align="end"
                                            />
                                        </div>
                                        <div
                                            class="d-flex justify-space-between"
                                            v-if="
                                                order.shipping?.tracking_number
                                            "
                                        >
                                            <span class="text-medium-emphasis"
                                                >No. Resi</span
                                            >
                                            <span class="font-weight-medium">{{
                                                order.shipping.tracking_number
                                            }}</span>
                                        </div>
                                    </template>
                                </div>
                            </v-card>
                        </v-col>
                    </v-row>
                </div>
            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions class="pa-6 pt-4 border-t">
                <v-spacer />
                <v-btn
                    variant="flat"
                    color="primary"
                    class="text-none px-8"
                    @click="close"
                    >Tutup</v-btn
                >
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import PreorderChainInfo from "@/admin/components/transactions/PreorderChainInfo.vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import transactionOrderService from "@/admin/services/transaction-order.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";

type OrderDetailService = {
    getDetail: (id: number | string) => Promise<any>;
    getDocument?: (id: number | string) => Promise<any>;
};

const props = defineProps<{
    detailService?: OrderDetailService;
}>();

const { formatDateTime, formatDate, formatPrice, formatLocation } =
    useFormatter();
const snackbar = useSnackbarStore();
const dialog = ref(false);
const loading = ref(false);
const printing = ref<string | null>(null);
const order = ref<any>(null);
const isPickup = computed(
    () =>
        (order.value?.shipping?.method || order.value?.shipping_method) ===
        "pickup",
);

const open = async (id: number | string) => {
    dialog.value = true;
    await fetchDetail(id);
};

const close = () => {
    dialog.value = false;
    order.value = null;
};

const fetchDetail = async (id: number | string) => {
    loading.value = true;
    try {
        const service = props.detailService ?? transactionOrderService;
        const data = await service.getDetail(id);
        if (data) {
            order.value = data;
        }
    } catch (error) {
        console.error("Failed to fetch order detail:", error);
        snackbar.showMessage("Gagal memuat detail pesanan", "error");
    } finally {
        loading.value = false;
    }
};

const escapeHtml = (value: unknown): string =>
    String(value ?? "-").replace(/[&<>"']/g, (character) => {
        const entities: Record<string, string> = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
        };

        return entities[character];
    });

const shippingMethodLabel = (method: string): string =>
    ({
        pickup: "Ambil di Tempat",
        courier_express: "Kurir Ekspres",
        courier_instant: "Kurir Instan",
        courier_manual: "Kurir Manual",
    })[method] ||
    method ||
    "-";

const printDocument = async (type: "po" | "invoice") => {
    if (!order.value?.id) return;

    printing.value = type;
    try {
        const service = props.detailService?.getDocument
            ? props.detailService
            : transactionOrderService;
        const data = await service.getDocument!(order.value.id);
        if (data) {
            let printContent = "";

            if (type === "po") {
                printContent = `
                <div style="font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; font-size: 12px; color: #000;">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; font-size: 16px;">[KOP SURAT SARANA PEMESAN/DISTRIBUTOR / TOKO] SURAT</h2>
                        <h2 style="margin: 0; font-size: 16px;">PESANAN KOSMETIKA (PURCHASE ORDER)</h2>
                        <p style="margin: 5px 0; font-size: 14px;">Nomor: ${escapeHtml(data.code)}</p>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div style="width: 48%;">
                            <table style="width: 100%; font-size: 12px;">
                                <tr><td colspan="3"><strong>Data Pemesan (Sarana):</strong></td></tr>
                                <tr><td style="width: 140px;">Nama Toko / Klinik</td><td style="width: 10px;">:</td><td>${escapeHtml(data.buyer?.name)}</td></tr>
                                <tr><td>NIB / Izin Usaha</td><td>:</td><td>........................................................</td></tr>
                                <tr><td>Nama Penanggung Jawab</td><td>:</td><td>${escapeHtml(data.buyer?.destination?.name || "-")}</td></tr>
                                <tr><td style="vertical-align: top;">Alamat Lengkap</td><td style="vertical-align: top;">:</td><td>${escapeHtml(data.buyer?.destination?.address || "-")}, ${escapeHtml(data.buyer?.destination?.subdistrict?.name || "-")}, ${escapeHtml(data.buyer?.destination?.district?.name || "-")}, ${escapeHtml(data.buyer?.destination?.city?.name || "-")}, ${escapeHtml(data.buyer?.destination?.province?.name || "-")}</td></tr>
                                <tr><td>No. Telp / WA</td><td>:</td><td>${escapeHtml(data.buyer?.destination?.phone || "-")}</td></tr>
                            </table>
                        </div>
                        <div style="width: 48%; border-left: 1px solid #000; padding-left: 15px;">
                            <table style="width: 100%; font-size: 12px;">
                                <tr><td colspan="3"><strong>Ditujukan Kepada Penyalur:</strong></td></tr>
                                <tr><td colspan="3"><strong>${escapeHtml(data.seller?.name || "PT DEENYE BERKAH ABADI")}</strong></td></tr>
                                <tr><td colspan="3">${escapeHtml(data.seller?.origin?.address || "-")}, ${escapeHtml(data.seller?.origin?.subdistrict?.name || "-")}, ${escapeHtml(data.seller?.origin?.district?.name || "-")}, ${escapeHtml(data.seller?.origin?.city?.name || "-")}, ${escapeHtml(data.seller?.origin?.province?.name || "-")}</td></tr>
                                <tr><td style="width: 80px;">Email / Telp</td><td style="width: 10px;">:</td><td>${escapeHtml(data.seller?.origin?.phone || "-")}</td></tr>
                            </table>
                        </div>
                    </div>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;" border="1">
                        <thead>
                            <tr style="background-color: #f2f2f2;">
                                <th style="text-align: center; padding: 8px;">No</th>
                                <th style="text-align: left; padding: 8px;">Nama Produk / Varian</th>
                                <th style="text-align: center; padding: 8px;">Kemasan<br>/ Isi</th>
                                <th style="text-align: center; padding: 8px;">No. BPOM</th>
                                <th style="text-align: center; padding: 8px;">Jumlah</th>
                                <th style="text-align: left; padding: 8px;">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${(data.details || [])
                                .map(
                                    (item: any, index: number) => `
                                <tr>
                                    <td style="text-align: center; padding: 8px;">${index + 1}</td>
                                    <td style="padding: 8px;">${escapeHtml(item.product?.name)}</td>
                                    <td style="text-align: center; padding: 8px;">-</td>
                                    <td style="text-align: center; padding: 8px;">${escapeHtml(item.product?.bpom_number || "-")}</td>
                                    <td style="text-align: center; padding: 8px;">${item.quantity} pcs</td>
                                    <td style="padding: 8px;">-</td>
                                </tr>
                            `,
                                )
                                .join("")}
                        </tbody>
                    </table>

                    <div style="margin-bottom: 30px;">
                        <strong>Ketentuan Pengiriman:</strong><br>
                        1. Wajib melampirkan Faktur Penjualan & Surat Jalan resmi yang memuat Nomor Batch.<br>
                        2. Alamat Pengiriman: ${escapeHtml(data.buyer?.destination?.address || "-")}, ${escapeHtml(data.buyer?.destination?.subdistrict?.name || "-")}, ${escapeHtml(data.buyer?.destination?.district?.name || "-")}, ${escapeHtml(data.buyer?.destination?.city?.name || "-")}, ${escapeHtml(data.buyer?.destination?.province?.name || "-")}<br>
                        3. PIC Penerima di Lokasi: ${escapeHtml(data.buyer?.destination?.name || "-")} (No. HP: ${escapeHtml(data.buyer?.destination?.phone || "-")})<br>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between;">
                        <div style="width: 50%;">
                            Kota Pemesan, ........................ 20...<br>
                            <strong>Pemesan,</strong><br><br><br><br>
                            (....................................................)<br>
                            Penanggung Jawab Sarana / Stempel
                        </div>
                    </div>
                </div>
                `;
            } else if (type === "invoice") {
                printContent = `
                <div style="font-family: sans-serif; max-width: 100%; margin: 0 auto; padding: 20px; font-size: 10px; color: #000;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid black; padding-bottom: 5px; margin-bottom: 10px;">
                        <h2 style="margin: 0; font-size: 18px;">PT.Deenye Berkah Abadi</h2>
                        <h2 style="margin: 0; font-size: 18px;">FAKTUR PENJUALAN</h2>
                        <div style="font-size: 14px;">NO FAKTUR : <strong>___________________</strong></div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding-bottom: 10px;">
                        <div style="width: 25%;">
                            <table style="width: 100%; font-size: 11px;">
                                <tr><td style="width: 80px; vertical-align: top;">Ijin Cbg PBF</td><td style="vertical-align: top;">:</td><td style="vertical-align: top;">-</td></tr>
                                <tr><td style="vertical-align: top;">Ijin Cbg PAK</td><td style="vertical-align: top;">:</td><td style="vertical-align: top;">-</td></tr>
                                <tr><td style="vertical-align: top;">Srt. CDOB OL</td><td style="vertical-align: top;">:</td><td style="vertical-align: top;">-</td></tr>
                                <tr><td style="vertical-align: top;">Srt. CDOB CCP</td><td style="vertical-align: top;">:</td><td style="vertical-align: top;">-</td></tr>
                            </table>
                        </div>
                        <div style="width: 25%;">
                            <table style="width: 100%; font-size: 11px;">
                                <tr><td style="width: 60px;">Telp</td><td>:</td><td>-</td></tr>
                                <tr><td>NPWP DNY</td><td>:</td><td>-</td></tr>
                                <tr><td>NPWP Lggn</td><td>:</td><td>-</td></tr>
                            </table>
                        </div>
                        <div style="width: 25%;">
                            <table style="width: 100%; font-size: 11px;">
                                <tr><td style="width: 60px;">Tanggal</td><td>:</td><td>${escapeHtml(formatDate(data.ordered_at))}</td></tr>
                                <tr><td>Jth Tempo</td><td>:</td><td>-</td></tr>
                                <tr><td>TOP</td><td>:</td><td>30 Hari</td></tr>
                                <tr><td>No SP</td><td>:</td><td>${escapeHtml(data.shipping?.delivery_note_number || "-")}</td></tr>
                                <tr><td>No DO</td><td>:</td><td>-</td></tr>
                                <tr><td>Kd Lggn</td><td>:</td><td>${escapeHtml(data.buyer?.code)}</td></tr>
                            </table>
                        </div>
                        <div style="width: 25%;">
                            <strong>Kepada Yth.</strong><br>
                            ${escapeHtml(data.buyer?.name)}<br>
                            ${escapeHtml(data.buyer?.destination?.address || "-")}, ${escapeHtml(data.buyer?.destination?.subdistrict?.name || "-")}, ${escapeHtml(data.buyer?.destination?.district?.name || "-")}, ${escapeHtml(data.buyer?.destination?.city?.name || "-")}, ${escapeHtml(data.buyer?.destination?.province?.name || "-")}
                        </div>
                    </div>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; border-bottom: 1px dashed black;" border="0">
                        <thead>
                            <tr style="border-top: 1px dashed black; border-bottom: 1px dashed black;">
                                <th style="text-align: left; padding: 4px;">JUMLAH</th>
                                <th style="text-align: left; padding: 4px;">KODE BARANG</th>
                                <th style="text-align: left; padding: 4px;">NAMA BARANG</th>
                                <th style="text-align: left; padding: 4px;">BATCH</th>
                                <th style="text-align: left; padding: 4px;">ED</th>
                                <th style="text-align: right; padding: 4px;">HARGA SATUAN</th>
                                <th style="text-align: right; padding: 4px;">GROSS</th>
                                <th style="text-align: right; padding: 4px;">DISC(%)</th>
                                <th style="text-align: right; padding: 4px;">SUB TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${(data.details || [])
                                .flatMap((item: any) => {
                                    if (
                                        item.product?.batches &&
                                        item.product.batches.length > 0
                                    ) {
                                        return item.product.batches.map(
                                            (batch: any) => ({
                                                code: item.product.code,
                                                name: item.product.name,
                                                batch_number:
                                                    batch.batch_number,
                                                expiry_date: batch.expiry_date,
                                                quantity: batch.quantity,
                                                price: item.price,
                                                discount_percent:
                                                    item.discount_percent || 0,
                                                net_price:
                                                    item.net_price ||
                                                    item.price,
                                            }),
                                        );
                                    }
                                    return [
                                        {
                                            code: item.product?.code,
                                            name: item.product?.name,
                                            batch_number:
                                                item.product?.batch_number ||
                                                null,
                                            expiry_date:
                                                item.product?.expiry_date ||
                                                null,
                                            quantity: item.quantity,
                                            price: item.price,
                                            discount_percent:
                                                item.discount_percent || 0,
                                            net_price:
                                                item.net_price || item.price,
                                        },
                                    ];
                                })
                                .map(
                                    (row: any) => `
                                <tr>
                                    <td style="padding: 4px;">${row.quantity} Pcs</td>
                                    <td style="padding: 4px;">${escapeHtml(row.code)}</td>
                                    <td style="padding: 4px;">${escapeHtml(row.name)}</td>
                                    <td style="padding: 4px;">${escapeHtml(row.batch_number || "-")}</td>
                                    <td style="padding: 4px;">${row.expiry_date ? escapeHtml(formatDate(row.expiry_date)) : "-"}</td>
                                    <td style="text-align: right; padding: 4px;">Rp${formatPrice(row.price)}</td>
                                    <td style="text-align: right; padding: 4px;">Rp${formatPrice(row.price * row.quantity)}</td>
                                    <td style="text-align: right; padding: 4px;">${Number(row.discount_percent).toFixed(2)}</td>
                                    <td style="text-align: right; padding: 4px;">Rp${formatPrice(row.net_price * row.quantity)}</td>
                                </tr>
                            `,
                                )
                                .join("")}
                        </tbody>
                    </table>

                    <div style="border-bottom: 1px dashed black; padding-bottom: 5px; margin-bottom: 5px;">
                        <em>Terbilang : ....................................................</em>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between;">
                        <div style="width: 25%; font-size: 10px;">
                            Produk, Jumlah, Batch, Harga dan Kondisi Barang telah diperiksa & sesuai
                            <br><br><br><br><br><br><br>
                            <div style="border-top: 1px solid black; text-align: center; width: 80%;">Penerima</div>
                        </div>
                        <div style="width: 40%; font-size: 10px; border-left: 1px dashed black; border-right: 1px dashed black; padding: 0 10px;">
                            <strong>*</strong> Pembayaran Cek/Giro (an PT.Deenye Berkah Abadi), baru dianggap lunas setelah diuangkan / dipindahbukukan.<br>
                            <strong>*</strong> Barang yang telah diserahkan tidak dapat ditukar dengan barang lain / dikembalikan, kecuali ada perjanjian tertulis sebelumnya & barang kadaluarsa.<br>
                            Rek. a/n PT Deenye Berkah Abadi - Mandiri : 1410001 777762
                        </div>
                        <div style="width: 15%; text-align: center; font-size: 10px; padding: 0 10px;">
                            Png. Jawab PBF
                            <br><br><br><br><br><br><br>
                            <div style="border-top: 1px solid black;">apt. ________________</div>
                        </div>
                        <div style="width: 20%; font-size: 10px; padding-left: 10px;">
                            <table style="width: 100%; text-align: right;">
                                <tr><td style="text-align: left;">Gross</td><td>Rp</td><td>${formatPrice(data.totals?.product_total || 0)}</td></tr>
                                <tr><td style="text-align: left;">Discount</td><td>Rp</td><td>${formatPrice(data.totals?.discount_value || 0)}</td></tr>
                                <tr><td style="text-align: left;">Subtotal</td><td>Rp</td><td>${formatPrice((data.totals?.product_total || 0) - (data.totals?.discount_value || 0))}</td></tr>
                                <tr><td style="text-align: left;">Cash Disc 0.00%</td><td>Rp</td><td>0</td></tr>
                                <tr><td style="text-align: left;">Netto</td><td>Rp</td><td>${formatPrice((data.totals?.product_total || 0) - (data.totals?.discount_value || 0))}</td></tr>
                                <tr><td style="text-align: left;">PPN</td><td>Rp</td><td>0</td></tr>
                                <tr style="font-weight: bold; border-top: 1px dashed black;"><td style="text-align: left;">Harus Dibayar</td><td>Rp</td><td>${formatPrice(data.totals?.grand_total || 0)}</td></tr>
                                <tr><td style="text-align: left;">DPP Lain</td><td>Rp</td><td>0</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                `;
            }

            const printStyles =
                type === "invoice"
                    ? "<style>@media print { @page { size: landscape; margin: 1cm; } body { -webkit-print-color-adjust: exact; } }</style>"
                    : "<style>@media print { body { -webkit-print-color-adjust: exact; } }</style>";

            const printWindow = window.open("", "_blank");
            if (printWindow) {
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Cetak Dokumen - ${escapeHtml(data.code)}</title>
                            ${printStyles}
                        </head>
                        <body onload="window.print(); window.close();">
                            ${printContent}
                        </body>
                    </html>
                `);
                printWindow.document.close();
            }
        }
    } catch (error) {
        console.error("Failed to print document:", error);
        snackbar.showMessage("Gagal mencetak dokumen", "error");
    } finally {
        printing.value = null;
    }
};

defineExpose({ open, close });
</script>

<style scoped>
.gap-2 {
    gap: 8px;
}
.gap-4 {
    gap: 16px;
}
.gap-6 {
    gap: 24px;
}
</style>
