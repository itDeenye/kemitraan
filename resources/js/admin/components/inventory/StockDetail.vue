<template>
    <v-dialog v-model="dialog" max-width="500" scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28">mdi-package</v-icon>
                    <span class="text-h6 font-weight-bold text-primary">
                        Detail Stok Produk
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

                <div v-else-if="stock">
                    <div
                        class="d-flex flex-column align-center text-center mb-6 pt-4"
                    >
                        <v-avatar
                            color="primary-lighten-4"
                            size="80"
                            class="mb-4 elevation-1 text-primary"
                        >
                            <v-icon size="40"
                                >mdi-package-variant-closed</v-icon
                            >
                        </v-avatar>
                        <h3 class="text-h6 font-weight-bold mb-1">
                            {{ stock.product?.name }}
                        </h3>
                        <p class="text-body-2 text-medium-emphasis mb-3">
                            {{ stock.product?.code }}
                        </p>
                    </div>

                    <h4
                        class="text-body-2 font-weight-bold mb-3 d-flex align-center ga-2 text-primary text-uppercase"
                        style="letter-spacing: 0.5px"
                    >
                        <v-icon size="18">mdi-information-outline</v-icon>
                        Informasi Stok
                    </h4>
                    <v-card variant="outlined" class="rounded-lg bg-surface">
                        <v-list density="compact" class="bg-transparent pa-2">
                            <v-list-item>
                                <template #prepend>
                                    <v-icon
                                        color="grey-darken-1"
                                        size="18"
                                        class="mr-3"
                                        >mdi-tag-outline</v-icon
                                    >
                                </template>
                                <v-list-item-title
                                    class="text-body-2 text-medium-emphasis"
                                    >Kategori</v-list-item-title
                                >
                                <template #append>
                                    <v-chip
                                        size="small"
                                        variant="tonal"
                                        color="primary"
                                    >
                                        {{
                                            stock.product?.category_name || "-"
                                        }}
                                    </v-chip>
                                </template>
                            </v-list-item>
                            <v-list-item>
                                <template #prepend>
                                    <v-icon
                                        color="grey-darken-1"
                                        size="18"
                                        class="mr-3"
                                        >mdi-scale-balance</v-icon
                                    >
                                </template>
                                <v-list-item-title
                                    class="text-body-2 text-medium-emphasis"
                                    >Sisa Stok</v-list-item-title
                                >
                                <template #append>
                                    <span
                                        class="text-body-2 font-weight-bold text-primary"
                                        >{{ formatPrice(stock.balance) }}
                                        {{ stock.product?.unit }}</span
                                    >
                                </template>
                            </v-list-item>
                            <v-list-item>
                                <template #prepend>
                                    <v-icon
                                        color="success"
                                        size="18"
                                        class="mr-3"
                                        >mdi-arrow-down-bold-circle-outline</v-icon
                                    >
                                </template>
                                <v-list-item-title
                                    class="text-body-2 text-medium-emphasis"
                                    >Total Masuk</v-list-item-title
                                >
                                <template #append>
                                    <span
                                        class="text-body-2 font-weight-medium text-success"
                                        >{{ stock.transfer_in }}
                                        {{ stock.product?.unit }}</span
                                    >
                                </template>
                            </v-list-item>
                            <v-list-item>
                                <template #prepend>
                                    <v-icon color="error" size="18" class="mr-3"
                                        >mdi-arrow-up-bold-circle-outline</v-icon
                                    >
                                </template>
                                <v-list-item-title
                                    class="text-body-2 text-medium-emphasis"
                                    >Total Keluar</v-list-item-title
                                >
                                <template #append>
                                    <span
                                        class="text-body-2 font-weight-medium text-error"
                                        >{{ stock.transfer_out }}
                                        {{ stock.product?.unit }}</span
                                    >
                                </template>
                            </v-list-item>
                        </v-list>
                    </v-card>
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
import type { WarehouseStock } from "@/admin/types/inventory";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatPrice } = useFormatter();
const dialog = ref(false);
const loading = ref(false);
const stock = ref<WarehouseStock | null>(null);

const open = async (id: number) => {
    dialog.value = true;
    loading.value = true;
    stock.value = null;

    try {
        const response = await inventoryService.getStockDetail(id);
        stock.value = response.data?.data || response.data;
    } catch (error) {
        console.error("Failed to fetch stock details:", error);
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
