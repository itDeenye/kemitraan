<template>
    <v-dialog v-model="dialog" max-width="900" scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28">mdi-package</v-icon>
                    <span class="text-h6 font-weight-bold text-primary">
                        Detail Produk
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

                <v-row v-else-if="product">
                    <v-col cols="12" md="4" class="border-e-md pr-md-6">
                        <div
                            class="d-flex flex-column align-center text-center mb-6 pt-4"
                        >
                            <v-avatar
                                color="primary"
                                size="120"
                                class="mb-4 elevation-2"
                            >
                                <v-img
                                    v-if="product.image"
                                    :src="product.image"
                                    cover
                                ></v-img>
                                <v-icon v-else size="48" color="white"
                                    >mdi-package-variant</v-icon
                                >
                            </v-avatar>
                            <h3 class="text-h6 font-weight-bold mb-1">
                                {{ product.name }}
                            </h3>
                            <p class="text-body-2 text-medium-emphasis mb-3">
                                {{ product.code }}
                            </p>
                            <div
                                class="d-flex align-center justify-center ga-2 mt-1 flex-wrap"
                            >
                                <BaseBadge
                                    inline
                                    type="category"
                                    :value="
                                        product.category?.name ||
                                        'Uncategorized'
                                    "
                                />
                                <BaseBadge
                                    inline
                                    type="status"
                                    :value="product.is_active"
                                />
                                <BaseBadge
                                    inline
                                    :color="
                                        product.is_publish ? 'primary' : 'grey'
                                    "
                                >
                                    {{
                                        product.is_publish
                                            ? "Dipublish"
                                            : "Draft"
                                    }}
                                </BaseBadge>
                            </div>
                        </div>
                    </v-col>

                    <v-col cols="12" md="8" class="pl-md-6 pt-md-6">
                        <h4
                            class="text-body-2 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3 text-uppercase"
                            style="letter-spacing: 0.5px"
                        >
                            <v-icon size="18" color="primary"
                                >mdi-cube-outline</v-icon
                            >
                            Spesifikasi
                        </h4>
                        <v-card
                            variant="flat"
                            class="rounded-lg border border-opacity-25"
                        >
                            <v-list
                                density="compact"
                                class="bg-transparent pa-2"
                            >
                                <v-list-item min-height="36">
                                    <template #prepend
                                        ><v-icon
                                            color="grey-darken-1"
                                            size="18"
                                            class="mr-3"
                                            >mdi-weight</v-icon
                                        ></template
                                    >
                                    <v-list-item-title
                                        class="text-body-2 text-medium-emphasis"
                                        >Berat</v-list-item-title
                                    >
                                    <template #append
                                        ><span
                                            class="font-weight-bold text-body-2"
                                            >{{ product.weight_grams }}g</span
                                        ></template
                                    >
                                </v-list-item>
                                <v-divider
                                    class="border-opacity-50"
                                ></v-divider>
                                <v-list-item min-height="36">
                                    <template #prepend
                                        ><v-icon
                                            color="grey-darken-1"
                                            size="18"
                                            class="mr-3"
                                            >mdi-certificate-outline</v-icon
                                        ></template
                                    >
                                    <v-list-item-title
                                        class="text-body-2 text-medium-emphasis"
                                        >Nomor BPOM</v-list-item-title
                                    >
                                    <template #append
                                        ><span
                                            class="font-weight-medium text-body-2"
                                            >{{
                                                product.bpom_number || "-"
                                            }}</span
                                        ></template
                                    >
                                </v-list-item>
                                <v-divider
                                    class="border-opacity-50"
                                ></v-divider>
                                <v-list-item min-height="36">
                                    <template #prepend
                                        ><v-icon
                                            color="grey-darken-1"
                                            size="18"
                                            class="mr-3"
                                            >mdi-shape</v-icon
                                        ></template
                                    >
                                    <v-list-item-title
                                        class="text-body-2 text-medium-emphasis"
                                        >Satuan</v-list-item-title
                                    >
                                    <template #append
                                        ><span
                                            class="font-weight-medium text-body-2"
                                            >{{ product.unit }}</span
                                        ></template
                                    >
                                </v-list-item>
                                <v-divider
                                    class="border-opacity-50"
                                ></v-divider>
                                <v-list-item min-height="36">
                                    <template #prepend
                                        ><v-icon
                                            color="grey-darken-1"
                                            size="18"
                                            class="mr-3"
                                            >mdi-ruler</v-icon
                                        ></template
                                    >
                                    <v-list-item-title
                                        class="text-body-2 text-medium-emphasis"
                                        >Dimensi (P x L x T)</v-list-item-title
                                    >
                                    <template #append
                                        ><span
                                            class="font-weight-medium text-body-2"
                                            >{{
                                                product.dimensions_cm?.length ||
                                                0
                                            }}
                                            x
                                            {{
                                                product.dimensions_cm?.width ||
                                                0
                                            }}
                                            x
                                            {{
                                                product.dimensions_cm?.height ||
                                                0
                                            }}
                                            cm</span
                                        ></template
                                    >
                                </v-list-item>
                            </v-list>
                        </v-card>
                        <h4
                            class="text-body-2 font-weight-bold mb-3 mt-6 d-flex align-center ga-2 text-grey-darken-3 text-uppercase"
                            style="letter-spacing: 0.5px"
                        >
                            <v-icon size="18" color="primary"
                                >mdi-tag-outline</v-icon
                            >
                            Harga
                        </h4>
                        <v-card
                            variant="flat"
                            class="rounded-lg border border-opacity-25"
                        >
                            <v-list
                                density="compact"
                                class="bg-transparent pa-2"
                            >
                                <v-list-item min-height="36">
                                    <template #prepend
                                        ><v-icon
                                            color="grey-darken-1"
                                            size="18"
                                            class="mr-3"
                                            >mdi-cash-multiple</v-icon
                                        ></template
                                    >
                                    <v-list-item-title
                                        class="text-body-2 text-medium-emphasis"
                                        >Harga Pelanggan</v-list-item-title
                                    >
                                    <template #append
                                        ><span
                                            class="font-weight-bold text-body-2 text-primary"
                                            >Rp{{
                                                formatPrice(
                                                    product.prices?.customer ||
                                                        0,
                                                )
                                            }}</span
                                        ></template
                                    >
                                </v-list-item>
                                <template
                                    v-for="member in product.prices?.members"
                                    :key="member.member_level_id"
                                >
                                    <v-divider
                                        class="border-opacity-50"
                                    ></v-divider>
                                    <v-list-item min-height="36">
                                        <template #prepend
                                            ><v-icon
                                                color="grey-darken-1"
                                                size="18"
                                                class="mr-3"
                                                >mdi-account-tag-outline</v-icon
                                            ></template
                                        >
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Harga
                                            {{ member.name }}</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-medium text-body-2"
                                                >Rp{{
                                                    formatPrice(
                                                        member.price || 0,
                                                    )
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                </template>
                            </v-list>
                        </v-card>
                    </v-col>

                    <v-col cols="12" class="pt-6 mt-2">
                        <v-divider class="mb-6"></v-divider>
                        <h4
                            class="text-body-2 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3 text-uppercase"
                            style="letter-spacing: 0.5px"
                        >
                            <v-icon size="18" color="primary"
                                >mdi-text-box-outline</v-icon
                            >
                            Deskripsi
                        </h4>
                        <v-card
                            variant="flat"
                            class="rounded-lg border border-opacity-25 pa-4 text-body-2 h-fit"
                        >
                            <div
                                class="text-medium-emphasis"
                                style="line-height: 1.6; white-space: pre-wrap"
                            >
                                {{
                                    product.description ||
                                    "Tidak ada deskripsi produk yang tersedia."
                                }}
                            </div>
                        </v-card>
                    </v-col>
                </v-row>
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
import type { Product } from "@/admin/types/product";
import productService from "@/admin/services/product.service";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";

import { useFormatter } from "@/shared/composables/useFormatter";

const snackbar = useSnackbarStore();
const { formatPrice } = useFormatter();

const dialog = ref(false);
const loading = ref(false);
const product = ref<any>(null);

const open = async (id: number) => {
    dialog.value = true;
    loading.value = true;
    try {
        product.value = await productService.getProduct(id);
    } catch (error) {
        console.error("Gagal mengambil detail produk", error);
        snackbar.showMessage("Gagal mengambil detail produk", "error");
        dialog.value = false;
    } finally {
        loading.value = false;
    }
};

const close = () => {
    dialog.value = false;
    setTimeout(() => {
        product.value = null;
    }, 300);
};

defineExpose({ open, close });
</script>
