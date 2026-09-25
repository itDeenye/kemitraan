<template>
    <v-dialog v-model="dialog" max-width="1000" scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28">{{
                        isPickup
                            ? "mdi-store-outline"
                            : "mdi-truck-delivery-outline"
                    }}</v-icon>
                    <span class="text-h6 font-weight-bold text-primary">
                        {{
                            isPickup
                                ? "Detail Pengambilan Barang"
                                : "Detail Pengiriman Barang"
                        }}
                    </span>
                </div>
                <div class="d-flex align-center ga-2">
                    <v-btn
                        v-if="order"
                        color="primary"
                        prepend-icon="mdi-printer"
                        variant="tonal"
                        size="small"
                        class="text-none font-weight-bold rounded-lg"
                        :loading="printing === 'surat-jalan'"
                        @click="printDocument('surat-jalan')"
                    >
                        Cetak Surat Jalan
                    </v-btn>
                    <v-btn
                        v-if="order && !isPickup"
                        color="info"
                        prepend-icon="mdi-printer"
                        variant="tonal"
                        size="small"
                        class="text-none font-weight-bold rounded-lg ml-2"
                        :loading="printing === 'resi'"
                        @click="printDocument('resi')"
                    >
                        Cetak Resi
                    </v-btn>
                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        color="medium-emphasis"
                        @click="close"
                        density="comfortable"
                    ></v-btn>
                </div>
            </v-card-title>

            <v-divider></v-divider>

            <v-card-text class="pa-6 pt-2 mb-2" style="max-height: 75vh">
                <div v-if="loading" class="d-flex justify-center py-12">
                    <v-progress-circular
                        indeterminate
                        color="primary"
                        size="48"
                    ></v-progress-circular>
                </div>

                <div v-else-if="order" class="d-flex flex-column ga-4">
                    <div
                        class="d-flex align-center justify-space-between flex-wrap ga-4"
                    >
                        <div class="d-flex align-center ga-4">
                            <div>
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    {{
                                        isPickup
                                            ? "Status Pengambilan"
                                            : "Status Pengiriman"
                                    }}
                                </div>
                                <BaseBadge
                                    :type="
                                        isPickup
                                            ? 'pickup_status'
                                            : 'shipping_status'
                                    "
                                    :value="
                                        order.shipping?.delivery_status ||
                                        order.shipping?.verification_status ||
                                        order.status
                                    "
                                />
                            </div>
                        </div>
                        <div class="d-flex align-center ga-4 text-right">
                            <div>
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    Kode Transaksi
                                </div>
                                <div class="text-body-2 font-weight-medium">
                                    {{ order.code || "-" }}
                                </div>
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
                                    Tanggal Transaksi
                                </div>
                                <div class="text-body-2 font-weight-medium">
                                    {{ formatDateTime(order.ordered_at) }}
                                </div>
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
                                    {{ isPickup ? "Penjual" : "Dikirim Dari" }}
                                </h3>
                                <div
                                    class="text-body-2 font-weight-medium mb-7"
                                >
                                    {{
                                        order.seller?.origin?.name ||
                                        order.seller?.name ||
                                        "-"
                                    }}
                                </div>
                                <div
                                    class="text-caption text-medium-emphasis mt-2 mb-1 d-flex"
                                >
                                    <v-icon size="x-small" class="mr-1 mt-1"
                                        >mdi-map-marker-outline</v-icon
                                    >
                                    <div>
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
                                    {{
                                        order.seller?.origin?.type ||
                                        order.seller?.type ||
                                        "Unknown"
                                    }}
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
                                        >mdi-map-marker-outline</v-icon
                                    >
                                    {{ isPickup ? "Pembeli" : "Dikirim Ke" }}
                                </h3>
                                <div class="text-body-2 font-weight-medium">
                                    {{ order.buyer?.destination?.name || "-" }}
                                </div>
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    {{ order.buyer?.destination?.code || "-" }}
                                </div>
                                <div
                                    class="text-caption text-medium-emphasis mt-2 mb-1 d-flex"
                                >
                                    <v-icon size="x-small" class="mr-1 mt-1"
                                        >mdi-map-marker-outline</v-icon
                                    >
                                    <div>
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
                                    {{
                                        order.buyer?.destination?.type ||
                                        order.buyer?.type ||
                                        "Unknown"
                                    }}
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
                            {{
                                isPickup
                                    ? "Rincian Produk yang Diambil"
                                    : "Rincian Produk yang Dikirim"
                            }}
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
                                        <th
                                            class="text-right font-weight-bold"
                                            style="width: 80px"
                                        >
                                            Qty
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in order.details"
                                        :key="item.id"
                                    >
                                        <td
                                            class="py-3"
                                            style="max-width: 250px"
                                        >
                                            <div
                                                class="font-weight-medium text-body-2 text-truncate"
                                                :title="item.product?.name"
                                            >
                                                {{ item.product?.name }}
                                            </div>
                                            <div
                                                class="text-caption text-medium-emphasis text-truncate"
                                                :title="item.product?.code"
                                            >
                                                {{ item.product?.code }}
                                            </div>
                                            <div
                                                v-for="(
                                                    batch, bIdx
                                                ) in getShippingItems(
                                                    item.product?.id,
                                                )"
                                                :key="bIdx"
                                                class="text-caption text-primary mt-1 d-flex align-center"
                                            >
                                                <v-icon size="12" class="mr-1"
                                                    >mdi-barcode</v-icon
                                                >
                                                <span class="mr-3"
                                                    >Nomor Batch:
                                                    {{
                                                        batch.batch_number ||
                                                        "-"
                                                    }}</span
                                                >
                                                <v-icon size="12" class="mr-1"
                                                    >mdi-calendar-alert</v-icon
                                                >
                                                <span class="mr-3"
                                                    >Expired Date:
                                                    {{
                                                        formatDate(
                                                            batch.expiry_date ||
                                                                "-",
                                                        )
                                                    }}</span
                                                >
                                                <span
                                                    v-if="
                                                        getShippingItems(
                                                            item.product?.id,
                                                        ).length > 1
                                                    "
                                                    class="font-weight-bold"
                                                    >(Qty:
                                                    {{
                                                        formatPrice(
                                                            batch.quantity,
                                                        )
                                                    }})</span
                                                >
                                            </div>
                                        </td>
                                        <td
                                            class="text-right text-body-2 font-weight-medium"
                                        >
                                            {{ formatPrice(item.quantity) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </v-card>
                    </div>
                    <div v-if="order.shipping">
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="primary">{{
                                isPickup
                                    ? "mdi-store-outline"
                                    : "mdi-truck-outline"
                            }}</v-icon>
                            {{
                                isPickup
                                    ? "Detail Pengambilan"
                                    : "Detail Pengiriman"
                            }}
                        </h3>
                        <v-card variant="outlined" class="rounded-lg pa-4">
                            <v-row>
                                <v-col
                                    cols="12"
                                    sm="6"
                                    v-if="order.shipping.method !== 'pickup'"
                                >
                                    <div
                                        class="text-caption text-medium-emphasis"
                                    >
                                        Kurir & Layanan
                                    </div>
                                    <div
                                        class="font-weight-medium text-capitalize"
                                    >
                                        <span v-if="order.shipping.courier"
                                            >{{ order.shipping.courier }} -
                                        </span>
                                        {{ order.shipping.service || "-" }}
                                    </div>
                                </v-col>

                                <v-col
                                    cols="12"
                                    sm="6"
                                    v-if="
                                        order.shipping.method ===
                                            'courier_express' &&
                                        order.shipping.pickup_method
                                    "
                                >
                                    <div
                                        class="text-caption text-medium-emphasis"
                                    >
                                        Tipe Pickup Kurir
                                    </div>
                                    <div class="font-weight-medium mt-1">
                                        <BaseBadge
                                            type="pickup_method"
                                            :value="
                                                order.shipping.pickup_method
                                            "
                                            size="small"
                                        />
                                    </div>
                                </v-col>

                                <v-col
                                    cols="12"
                                    sm="6"
                                    v-if="
                                        order.shipping.method ===
                                            'courier_express' &&
                                        order.shipping.pickup_schedule
                                    "
                                >
                                    <div
                                        class="text-caption text-medium-emphasis"
                                    >
                                        Jadwal Pickup Kurir
                                    </div>
                                    <div class="font-weight-medium">
                                        {{
                                            formatDateTime(
                                                order.shipping.pickup_schedule,
                                            )
                                        }}
                                    </div>
                                </v-col>

                                <v-col
                                    cols="12"
                                    sm="6"
                                    v-if="
                                        order.shipping.method ===
                                            'courier_express' &&
                                        order.shipping.pickup_number
                                    "
                                >
                                    <div
                                        class="text-caption text-medium-emphasis"
                                    >
                                        Nomor Pickup Kurir
                                    </div>
                                    <div class="font-weight-medium">
                                        {{ order.shipping.pickup_number }}
                                    </div>
                                </v-col>

                                <v-col
                                    cols="12"
                                    sm="6"
                                    v-if="order.shipping.delivery_note_number"
                                >
                                    <div
                                        class="text-caption text-medium-emphasis"
                                    >
                                        Nomor Surat Jalan
                                    </div>
                                    <div class="font-weight-medium">
                                        {{
                                            order.shipping.delivery_note_number
                                        }}
                                    </div>
                                </v-col>

                                <v-col
                                    cols="12"
                                    sm="6"
                                    v-if="order.shipping.tracking_number"
                                >
                                    <div
                                        class="text-caption text-medium-emphasis"
                                    >
                                        Nomor Resi
                                    </div>
                                    <div class="font-weight-medium">
                                        {{ order.shipping.tracking_number }}
                                    </div>
                                </v-col>
                                <v-col
                                    cols="12"
                                    sm="6"
                                    v-if="order.totals?.shipping_cost > 0"
                                >
                                    <div
                                        class="text-caption text-medium-emphasis"
                                    >
                                        Ongkos Kirim
                                    </div>
                                    <div class="font-weight-medium">
                                        Rp{{
                                            formatPrice(
                                                order.totals.shipping_cost,
                                            )
                                        }}
                                        <span
                                            v-if="
                                                order.totals
                                                    .shipping_cost_insurance
                                            "
                                        >
                                            + asuransi Rp{{
                                                formatPrice(
                                                    order.totals
                                                        .shipping_cost_insurance,
                                                )
                                            }}</span
                                        >
                                    </div>
                                </v-col>
                            </v-row>
                        </v-card>
                    </div>
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
import inventoryService from "@/admin/services/inventory.service";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import PreorderChainInfo from "@/admin/components/transactions/PreorderChainInfo.vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const { formatDate, formatDateTime, formatPrice, formatLocation } =
    useFormatter();

const snackbar = useSnackbarStore();
const dialog = ref(false);
const loading = ref(false);
const printing = ref<any>(false);
const order = ref<any>(null);
const isPickup = computed(
    () =>
        (order.value?.shipping?.method || order.value?.shipping_method) ===
        "pickup",
);

const getShippingItems = (productId: number) => {
    if (!order.value?.shipping?.items) return [];
    return order.value.shipping.items.filter(
        (i: any) => i.product_id === productId,
    );
};

const open = async (id: number) => {
    dialog.value = true;
    loading.value = true;
    order.value = null;

    try {
        const response = await inventoryService.getShipmentDetail(id);
        order.value = response.data?.data || response.data;
    } catch (error) {
        console.error("Failed to fetch shipping details:", error);
    } finally {
        loading.value = false;
    }
};

const close = () => {
    dialog.value = false;
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

const printDocument = async (type = "surat-jalan") => {
    if (!order.value?.id) return;

    printing.value = type;
    try {
        const data = order.value;
        let printContent = "";

        if (type === "surat-jalan") {
            printContent = `
                <div style="font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; font-size: 11px; color: #000;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid black; padding-bottom: 10px; margin-bottom: 10px;">
                        <div style="display: flex; align-items: center;">
                            <img src="/logo-fix.png" alt="PT DEENYE BERKAH ABADI" style="height: 45px; margin-right: 15px;">
                        </div>
                        <div style="text-align: center; flex-grow: 1;">
                            <h2 style="margin: 0; font-size: 16px;">${escapeHtml(data.seller?.name || "PT DEENYE BERKAH ABADI")}</h2>
                            <div style="font-size: 11px;">${escapeHtml(data.seller?.origin?.address || "-")}, ${escapeHtml(data.seller?.origin?.subdistrict?.name || "-")}, ${escapeHtml(data.seller?.origin?.district?.name || "-")}, ${escapeHtml(data.seller?.origin?.city?.name || "-")}, ${escapeHtml(data.seller?.origin?.province?.name || "-")}<br>Telp : ${escapeHtml(data.seller?.origin?.phone || "-")}</div>
                        </div>
                    </div>
                    
                    <div style="text-align: center; border-bottom: 1px solid black; margin-bottom: 10px; padding-bottom: 5px;">
                        <h3 style="margin: 0; font-size: 16px; letter-spacing: 2px;">SURAT JALAN</h3>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div style="width: 50%;">
                            <table style="width: 100%; font-size: 11px;">
                                <tr><td style="width: 80px;">Nomor Surat</td><td>:</td><td>${escapeHtml(data.shipping?.delivery_note_number || "-")}</td></tr>
                                <tr><td>Nomer Nota</td><td>:</td><td>..............................................</td></tr>
                                <tr><td>Nomer PO</td><td>:</td><td>${escapeHtml(data.code || "-")}</td></tr>
                            </table>
                        </div>
                        <div style="width: 50%;">
                            <table style="width: 100%; font-size: 11px;">
                                <tr><td>..........., ${escapeHtml(formatDate(data.ordered_at))}</td></tr>
                                <tr><td>Kepada Yth.</td><td></td></tr>
                                <tr><td colspan="2"><strong>${escapeHtml(data.buyer?.name)}</strong></td></tr>
                            </table>
                        </div>
                    </div>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 40px;" border="1">
                        <thead>
                            <tr style="background-color: #f2f2f2;">
                                <th style="text-align: center; padding: 4px; width: 30px;">No</th>
                                <th style="text-align: left; padding: 4px; width: 100px;">Kode Produk</th>
                                <th style="text-align: left; padding: 4px;">Nama Produk</th>
                                <th style="text-align: center; padding: 4px; width: 80px;">No. Batch</th>
                                <th style="text-align: center; padding: 4px; width: 80px;">Exp Date</th>
                                <th style="text-align: center; padding: 4px; width: 50px;">Jumlah</th>
                                <th style="text-align: center; padding: 4px; width: 50px;">Satuan</th>
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
                                        },
                                    ];
                                })
                                .map(
                                    (row: any, index: number) => `
                                <tr>
                                    <td style="text-align: center; padding: 4px;">${index + 1}</td>
                                    <td style="padding: 4px;">${escapeHtml(row.code)}</td>
                                    <td style="padding: 4px;">${escapeHtml(row.name)}</td>
                                    <td style="text-align: center; padding: 4px;">${escapeHtml(row.batch_number || "-")}</td>
                                    <td style="text-align: center; padding: 4px;">${row.expiry_date ? escapeHtml(formatDate(row.expiry_date)) : "-"}</td>
                                    <td style="text-align: center; padding: 4px;">${row.quantity}</td>
                                    <td style="text-align: center; padding: 4px;">PCS</td>
                                </tr>
                            `,
                                )
                                .join("")}
                        </tbody>
                    </table>

                    <div style="border-top: 2px solid black; padding-top: 5px; margin-bottom: 20px;">
                        <strong style="color: red; font-size: 12px;">PERHATIAN :</strong><br>
                        1. Surat Jalan ini merupakan bukti resmi pengiriman produk dan bukan bukti penjualan.<br>
                        2. Batas keluhan terhadap produk, max. 3 hari setelah produk diterima.<br>
                        3. Surat Jalan Asli kembali ke Supply and Chain, max. 3 hari setelah produk diterima (bisa berupa soft file)
                    </div>
                    
                    <table style="width: 100%; border: none; font-size: 11px;" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="width: 50%; vertical-align: top;">
                                PRODUK SUDAH DITERIMA DALAM KEADAAN BAIK DAN SESUAI oleh :<br>
                                (tanda tangan dan cap (stempel) perusahaan)
                            </td>
                            <td style="width: 50%; vertical-align: top;">
                            </td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top; padding-top: 15px;">
                                <div style="text-align: center; width: 250px;">
                                    Penerima
                                </div>
                            </td>
                            <td style="vertical-align: top; padding-top: 15px;">
                                <div style="text-align: center; width: 250px; float: right;">
                                    Tertanda,
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="vertical-align: bottom; height: 70px;">
                                <div style="text-align: center; width: 250px;">
                                    (_______________________________________)
                                </div>
                            </td>
                            <td style="vertical-align: bottom; height: 70px;">
                                <div style="text-align: center; width: 250px; float: right;">
                                    (_______________________________________)
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">
                            </td>
                            <td style="vertical-align: top;">
                                <div style="text-align: center; width: 250px; float: right;">
                                    Apoteker Penanggung Jawab
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
        `;
        } else if (type === "resi") {
            const totalWeight =
                data.details?.reduce(
                    (acc: number, item: any) =>
                        acc + item.quantity * (item.product?.weight || 0),
                    0,
                ) || 1000;
            const weightInKg = Math.max(1, Math.ceil(totalWeight / 1000));
            const shippingCost =
                data.shipping?.shipping_cost ||
                data.totals?.shipping_cost_total ||
                0;

            printContent = `
                <div style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 10px; font-size: 11px; color: #000; border: 1px solid #ddd;">
                    <!-- Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid black; padding-bottom: 10px; margin-bottom: 10px;">
                        <div style="font-size: 24px; font-weight: bold; color: red;">
                            ${escapeHtml(data.shipping?.courier?.toUpperCase() || "EKSPEDISI")}
                        </div>
                        <div style="text-align: right;">
                            <img src="https://barcode.tec-it.com/barcode.ashx?data=${escapeHtml(data.code)}&code=Code128&dpi=96" alt="Barcode" style="height: 40px;"/>
                        </div>
                    </div>
                    
                    <!-- Addresses -->
                    <div style="margin-bottom: 10px; border-bottom: 1px solid black; padding-bottom: 10px;">
                        <table style="width: 100%; font-size: 12px; line-height: 1.5;">
                            <tr>
                                <td style="width: 80px; vertical-align: top; font-weight: bold;">Pengirim</td>
                                <td style="vertical-align: top;">
                                    ${escapeHtml(data.seller?.name || "PT DEENYE BERKAH ABADI")} ${escapeHtml(data.seller?.origin?.phone || "-")}<br>
                                    ${escapeHtml(data.seller?.origin?.address || "-")}, ${escapeHtml(data.seller?.origin?.subdistrict?.name || "-")}, ${escapeHtml(data.seller?.origin?.city?.name || "-")}, ${escapeHtml(data.seller?.origin?.province?.name || "-")}
                                </td>
                            </tr>
                            <tr>
                                <td style="vertical-align: top; font-weight: bold; padding-top: 10px;">Penerima</td>
                                <td style="vertical-align: top; padding-top: 10px;">
                                    ${escapeHtml(data.buyer?.destination?.name || data.buyer?.name)} ${escapeHtml(data.buyer?.destination?.phone || "-")}<br>
                                    ${escapeHtml(data.buyer?.destination?.address || "-")}, ${escapeHtml(data.buyer?.destination?.subdistrict?.name || "-")}, ${escapeHtml(data.buyer?.destination?.district?.name || "-")}, ${escapeHtml(data.buyer?.destination?.city?.name || "-")}, ${escapeHtml(data.buyer?.destination?.province?.name || "-")}
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Details & Payment -->
                    <div style="display: flex; border-bottom: 1px solid black; margin-bottom: 10px;">
                        <div style="width: 70%; border-right: 1px solid black;">
                            <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 8px; border-bottom: 1px solid black; border-right: 1px solid black; text-align: center; font-weight: bold;">${weightInKg} KG</td>
                                    <td style="padding: 8px; border-bottom: 1px solid black; border-right: 1px solid black; text-align: center; font-weight: bold;">EZ</td>
                                    <td style="padding: 8px; border-bottom: 1px solid black; border-right: 1px solid black; text-align: center; font-weight: bold;">TUNAI</td>
                                    <td style="padding: 8px; border-bottom: 1px solid black; text-align: center; font-weight: bold;">Non COD</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding: 8px; vertical-align: top;">
                                        Biaya Kirim : ${formatPrice(shippingCost)}<br>
                                        Asuransi : ${formatPrice(data.shipping?.insurance || 0)}<br>
                                        Lain-lain : 0
                                    </td>
                                    <td colspan="2" style="padding: 8px; vertical-align: middle; text-align: center;">
                                        <div style="font-weight: bold; margin-bottom: 5px;">TOTAL Biaya</div>
                                        <div style="font-weight: bold; font-size: 16px;">${formatPrice(shippingCost + (data.shipping?.insurance || 0))}</div>
                                        <div style="font-size: 10px; margin-top: 5px; color: #555;">Sudah Termasuk Pajak</div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div style="width: 30%; display: flex; align-items: center; justify-content: center; padding: 10px;">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${escapeHtml(data.tracking?.tracking_number)}" alt="QR Code" style="width: 100px; height: 100px;"/>
                        </div>
                    </div>
                    
                    <div style="background-color: #000; color: #fff; text-align: center; padding: 5px; font-weight: bold; font-size: 14px;">
                        Lembar Pengirim
                    </div>
                </div>
            `;
        }

        const printWindow = window.open("", "_blank");
        if (printWindow) {
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Cetak Surat Jalan - ${escapeHtml(data.code)}</title>
                    </head>
                    <body onload="window.print(); window.close();">
                        ${printContent}
                    </body>
                </html>
            `);
            printWindow.document.close();
        }
    } catch (error) {
        console.error("Failed to print document:", error);
        snackbar.showMessage("Gagal mencetak dokumen", "error");
    } finally {
        printing.value = false;
    }
};

defineExpose({
    open,
});
</script>
