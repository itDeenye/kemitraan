<template>
    <v-dialog v-model="dialog" max-width="800" scrollable persistent>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-avatar color="primary" variant="tonal" size="48">
                        <v-icon>mdi-finance</v-icon>
                    </v-avatar>
                    <div>
                        <div class="text-h6 font-weight-bold">
                            Detail Transaksi Penjualan
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ detailData?.code || "Memuat..." }}
                        </div>
                    </div>
                </div>
                <div class="d-flex align-center ga-2">
                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        size="small"
                        @click="close"
                    />
                </div>
            </v-card-title>

            <v-card-text class="pa-4" style="max-height: 70vh">
                <div v-if="loadingDetail" class="d-flex justify-center py-12">
                    <v-progress-circular
                        indeterminate
                        color="primary"
                        size="48"
                        width="4"
                    />
                </div>
                <div
                    v-else-if="!detailData"
                    class="text-center py-12 text-medium-emphasis"
                >
                    <v-icon size="48" class="mb-2"
                        >mdi-alert-circle-outline</v-icon
                    >
                    <div>Gagal memuat detail laporan</div>
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
                                    Status Transaksi
                                </div>
                                <BaseBadge
                                    type="transaction_status"
                                    :value="detailData.status"
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
                                    :value="detailData.is_preorder"
                                />
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-caption text-medium-emphasis mb-1">
                                Tanggal Transaksi
                            </div>
                            <div class="text-body-2 font-weight-medium">
                                {{ formatDateTime(detailData.ordered_at) }}
                            </div>
                        </div>
                    </div>

                    <PreorderChainInfo
                        v-if="detailData.preorder"
                        :preorder="detailData.preorder"
                    />

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
                                <div class="text-body-2 font-weight-medium">
                                    {{ detailData.seller?.name || "-" }}
                                </div>
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    {{ detailData.seller?.code || "-" }}
                                </div>
                                <div
                                    v-if="detailData.seller?.origin?.address"
                                    class="text-caption text-medium-emphasis mt-2 mb-1 d-flex"
                                >
                                    <v-icon size="x-small" class="mr-1 mt-1"
                                        >mdi-map-marker-outline</v-icon
                                    >
                                    {{
                                        formatLocation(
                                            detailData.seller?.origin,
                                        )
                                    }}
                                </div>
                                <v-chip
                                    size="x-small"
                                    variant="tonal"
                                    color="primary"
                                    class="mt-1 font-weight-medium text-capitalize"
                                >
                                    {{ detailData.seller?.type || "Unknown" }}
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
                                    {{ detailData.buyer?.name || "-" }}
                                </div>
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    {{ detailData.buyer?.code || "-" }}
                                </div>
                                <div
                                    v-if="
                                        detailData.buyer?.destination?.address
                                    "
                                    class="text-caption text-medium-emphasis mt-2 mb-1 d-flex"
                                >
                                    <v-icon size="x-small" class="mr-1 mt-1"
                                        >mdi-map-marker-outline</v-icon
                                    >
                                    {{
                                        formatLocation(
                                            detailData.buyer?.destination,
                                        )
                                    }}
                                </div>
                                <v-chip
                                    size="x-small"
                                    variant="tonal"
                                    color="info"
                                    class="mt-1 font-weight-medium text-capitalize"
                                >
                                    {{ detailData.buyer?.type || "Unknown" }}
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
                                        v-for="item in detailData.details"
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
                                            {{
                                                formatPrice(
                                                    item.net_price ||
                                                        item.price,
                                                )
                                            }}
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
                                                detailData.totals
                                                    ?.product_total || 0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (detailData.totals?.discount_value ||
                                            0) > 0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Diskon</span
                                    >
                                    <span class="font-weight-medium text-error"
                                        >-Rp
                                        {{
                                            formatPrice(
                                                detailData.totals
                                                    ?.discount_value || 0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (detailData.totals?.voucher_value ||
                                            0) > 0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Potongan Voucher</span
                                    >
                                    <span class="font-weight-medium text-error"
                                        >-Rp
                                        {{
                                            formatPrice(
                                                detailData.totals
                                                    ?.voucher_value || 0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (detailData.totals?.shipping_cost ||
                                            0) > 0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Ongkos Kirim</span
                                    >
                                    <span class="font-weight-medium"
                                        >Rp{{
                                            formatPrice(
                                                detailData.totals
                                                    ?.shipping_cost || 0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (detailData.totals
                                            ?.shipping_cost_insurance || 0) > 0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Asuransi Pengiriman</span
                                    >
                                    <span class="font-weight-medium"
                                        >Rp{{
                                            formatPrice(
                                                detailData.totals
                                                    ?.shipping_cost_insurance ||
                                                    0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (detailData.totals?.payment_charge ||
                                            0) > 0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Biaya Layanan</span
                                    >
                                    <span class="font-weight-medium"
                                        >Rp{{
                                            formatPrice(
                                                detailData.totals
                                                    ?.payment_charge || 0,
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
                                                detailData.totals
                                                    ?.grand_total || 0,
                                            )
                                        }}</span
                                    >
                                </div>
                            </div>
                        </v-card>
                    </div>

                    <v-row v-if="detailData.payment || detailData.shipping">
                        <v-col
                            cols="12"
                            sm="6"
                            class="d-flex flex-column"
                            v-if="detailData.payment"
                        >
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
                                        v-if="detailData.payment"
                                    >
                                        <span class="text-medium-emphasis"
                                            >Status Pembayaran</span
                                        >
                                        <BaseBadge
                                            type="payment_status"
                                            :value="detailData.payment.status"
                                            align="end"
                                        />
                                    </div>
                                    <template v-if="detailData.payment?.bank">
                                        <div
                                            class="d-flex justify-space-between mt-2 pt-2 border-t"
                                        >
                                            <span class="text-medium-emphasis"
                                                >Bank Tujuan</span
                                            >
                                            <span class="font-weight-medium">{{
                                                detailData.payment.bank.name ||
                                                "-"
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
                                                        detailData.payment.bank
                                                            .account_number ||
                                                        "-"
                                                    }}
                                                </div>
                                                <div
                                                    class="text-caption text-medium-emphasis"
                                                >
                                                    a/n
                                                    {{
                                                        detailData.payment.bank
                                                            .account_name || "-"
                                                    }}
                                                </div>
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </v-card>
                        </v-col>

                        <v-col
                            cols="12"
                            sm="6"
                            class="d-flex flex-column"
                            v-if="detailData.shipping"
                        >
                            <h3
                                class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                            >
                                <v-icon size="small" color="primary">{{
                                    detailData.shipping?.method === "pickup" ||
                                    detailData.shipping_method === "pickup"
                                        ? "mdi-store-outline"
                                        : "mdi-truck-delivery-outline"
                                }}</v-icon>
                                {{
                                    detailData.shipping?.method === "pickup" ||
                                    detailData.shipping_method === "pickup"
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
                                    <template
                                        v-if="
                                            detailData.shipping?.method ===
                                                'pickup' ||
                                            detailData.shipping_method ===
                                                'pickup'
                                        "
                                    >
                                        <div
                                            class="d-flex flex-column ga-1 mt-1"
                                        >
                                            <div
                                                class="font-weight-medium text-body-2"
                                            >
                                                {{
                                                    detailData.seller?.origin
                                                        ?.name ||
                                                    detailData.seller?.name ||
                                                    "-"
                                                }}
                                            </div>
                                            <div
                                                class="text-medium-emphasis text-body-2 line-height-relaxed"
                                                style="line-height: 1.5"
                                            >
                                                {{
                                                    formatLocation(
                                                        detailData.seller
                                                            ?.origin,
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
                                                    detailData.shipping
                                                        ?.method ||
                                                    detailData.shipping_method
                                                "
                                                align="end"
                                            />
                                        </div>
                                        <div
                                            class="d-flex justify-space-between"
                                            v-if="detailData.shipping?.courier"
                                        >
                                            <span class="text-medium-emphasis"
                                                >Kurir</span
                                            >
                                            <span
                                                class="font-weight-medium text-capitalize text-truncate text-right"
                                                style="max-width: 180px"
                                                :title="
                                                    detailData.shipping
                                                        .courier +
                                                    (detailData.shipping.service
                                                        ? ` (${detailData.shipping.service})`
                                                        : '')
                                                "
                                            >
                                                {{
                                                    detailData.shipping.courier
                                                }}
                                                {{
                                                    detailData.shipping.service
                                                        ? `(${detailData.shipping.service})`
                                                        : ""
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="d-flex justify-space-between"
                                            v-if="
                                                detailData.shipping
                                                    ?.pickup_method
                                            "
                                        >
                                            <span class="text-medium-emphasis"
                                                >Tipe Pickup Kurir</span
                                            >
                                            <BaseBadge
                                                type="pickup_method"
                                                :value="
                                                    detailData.shipping
                                                        .pickup_method
                                                "
                                                align="end"
                                            />
                                        </div>
                                        <div
                                            class="d-flex justify-space-between"
                                            v-if="
                                                detailData.shipping
                                                    ?.tracking_number ||
                                                detailData.shipping
                                                    ?.pickup_number
                                            "
                                        >
                                            <span class="text-medium-emphasis"
                                                >No. Resi / Kode Pickup</span
                                            >
                                            <span class="font-weight-medium">{{
                                                detailData.shipping
                                                    .tracking_number ||
                                                detailData.shipping
                                                    .pickup_number
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
import { ref } from "vue";
import api from "@/shared/services/api";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import PreorderChainInfo from "@/admin/components/transactions/PreorderChainInfo.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const { formatPrice, formatDateTime, formatLocation } = useFormatter();
const snackbar = useSnackbarStore();

const dialog = ref(false);
const detailData = ref<any>(null);
const loadingDetail = ref(false);

const open = async (id: number) => {
    dialog.value = true;
    loadingDetail.value = true;
    detailData.value = null;
    try {
        const { data } = await api.get(
            `/admin/reports/partnership-sales/${id}`,
        );
        if (data?.success) {
            detailData.value = data.data;
        }
    } catch (error) {
        console.error("Gagal memuat detail", error);
        snackbar.showMessage("Gagal memuat detail laporan", "error");
    } finally {
        loadingDetail.value = false;
    }
};

const close = () => {
    dialog.value = false;
    setTimeout(() => {
        detailData.value = null;
    }, 300);
};

defineExpose({
    open,
    close,
});
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
