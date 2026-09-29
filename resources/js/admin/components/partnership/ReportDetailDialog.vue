<template>
    <v-dialog v-model="dialog" max-width="800" scrollable persistent>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-avatar color="primary" variant="tonal" size="48">
                        <v-icon>mdi-file-document-outline</v-icon>
                    </v-avatar>
                    <div>
                        <div class="text-h6 font-weight-bold">
                            Detail Transaksi
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ report?.code || "Memuat..." }}
                        </div>
                    </div>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    @click="close"
                />
            </v-card-title>

            <v-card-text class="pa-6">
                <div v-if="loading" class="d-flex justify-center py-12">
                    <v-progress-circular indeterminate color="primary" />
                </div>

                <div v-else-if="report" class="d-flex flex-column ga-6">
                    <!-- Status dan Info Singkat -->
                    <div class="d-flex flex-wrap ga-4">
                        <v-card
                            variant="outlined"
                            class="flex-grow-1 rounded-lg"
                        >
                            <v-card-text class="pa-4">
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    Tanggal Transaksi
                                </div>
                                <div class="text-body-1 font-weight-medium">
                                    {{ formatDateTime(report.ordered_at) }}
                                </div>
                            </v-card-text>
                        </v-card>
                        <v-card
                            variant="outlined"
                            class="flex-grow-1 rounded-lg"
                        >
                            <v-card-text class="pa-4">
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    Tipe Pesanan
                                </div>
                                <div class="text-body-1 font-weight-medium">
                                    {{
                                        report.is_preorder
                                            ? "Pre-order"
                                            : "Reguler"
                                    }}
                                </div>
                            </v-card-text>
                        </v-card>
                        <v-card
                            variant="outlined"
                            class="flex-grow-1 rounded-lg"
                        >
                            <v-card-text class="pa-4">
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    Status
                                </div>
                                <BaseBadge
                                    type="transaction_status"
                                    :value="report.status"
                                />
                            </v-card-text>
                        </v-card>
                    </div>

                    <v-divider />

                    <!-- Informasi Penjual dan Pembeli -->
                    <div>
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="primary"
                                >mdi-account-circle-outline</v-icon
                            >
                            Informasi Penjual & Pembeli
                        </h3>
                        <v-row dense>
                            <v-col cols="12" md="6">
                                <v-card
                                    variant="outlined"
                                    class="rounded-lg h-100"
                                >
                                    <v-card-text class="pa-4">
                                        <div
                                            class="text-caption text-medium-emphasis mb-3"
                                        >
                                            Penjual
                                        </div>
                                        <div class="d-flex align-center ga-3">
                                            <v-avatar
                                                color="primary"
                                                variant="tonal"
                                            >
                                                {{
                                                    report.seller?.name?.charAt(
                                                        0,
                                                    ) || "-"
                                                }}
                                            </v-avatar>
                                            <div>
                                                <div
                                                    class="font-weight-bold text-body-1"
                                                >
                                                    {{
                                                        report.seller?.name ||
                                                        "-"
                                                    }}
                                                </div>
                                                <div
                                                    class="text-caption text-medium-emphasis"
                                                >
                                                    {{
                                                        partySubtitle(
                                                            report.seller,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-card
                                    variant="outlined"
                                    class="rounded-lg h-100"
                                >
                                    <v-card-text class="pa-4">
                                        <div
                                            class="text-caption text-medium-emphasis mb-3"
                                        >
                                            Pembeli
                                        </div>
                                        <div class="d-flex align-center ga-3">
                                            <v-avatar
                                                color="primary"
                                                variant="tonal"
                                            >
                                                {{
                                                    report.buyer?.name?.charAt(
                                                        0,
                                                    ) || "-"
                                                }}
                                            </v-avatar>
                                            <div>
                                                <div
                                                    class="font-weight-bold text-body-1"
                                                >
                                                    {{
                                                        report.buyer?.name ||
                                                        "-"
                                                    }}
                                                </div>
                                                <div
                                                    class="text-caption text-medium-emphasis"
                                                >
                                                    {{
                                                        partySubtitle(
                                                            report.buyer,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>
                    </div>

                    <v-divider />

                    <!-- Detail Pesanan (Produk) -->
                    <div>
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="primary"
                                >mdi-cart-outline</v-icon
                            >
                            Detail Produk
                        </h3>
                        <v-card
                            variant="outlined"
                            class="rounded-lg overflow-hidden"
                        >
                            <v-table>
                                <thead class="bg-surface-light">
                                    <tr>
                                        <th class="text-left font-weight-bold">
                                            Produk
                                        </th>
                                        <th class="text-right font-weight-bold">
                                            Harga
                                        </th>
                                        <th
                                            class="text-right font-weight-bold"
                                            style="width: 80px"
                                        >
                                            Qty
                                        </th>
                                        <th class="text-right font-weight-bold">
                                            Subtotal
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in report.details"
                                        :key="item.id"
                                    >
                                        <td class="py-3">
                                            <div
                                                class="font-weight-medium text-body-2"
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
                            <!-- Ringkasan Biaya -->
                            <div class="pa-4 bg-surface-light">
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                >
                                    <span class="text-medium-emphasis"
                                        >Subtotal Produk</span
                                    >
                                    <span class="font-weight-medium">{{
                                        formatPrice(
                                            report.totals?.product_total || 0,
                                        )
                                    }}</span>
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                >
                                    <span class="text-medium-emphasis"
                                        >Diskon</span
                                    >
                                    <span class="font-weight-medium text-error"
                                        >-
                                        {{
                                            formatPrice(
                                                report.totals?.discount_value ||
                                                    0,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-space-between mb-2 text-body-2"
                                >
                                    <span class="text-medium-emphasis"
                                        >Ongkos Kirim</span
                                    >
                                    <span class="font-weight-medium">{{
                                        formatPrice(
                                            report.totals
                                                ?.shipping_cost_total || 0,
                                        )
                                    }}</span>
                                </div>
                                <v-divider class="my-2" />
                                <div
                                    class="d-flex justify-space-between text-subtitle-1 font-weight-bold"
                                >
                                    <span>Total Akhir</span>
                                    <span class="text-primary">{{
                                        formatPrice(
                                            report.totals?.grand_total || 0,
                                        )
                                    }}</span>
                                </div>
                            </div>
                        </v-card>
                    </div>

                    <!-- Info Pengiriman jika ada -->
                    <div v-if="report.shipping_method">
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="primary"
                                >mdi-truck-delivery-outline</v-icon
                            >
                            Informasi Pengiriman
                        </h3>
                        <v-card
                            variant="outlined"
                            class="rounded-lg pa-4 bg-surface-light"
                        >
                            <div class="d-flex flex-column ga-2 text-body-2">
                                <div class="d-flex">
                                    <span
                                        class="text-medium-emphasis"
                                        style="min-width: 120px"
                                        >Metode Kirim</span
                                    >
                                    <span class="font-weight-medium">{{
                                        report.shipping_method || "-"
                                    }}</span>
                                </div>
                            </div>
                        </v-card>
                    </div>
                </div>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import partnershipSalesService from "@/admin/services/partnership-sales.service";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime, formatPrice } = useFormatter();

const dialog = ref(false);
const loading = ref(false);
const report = ref<any>(null);

const open = async (id: number | string) => {
    dialog.value = true;
    await fetchDetail(id);
};

const close = () => {
    dialog.value = false;
    report.value = null;
};

const fetchDetail = async (id: number | string) => {
    try {
        loading.value = true;
        report.value = await partnershipSalesService.getDetail(id);
    } catch (error) {
        console.error("Failed to fetch detail", error);
    } finally {
        loading.value = false;
    }
};

const partySubtitle = (party: any) => {
    if (party?.code) return party.code;

    const labels: Record<string, string> = {
        warehouse: "Gudang Pusat",
        distributor: "Distributor",
        agent: "Agent",
        reseller: "Reseller",
        customer: "Pelanggan",
    };

    return labels[party?.type] || "-";
};

defineExpose({
    open,
    close,
});
</script>
