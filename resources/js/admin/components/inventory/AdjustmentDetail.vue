<template>
    <v-dialog v-model="dialog" max-width="1000" scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28"
                        >mdi-package-variant-closed-plus</v-icon
                    >
                    <span class="text-h6 font-weight-bold text-primary">
                        Detail Penyesuaian Stok
                    </span>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    color="medium-emphasis"
                    @click="close"
                    density="comfortable"
                ></v-btn>
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

                <div v-else-if="adjustment">
                    <v-row class="mb-4">
                        <v-col cols="12" md="6">
                            <div class="text-caption text-medium-emphasis">
                                Kode Penyesuaian
                            </div>
                            <div class="font-weight-bold">
                                {{ adjustment.code || "-" }}
                            </div>
                        </v-col>
                        <v-col cols="12" md="6">
                            <div class="text-caption text-medium-emphasis">
                                Waktu Input
                            </div>
                            <div class="font-weight-bold">
                                {{ formatDateTime(adjustment.happened_at) }}
                            </div>
                        </v-col>
                        <v-col cols="12" md="6">
                            <div class="text-caption text-medium-emphasis">
                                Administrator
                            </div>
                            <div class="font-weight-bold">
                                {{ adjustment.administrator?.name }}
                            </div>
                        </v-col>
                        <v-col cols="12" md="6">
                            <div class="text-caption text-medium-emphasis">
                                Catatan
                            </div>
                            <div class="font-weight-bold">
                                {{ adjustment.note || "-" }}
                            </div>
                        </v-col>
                    </v-row>

                    <h4
                        class="text-body-2 font-weight-bold mb-3 d-flex align-center ga-2 text-primary text-uppercase"
                        style="letter-spacing: 0.5px"
                    >
                        <v-icon size="18">mdi-format-list-bulleted</v-icon>
                        Rincian Produk
                    </h4>

                    <v-table density="comfortable" class="border rounded-lg">
                        <thead class="bg-grey-lighten-4">
                            <tr>
                                <th>Produk</th>
                                <th>No Batch</th>
                                <th class="text-center">Tipe</th>
                                <th class="text-end">Qty</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(detail, i) in adjustment.details"
                                :key="i"
                            >
                                <td style="max-width: 250px">
                                    <div
                                        class="font-weight-bold text-truncate"
                                        :title="detail.product?.name"
                                    >
                                        {{ detail.product?.name }}
                                    </div>
                                    <div
                                        class="text-caption text-medium-emphasis text-truncate"
                                        :title="detail.product?.code"
                                    >
                                        {{ detail.product?.code }}
                                    </div>
                                </td>
                                <td>
                                    <span class="font-weight-medium">{{
                                        detail.batch_number || "-"
                                    }}</span>
                                </td>
                                <td class="text-center">
                                    <v-chip
                                        size="small"
                                        :color="
                                            detail.type === 'in'
                                                ? 'success'
                                                : 'error'
                                        "
                                    >
                                        {{
                                            detail.type === "in"
                                                ? "Masuk"
                                                : "Keluar"
                                        }}
                                    </v-chip>
                                </td>
                                <td class="text-end font-weight-bold">
                                    {{ formatPrice(detail.quantity) }}
                                </td>
                                <td>
                                    {{ detail.note || "-" }}
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
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
import inventoryService from "@/admin/services/inventory.service";
import type { StockAdjustment } from "@/admin/types/inventory";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime, formatPrice } = useFormatter();
const dialog = ref(false);
const loading = ref(false);
const adjustment = ref<StockAdjustment | null>(null);

const open = async (id: number) => {
    dialog.value = true;
    loading.value = true;
    adjustment.value = null;

    try {
        const response = await inventoryService.getAdjustmentDetail(id);
        adjustment.value = response.data?.data || response.data;
    } catch (error) {
        console.error("Failed to fetch adjustment details:", error);
    } finally {
        loading.value = false;
    }
};

const close = () => {
    dialog.value = false;
};

defineExpose({
    open,
});
</script>
