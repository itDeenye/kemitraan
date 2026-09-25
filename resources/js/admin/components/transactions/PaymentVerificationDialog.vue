<template>
    <v-dialog v-model="dialog" max-width="800" scrollable persistent>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-avatar color="primary" variant="tonal" size="48">
                        <v-icon>mdi-cash-check</v-icon>
                    </v-avatar>
                    <div>
                        <div class="text-h6 font-weight-bold">
                            Detail Verifikasi Pembayaran
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ payment?.code || "Memuat..." }}
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
                    v-else-if="!payment"
                    class="text-center py-12 text-medium-emphasis"
                >
                    <v-icon size="48" class="mb-2"
                        >mdi-alert-circle-outline</v-icon
                    >
                    <div>Gagal memuat detail verifikasi pembayaran</div>
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
                                    Status Pembayaran
                                </div>
                                <BaseBadge
                                    type="payment_status"
                                    :value="payment.payment.status"
                                />
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-caption text-medium-emphasis mb-1">
                                Waktu Transfer
                            </div>
                            <div class="text-body-2 font-weight-medium">
                                {{
                                    formatDateTime(
                                        payment.payment.transferred_at,
                                    )
                                }}
                            </div>
                        </div>
                    </div>

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
                                    {{ payment.seller?.name || "-" }}
                                </div>
                                <div
                                    v-if="payment.seller?.origin"
                                    class="text-caption text-medium-emphasis mt-2 mb-1 d-flex"
                                >
                                    <v-icon size="x-small" class="mr-1 mt-1"
                                        >mdi-map-marker-outline</v-icon
                                    >
                                    <div>
                                        {{
                                            formatLocation(
                                                payment.seller.origin,
                                            )
                                        }}
                                    </div>
                                </div>
                                <v-chip
                                    size="x-small"
                                    variant="tonal"
                                    color="primary"
                                    class="mt-1 font-weight-medium text-capitalize"
                                >
                                    {{ payment.seller?.type || "-" }}
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
                                    {{
                                        payment.buyer?.name || "-"
                                    }}
                                </div>
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    {{
                                        payment.buyer?.code || "-"
                                    }}
                                </div>
                                <div
                                    v-if="payment.buyer?.destination"
                                    class="text-caption text-medium-emphasis mt-2 mb-1 d-flex"
                                >
                                    <v-icon size="x-small" class="mr-1 mt-1"
                                        >mdi-map-marker-outline</v-icon
                                    >
                                    <div>
                                        <div>
                                            Tujuan barang: {{ payment.buyer.destination.name || "-" }}
                                            <br />
                                            {{ formatLocation(payment.buyer.destination) }}
                                        </div>
                                    </div>
                                </div>
                                <v-chip
                                    size="x-small"
                                    variant="tonal"
                                    color="info"
                                    class="mt-1 font-weight-medium text-capitalize"
                                >
                                    {{ payment.buyer?.type || "-" }}
                                </v-chip>
                            </v-card>
                        </v-col>
                    </v-row>

                    <div v-if="payment.details.length > 0">
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
                                        v-for="item in payment.details"
                                        :key="item.id"
                                    >
                                        <td class="py-3">
                                            <div
                                                class="font-weight-medium text-body-2 text-truncate"
                                                style="max-width: 280px"
                                                :title="item.product.name || ''"
                                            >
                                                {{ item.product.name }}
                                            </div>
                                            <div
                                                class="text-caption text-medium-emphasis"
                                            >
                                                {{ item.product.code }}
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
                            <v-divider v-if="payment.totals" />
                            <div class="pa-4" v-if="payment.totals">
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (payment.totals?.product_total || 0) > 0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Total Harga Produk</span
                                    >
                                    <span class="font-weight-medium"
                                        >Rp{{
                                            formatPrice(
                                                payment.totals?.product_total ||
                                                    0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (payment.totals?.discount_value || 0) >
                                        0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Diskon</span
                                    >
                                    <span class="font-weight-medium text-error"
                                        >-Rp
                                        {{
                                            formatPrice(
                                                payment.totals
                                                    ?.discount_value || 0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (payment.totals?.voucher_value || 0) > 0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Potongan Voucher</span
                                    >
                                    <span class="font-weight-medium text-error"
                                        >-Rp
                                        {{
                                            formatPrice(
                                                payment.totals?.voucher_value ||
                                                    0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (payment.totals?.shipping_cost || 0) > 0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Ongkos Kirim</span
                                    >
                                    <span class="font-weight-medium"
                                        >Rp{{
                                            formatPrice(
                                                payment.totals?.shipping_cost ||
                                                    0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (payment.totals
                                            ?.shipping_cost_insurance || 0) > 0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Asuransi Pengiriman</span
                                    >
                                    <span class="font-weight-medium"
                                        >Rp{{
                                            formatPrice(
                                                payment.totals
                                                    ?.shipping_cost_insurance ||
                                                    0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                    v-if="
                                        (payment.totals?.payment_charge || 0) >
                                        0
                                    "
                                >
                                    <span class="text-medium-emphasis"
                                        >Biaya Layanan</span
                                    >
                                    <span class="font-weight-medium"
                                        >Rp{{
                                            formatPrice(
                                                payment.totals
                                                    ?.payment_charge || 0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between text-subtitle-1 font-weight-bold mt-2 pt-2 border-t"
                                    v-if="
                                        (payment.totals?.grand_total || 0) >= 0
                                    "
                                >
                                    <span>Total Pembayaran</span>
                                    <span class="text-primary"
                                        >Rp{{
                                            formatPrice(
                                                payment.totals?.grand_total ||
                                                    0,
                                            )
                                        }}</span
                                    >
                                </div>
                            </div>
                        </v-card>
                    </div>

                    <PreorderChainInfo :preorder="payment.preorder" />

                    <v-row>
                        <v-col cols="12" sm="6" class="d-flex flex-column">
                            <h3
                                class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                            >
                                <v-icon size="small" color="primary"
                                    >mdi-cart-outline</v-icon
                                >
                                Informasi Pesanan
                            </h3>
                            <v-card
                                variant="outlined"
                                class="rounded-lg pa-4 flex-grow-1"
                            >
                                <div
                                    class="d-flex flex-column ga-2 text-body-2"
                                >
                                    <div
                                        class="d-flex justify-space-between align-center"
                                    >
                                        <span class="text-medium-emphasis"
                                            >Status Pesanan</span
                                        >
                                        <BaseBadge
                                            type="transaction_status"
                                            :value="payment.status"
                                            align="end"
                                        />
                                    </div>
                                    <div
                                        class="d-flex justify-space-between align-center"
                                    >
                                        <span class="text-medium-emphasis"
                                            >Tipe Pesanan</span
                                        >
                                        <BaseBadge
                                            type="transaction_type"
                                            :value="payment.is_preorder"
                                            align="end"
                                        />
                                    </div>
                                    <div class="d-flex justify-space-between">
                                        <span class="text-medium-emphasis"
                                            >Tanggal Pesanan</span
                                        >
                                        <span
                                            class="font-weight-medium text-right"
                                            >{{
                                                formatDateTime(
                                                    payment.ordered_at,
                                                )
                                            }}</span
                                        >
                                    </div>
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
                                                    payment.seller?.origin
                                                        ?.name ||
                                                    payment.seller?.name ||
                                                    "-"
                                                }}
                                            </div>
                                            <div
                                                class="text-medium-emphasis text-body-2 line-height-relaxed"
                                                style="line-height: 1.5"
                                            >
                                                {{
                                                    formatLocation(
                                                        payment.seller?.origin,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div
                                            class="d-flex justify-space-between align-center"
                                        >
                                            <span class="text-medium-emphasis"
                                                >Metode Kirim</span
                                            >
                                            <BaseBadge
                                                type="shipping_method"
                                                :value="
                                                    payment.shipping?.method ||
                                                    payment.shipping_method
                                                "
                                                align="end"
                                            />
                                        </div>
                                        <div
                                            v-if="shippingStatus"
                                            class="d-flex justify-space-between align-center"
                                        >
                                            <span class="text-medium-emphasis"
                                                >Status Pengiriman</span
                                            >
                                            <BaseBadge
                                                type="shipping_status"
                                                :value="shippingStatus"
                                                align="end"
                                            />
                                        </div>
                                        <div
                                            v-if="payment.shipping?.courier"
                                            class="d-flex justify-space-between ga-3"
                                        >
                                            <span class="text-medium-emphasis"
                                                >Kurir</span
                                            >
                                            <span
                                                class="font-weight-medium text-capitalize text-truncate text-right"
                                                style="max-width: 180px"
                                                :title="shippingCourierLabel"
                                                >{{
                                                    shippingCourierLabel
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="
                                                payment.shipping?.pickup_method
                                            "
                                            class="d-flex justify-space-between align-center"
                                        >
                                            <span class="text-medium-emphasis"
                                                >Tipe Pickup Kurir</span
                                            >
                                            <BaseBadge
                                                type="pickup_method"
                                                :value="
                                                    payment.shipping
                                                        .pickup_method
                                                "
                                                align="end"
                                            />
                                        </div>
                                        <div
                                            v-if="
                                                payment.shipping
                                                    ?.tracking_number
                                            "
                                            class="d-flex justify-space-between ga-3"
                                        >
                                            <span class="text-medium-emphasis"
                                                >No. Resi</span
                                            >
                                            <span class="font-weight-medium">{{
                                                payment.shipping.tracking_number
                                            }}</span>
                                        </div>
                                        <div
                                            v-if="
                                                payment.shipping
                                                    ?.delivery_note_number
                                            "
                                            class="d-flex justify-space-between ga-3"
                                        >
                                            <span class="text-medium-emphasis"
                                                >Surat Jalan</span
                                            >
                                            <span class="font-weight-medium">{{
                                                payment.shipping
                                                    .delivery_note_number
                                            }}</span>
                                        </div>
                                        <div
                                            v-if="shippingSchedule"
                                            class="d-flex justify-space-between ga-3"
                                        >
                                            <span class="text-medium-emphasis"
                                                >Jadwal Pickup</span
                                            >
                                            <span
                                                class="font-weight-medium text-right"
                                                >{{
                                                    formatDateTime(
                                                        shippingSchedule,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                    </template>
                                </div>
                            </v-card>
                        </v-col>
                    </v-row>

                    <v-row>
                        <v-col cols="12" md="6" class="d-flex flex-column">
                            <h3
                                class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                            >
                                <v-icon size="small" color="primary"
                                    >mdi-bank-outline</v-icon
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
                                    <div class="d-flex justify-space-between">
                                        <span class="text-medium-emphasis"
                                            >Bank Tujuan</span
                                        >
                                        <span class="font-weight-medium">{{
                                            payment.payment?.bank?.name || "-"
                                        }}</span>
                                    </div>
                                    <div class="d-flex justify-space-between">
                                        <span class="text-medium-emphasis"
                                            >Rekening Tujuan</span
                                        >
                                        <span
                                            class="font-weight-medium text-right"
                                        >
                                            <div>
                                                {{
                                                    payment.payment?.bank
                                                        ?.account_number || "-"
                                                }}
                                            </div>
                                            <div
                                                class="text-caption text-medium-emphasis"
                                            >
                                                a/n
                                                {{
                                                    payment.payment?.bank
                                                        ?.account_name || "-"
                                                }}
                                            </div>
                                        </span>
                                    </div>
                                    <div
                                        class="d-flex justify-space-between mt-2 pt-2 border-t"
                                    >
                                        <span class="text-medium-emphasis"
                                            >Nominal Transfer</span
                                        >
                                        <span class="font-weight-bold"
                                            >Rp{{
                                                formatPrice(
                                                    payment.payment.bill_amount,
                                                )
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </v-card>
                        </v-col>

                        <v-col cols="12" md="6" class="d-flex flex-column">
                            <h3
                                class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                            >
                                <v-icon size="small" color="primary"
                                    >mdi-receipt-text-outline</v-icon
                                >
                                Bukti Transfer
                            </h3>
                            <v-card
                                variant="outlined"
                                class="rounded-lg d-flex align-center justify-center overflow-hidden flex-grow-1"
                                style="min-height: 150px"
                            >
                                <div
                                    v-if="receiptImageLoading"
                                    class="d-flex align-center justify-center fill-height"
                                >
                                    <v-progress-circular
                                        indeterminate
                                        color="grey-lighten-1"
                                    />
                                </div>
                                <v-img
                                    v-else-if="receiptImageUrl"
                                    :src="receiptImageUrl"
                                    height="200"
                                    contain
                                    class="cursor-pointer"
                                    @click="openReceiptDialog(receiptImageUrl)"
                                    @error="handleReceiptImageError"
                                >
                                    <template v-slot:placeholder>
                                        <div
                                            class="d-flex align-center justify-center fill-height"
                                        >
                                            <v-progress-circular
                                                indeterminate
                                                color="grey-lighten-1"
                                            ></v-progress-circular>
                                        </div>
                                    </template>
                                </v-img>
                                <div
                                    v-else-if="payment.payment?.receipt_url"
                                    class="text-error text-body-2 pa-4 text-center"
                                >
                                    <v-icon size="32" class="mb-2"
                                        >mdi-image-broken-variant</v-icon
                                    >
                                    <div>Bukti transfer gagal dimuat</div>
                                    <v-btn
                                        class="mt-2"
                                        color="primary"
                                        size="small"
                                        variant="text"
                                        @click="
                                            loadReceiptImage(
                                                payment.payment.receipt_url,
                                            )
                                        "
                                    >
                                        Coba Lagi
                                    </v-btn>
                                </div>
                                <div
                                    v-else
                                    class="text-medium-emphasis text-body-2 pa-4 text-center"
                                >
                                    <v-icon size="32" color="grey" class="mb-2"
                                        >mdi-image-off-outline</v-icon
                                    >
                                    <div>Tidak ada bukti transfer</div>
                                </div>
                            </v-card>
                        </v-col>
                    </v-row>

                    <div v-if="payment.payment?.note">
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-2 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="primary"
                                >mdi-text-box-outline</v-icon
                            >
                            Catatan Verifikasi
                        </h3>
                        <v-card variant="outlined" class="rounded-lg pa-4">
                            <p class="text-body-2 mb-0">
                                {{ payment.payment.note }}
                            </p>
                        </v-card>
                    </div>
                </div>
            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions
                class="pa-4"
                v-if="payment?.payment.status === 'submitted'"
            >
                <v-spacer></v-spacer>
                <v-btn
                    color="error"
                    variant="outlined"
                    class="rounded-lg px-4"
                    @click="openActionDialog('reject')"
                    :disabled="submitting"
                >
                    Tolak Pembayaran
                </v-btn>

                <v-btn
                    color="success"
                    variant="outlined"
                    class="rounded-lg px-6"
                    @click="openActionDialog('approve')"
                    :disabled="submitting"
                >
                    Verifikasi Pembayaran
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
                        ? "Verifikasi Pembayaran"
                        : "Tolak Pembayaran"
                }}
            </v-card-title>
            <v-card-text class="text-center pb-6">
                <p class="text-body-1 mb-4 text-medium-emphasis">
                    {{
                        actionType === "approve"
                            ? "Apakah Anda yakin bukti transfer dan nominal sudah sesuai?"
                            : "Berikan alasan kenapa pembayaran ditolak."
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
                    {{ actionType === "approve" ? "Verifikasi" : "Tolak" }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="receiptDialog" max-width="800">
        <v-card class="rounded-lg">
            <v-toolbar
                color="transparent"
                class="position-absolute w-100"
                style="z-index: 1"
            >
                <v-spacer></v-spacer>
                <v-btn
                    icon="mdi-close"
                    variant="elevated"
                    color="white"
                    class="mr-2"
                    @click="receiptDialog = false"
                ></v-btn>
            </v-toolbar>
            <v-img
                :src="selectedReceiptUrl"
                width="100%"
                contain
                class="bg-grey-darken-4"
                style="max-height: 80vh"
            ></v-img>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import PreorderChainInfo from "@/admin/components/transactions/PreorderChainInfo.vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import transactionPaymentService from "@/admin/services/transaction-payment.service";
import type {
    TransactionPayment,
    TransactionPaymentLocation,
} from "@/admin/types/transaction-payment";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const { formatDateTime, formatPrice, formatLocation } = useFormatter();
const snackbar = useSnackbarStore();
const emit = defineEmits(["updated"]);

const dialog = ref(false);
const loading = ref(false);
const submitting = ref(false);
const payment = ref<TransactionPayment | null>(null);
const isPickup = computed(
    () => (payment.value?.shipping?.method || payment.value?.shipping_method) === "pickup",
);
const shippingStatus = computed(
    () =>
        payment.value?.shipping?.delivery_status ||
        payment.value?.shipping?.verification_status ||
        null,
);
const shippingSchedule = computed(
    () =>
        payment.value?.shipping?.pickup_schedule ||
        payment.value?.shipping?.schedule_at ||
        null,
);
const shippingCourierLabel = computed(() => {
    const courier = payment.value?.shipping?.courier;
    const service = payment.value?.shipping?.service;

    if (!courier) return "-";

    return service ? `${courier} (${service})` : courier;
});

const actionDialog = ref(false);
const actionType = ref<"approve" | "reject">("approve");
const actionNote = ref("");

const receiptDialog = ref(false);
const selectedReceiptUrl = ref("");
const receiptImageUrl = ref("");
const receiptImageLoading = ref(false);
const receiptImageIsObjectUrl = ref(false);
let receiptImageRequestId = 0;

const clearReceiptImage = () => {
    if (receiptImageIsObjectUrl.value && receiptImageUrl.value) {
        URL.revokeObjectURL(receiptImageUrl.value);
    }

    receiptImageUrl.value = "";
    receiptImageIsObjectUrl.value = false;
};

const loadReceiptImage = async (url: string | null) => {
    const requestId = ++receiptImageRequestId;
    clearReceiptImage();

    if (!url) return;

    receiptImageLoading.value = true;

    try {
        const resolvedUrl =
            await transactionPaymentService.getReceiptObjectUrl(url);

        if (requestId !== receiptImageRequestId) {
            if (resolvedUrl.startsWith("blob:")) {
                URL.revokeObjectURL(resolvedUrl);
            }

            return;
        }

        receiptImageUrl.value = resolvedUrl;
        receiptImageIsObjectUrl.value = resolvedUrl.startsWith("blob:");
    } catch (error) {
        console.error("Failed to load payment receipt:", error);
    } finally {
        receiptImageLoading.value = false;
    }
};

const handleReceiptImageError = () => {
    receiptImageRequestId += 1;
    clearReceiptImage();
};

const open = async (id: number | string) => {
    dialog.value = true;
    await fetchDetail(id);
};

const close = () => {
    receiptImageRequestId += 1;
    dialog.value = false;
    payment.value = null;
    actionDialog.value = false;
    actionNote.value = "";
    receiptDialog.value = false;
    clearReceiptImage();
};

const fetchDetail = async (id: number | string) => {
    loading.value = true;
    try {
        const data = await transactionPaymentService.getDetail(id);
        if (data) {
            payment.value = data;
            void loadReceiptImage(data.payment?.receipt_url ?? null);
        }
    } catch (error) {
        console.error("Failed to fetch payment detail:", error);
        snackbar.showMessage("Gagal memuat detail pembayaran", "error");
    } finally {
        loading.value = false;
    }
};

const openActionDialog = (type: "approve" | "reject") => {
    actionType.value = type;
    actionNote.value = type === "approve" ? "" : "";
    actionDialog.value = true;
};

const closeActionDialog = () => {
    actionDialog.value = false;
    actionNote.value = "";
};

const submitAction = async () => {
    if (!payment.value?.payment.id) return;

    submitting.value = true;
    try {
        if (actionType.value === "approve") {
            await transactionPaymentService.approve(payment.value.payment.id, {
                note: actionNote.value,
            });
            snackbar.showMessage("Pembayaran berhasil diverifikasi", "success");
        } else {
            const response = await transactionPaymentService.reject(payment.value.payment.id, {
                note: actionNote.value,
            });
            snackbar.showMessage(response.message || "Pembayaran ditolak", "warning");
        }

        closeActionDialog();
        emit("updated");
        close();
    } catch (error) {
        console.error(`Failed to ${actionType.value} payment:`, error);
        snackbar.showMessage(`Gagal memproses verifikasi pembayaran`, "error");
    } finally {
        submitting.value = false;
    }
};

const openReceiptDialog = (url: string) => {
    if (!url) return;
    selectedReceiptUrl.value = url;
    receiptDialog.value = true;
};

defineExpose({ open, close });

onBeforeUnmount(clearReceiptImage);
</script>
