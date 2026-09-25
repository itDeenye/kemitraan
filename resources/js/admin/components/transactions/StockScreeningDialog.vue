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
                                    {{ formatLocation(order.seller?.origin) }}
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
                                        Tujuan barang: {{ order.buyer?.destination?.name || "-" }}
                                        <br />
                                        {{ formatLocation(order.buyer?.destination) }}
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
                                    <template
                                        v-if="isPickup"
                                    >
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

            <v-card-actions
                class="pa-4"
                v-if="
                    order?.actions?.can_approve_stock_screening ||
                    order?.actions?.can_reject_stock_screening
                "
            >
                <v-spacer></v-spacer>
                <v-btn
                    v-if="order?.actions?.can_reject_stock_screening"
                    color="error"
                    variant="outlined"
                    class="rounded-lg px-4"
                    @click="openActionDialog('reject')"
                    :disabled="submitting"
                >
                    Tolak Pesanan
                </v-btn>

                <v-btn
                    v-if="order?.actions?.can_approve_stock_screening"
                    color="success"
                    variant="outlined"
                    class="rounded-lg px-6"
                    @click="openActionDialog('approve')"
                    :disabled="submitting"
                >
                    Setujui Pesanan
                </v-btn>
            </v-card-actions>
            <v-card-actions class="pa-6 pt-4 border-t" v-else>
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

    <v-dialog v-model="actionDialog" max-width="500" persistent>
        <v-card class="rounded-xl pa-2">
            <v-card-title class="text-h6 font-weight-bold text-center pt-4">
                {{
                    actionType === "approve"
                        ? "Persetujuan Stok"
                        : "Penolakan Stok"
                }}
            </v-card-title>
            <v-card-text class="text-center pb-6">
                <p class="text-body-1 mb-4 text-medium-emphasis">
                    {{
                        actionType === "approve"
                            ? "Apakah Anda yakin stok produk sudah tersedia dan sesuai untuk pesanan ini?"
                            : "Berikan alasan penolakan pesanan ini (misal: stok tidak mencukupi)."
                    }}
                </p>
                <v-textarea
                    v-model="actionNote"
                    label="Catatan (Opsional)"
                    variant="outlined"
                    density="comfortable"
                    hide-details
                    rows="3"
                ></v-textarea>
            </v-card-text>
            <v-card-actions class="justify-center pb-6 px-6">
                <v-btn
                    variant="outlined"
                    color="grey-darken-1"
                    class="flex-grow-1"
                    @click="closeActionDialog"
                    :disabled="submitting"
                >
                    Batal
                </v-btn>
                <v-btn
                    :color="actionType === 'approve' ? 'success' : 'error'"
                    variant="flat"
                    class="flex-grow-1"
                    @click="submitAction"
                    :loading="submitting"
                >
                    {{ actionType === "approve" ? "Setujui" : "Tolak" }}
                </v-btn>
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

const { formatDateTime, formatPrice, formatLocation } = useFormatter();
const snackbar = useSnackbarStore();
const emit = defineEmits(["updated"]);

const dialog = ref(false);
const loading = ref(false);

const submitting = ref(false);
const order = ref<any>(null);
const isPickup = computed(
    () => (order.value?.shipping?.method || order.value?.shipping_method) === "pickup",
);

const actionDialog = ref(false);
const actionType = ref<"approve" | "reject">("approve");
const actionNote = ref("");

const open = async (id: number | string) => {
    dialog.value = true;
    await fetchDetail(id);
};

const close = () => {
    dialog.value = false;
    order.value = null;
    actionDialog.value = false;
    actionNote.value = "";
};

const fetchDetail = async (id: number | string) => {
    loading.value = true;
    try {
        const data = await transactionOrderService.getDetail(id);
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

const openActionDialog = (type: "approve" | "reject") => {
    actionType.value = type;
    actionNote.value = "";
    actionDialog.value = true;
};

const closeActionDialog = () => {
    actionDialog.value = false;
    actionNote.value = "";
};

const submitAction = async () => {
    if (!order.value?.id) return;

    submitting.value = true;
    try {
        if (actionType.value === "approve") {
            await transactionOrderService.approveScreening(
                order.value.id,
                actionNote.value,
            );
            snackbar.showMessage("Pesanan berhasil disetujui", "success");
        } else {
            await transactionOrderService.rejectScreening(
                order.value.id,
                actionNote.value,
            );
            snackbar.showMessage("Pesanan ditolak", "warning");
        }

        closeActionDialog();
        emit("updated");
        close();
    } catch (error) {
        console.error(`Failed to ${actionType.value} screening:`, error);
        snackbar.showMessage(`Gagal memproses screening stok`, "error");
    } finally {
        submitting.value = false;
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
