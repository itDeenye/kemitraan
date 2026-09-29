<template>
    <v-dialog v-model="dialog" max-width="800" persistent scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28">{{
                        isBulk ? "mdi-cash-multiple" : "mdi-cash"
                    }}</v-icon>
                    <span class="text-h6 font-weight-bold text-primary">
                        {{
                            isBulk ? "Ubah Harga Massal" : "Update Harga Produk"
                        }}
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

            <v-card-text class="pa-6 pt-5">
                <div v-if="fetching" class="d-flex justify-center py-12">
                    <v-progress-circular
                        indeterminate
                        color="primary"
                        size="48"
                    ></v-progress-circular>
                </div>
                <v-form v-else ref="formRef" @submit.prevent="save">
                    <div
                        v-if="!isBulk"
                        class="mb-6 bg-grey-lighten-4 pa-4 rounded-lg d-flex align-center ga-3"
                    >
                        <v-avatar color="white" size="48" class="elevation-1">
                            <v-icon color="primary">mdi-package-variant</v-icon>
                        </v-avatar>
                        <div>
                            <div class="text-subtitle-2 font-weight-bold">
                                {{ product?.name }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                Kode: {{ product?.code }}
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="mb-6 bg-primary-lighten-5 pa-4 rounded-lg d-flex align-center ga-3 border border-primary-lighten-3"
                    >
                        <v-avatar
                            color="primary"
                            size="48"
                            class="elevation-1 text-white"
                        >
                            <v-icon>mdi-package-variant-closed</v-icon>
                        </v-avatar>
                        <div>
                            <div
                                class="text-subtitle-2 font-weight-bold text-primary-darken-2"
                            >
                                Mengubah harga untuk
                                {{ selectedProducts.length }} produk
                            </div>
                            <div class="text-caption text-primary-darken-1">
                                Perubahan harga di bawah ini akan diterapkan
                                pada semua produk yang dipilih.
                            </div>
                        </div>
                    </div>

                    <label
                        class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                        >Harga Pelanggan
                        <span class="text-error">*</span></label
                    >
                    <v-text-field
                        :model-value="formatNumber(form.customer_price)"
                        @update:model-value="
                            form.customer_price = parseNumber($event)
                        "
                        placeholder="0"
                        variant="outlined"
                        density="comfortable"
                        hide-details="auto"
                        class="mb-6"
                        :rules="[rules.requiredNumber('Harga'), rules.minZero]"
                    ></v-text-field>

                    <h4
                        class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3"
                    >
                        <v-icon size="20" color="primary"
                            >mdi-account-group-outline</v-icon
                        >
                        Harga Khusus Mitra
                    </h4>

                    <div
                        class="bg-grey-lighten-4 pa-5 rounded-lg border border-opacity-25"
                    >
                        <div
                            v-if="form.member_prices.length === 0"
                            class="text-center text-medium-emphasis py-4"
                        >
                            Tidak ada data level mitra aktif.
                        </div>
                        <v-row dense v-else>
                            <v-col
                                cols="12"
                                md="6"
                                v-for="(mp, i) in form.member_prices"
                                :key="mp.member_level_id"
                            >
                                <label
                                    class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                                    >Harga untuk {{ mp.name }}
                                    <span class="text-error">*</span></label
                                >
                                <v-text-field
                                    :model-value="formatNumber(mp.price)"
                                    @update:model-value="
                                        mp.price = parseNumber($event)
                                    "
                                    placeholder="0"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    class="mb-2 bg-white"
                                    :rules="[
                                        rules.requiredNumber('Harga'),
                                        rules.minZero,
                                    ]"
                                ></v-text-field>

                                <div
                                    class="text-caption d-flex align-center mt-1"
                                    :class="
                                        getMarginColorClass(
                                            form.customer_price,
                                            mp.price,
                                        )
                                    "
                                >
                                    <v-icon size="14" class="mr-1">{{
                                        getMarginIcon(
                                            form.customer_price,
                                            mp.price,
                                        )
                                    }}</v-icon>
                                    Margin: Rp{{
                                        formatPrice(
                                            getMarginValue(
                                                form.customer_price,
                                                mp.price,
                                            ),
                                        )
                                    }}
                                    ({{
                                        getMarginPercentage(
                                            form.customer_price,
                                            mp.price,
                                        )
                                    }}%)
                                </div>
                            </v-col>
                        </v-row>
                    </div>

                    <!-- Projection Table for Bulk Edit -->
                    <div v-if="isBulk" class="mt-8">
                        <h4
                            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3"
                        >
                            <v-icon size="20" color="info"
                                >mdi-table-eye</v-icon
                            >
                            Proyeksi Perubahan Harga
                        </h4>

                        <v-card
                            variant="outlined"
                            class="rounded-lg overflow-hidden border-opacity-25"
                        >
                            <v-table
                                density="comfortable"
                                fixed-header
                                height="300px"
                            >
                                <thead>
                                    <tr>
                                        <th
                                            class="text-left font-weight-bold bg-grey-lighten-4"
                                        >
                                            Produk
                                        </th>
                                        <th
                                            class="text-right font-weight-bold bg-grey-lighten-4"
                                            style="width: 150px"
                                        >
                                            Harga Lama
                                        </th>
                                        <th
                                            class="text-right font-weight-bold bg-grey-lighten-4"
                                            style="width: 150px"
                                        >
                                            Harga Baru
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in selectedProducts"
                                        :key="item.id"
                                    >
                                        <td class="py-2">
                                            <div
                                                class="font-weight-medium text-body-2"
                                            >
                                                {{ item.name }}
                                            </div>
                                            <div
                                                class="text-caption text-medium-emphasis"
                                            >
                                                {{ item.code }}
                                            </div>
                                        </td>
                                        <td class="text-right py-2">
                                            <div
                                                class="text-caption text-medium-emphasis text-decoration-line-through"
                                            >
                                                Rp{{
                                                    formatPrice(
                                                        item.customer_price ||
                                                            0,
                                                    )
                                                }}
                                            </div>
                                        </td>
                                        <td class="text-right py-2">
                                            <div
                                                class="text-body-2 font-weight-bold text-success d-flex align-center justify-end ga-1"
                                            >
                                                <v-icon size="14"
                                                    >mdi-arrow-right</v-icon
                                                >
                                                Rp{{
                                                    formatPrice(
                                                        form.customer_price,
                                                    )
                                                }}
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </v-card>
                    </div>
                </v-form>
            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions class="pa-6 pt-4 border-t">
                <v-spacer></v-spacer>
                <v-btn
                    variant="outlined"
                    color="medium-emphasis"
                    class="text-none px-6"
                    @click="close"
                    :disabled="loading"
                >
                    Batal
                </v-btn>
                <v-btn
                    color="primary"
                    variant="flat"
                    class="text-none px-6"
                    @click="save"
                    :disabled="!isFormValid || loading"
                    :loading="loading"
                    >Simpan Harga</v-btn
                >
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from "vue";
import productService from "@/admin/services/product.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useFormatter } from "@/shared/composables/useFormatter";

const emit = defineEmits(["saved"]);
const snackbar = useSnackbarStore();
const { formatPrice } = useFormatter();

const dialog = ref(false);
const loading = ref(false);
const fetching = ref(false);
const formRef = ref();

const isBulk = ref(false);
const selectedProducts = ref<any[]>([]);
const product = ref<any>(null);

const form = reactive({
    customer_price: 0,
    member_prices: [] as any[],
});

const isFormValid = computed(() => {
    if (!form.customer_price || form.customer_price <= 0) return false;
    if (!form.member_prices || form.member_prices.length === 0) return false;
    for (const mp of form.member_prices) {
        if (!mp.price || mp.price <= 0) return false;
    }
    return true;
});

const rules = {
    requiredNumber: (field: string) => (v: any) =>
        (parseNumber(v) !== null && parseNumber(v) !== "") ||
        `${field} wajib diisi`,
    minZero: (v: any) => parseNumber(v) >= 0 || "Harga tidak boleh negatif",
};

const formatNumber = (value: number | string) => {
    if (!value) return "";
    return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
};

const parseNumber = (value: string | number) => {
    if (typeof value === "number") return value;
    if (!value) return 0;
    return parseInt(value.replace(/\./g, ""), 10) || 0;
};

const getMarginValue = (customerPrice: number, memberPrice: number) => {
    return customerPrice - memberPrice;
};

const getMarginPercentage = (customerPrice: number, memberPrice: number) => {
    if (!customerPrice || customerPrice <= 0) return "0";
    const margin = customerPrice - memberPrice;
    return ((margin / customerPrice) * 100).toFixed(1);
};

const getMarginColorClass = (customerPrice: number, memberPrice: number) => {
    const margin = customerPrice - memberPrice;
    if (margin > 0) return "text-success font-weight-medium";
    if (margin < 0) return "text-error font-weight-medium";
    return "text-medium-emphasis";
};

const getMarginIcon = (customerPrice: number, memberPrice: number) => {
    const margin = customerPrice - memberPrice;
    if (margin > 0) return "mdi-trending-up";
    if (margin < 0) return "mdi-trending-down";
    return "mdi-minus";
};

const open = async (itemOrItems: any | any[]) => {
    dialog.value = true;
    fetching.value = true;

    if (Array.isArray(itemOrItems)) {
        isBulk.value = true;
        selectedProducts.value = itemOrItems;
        product.value = itemOrItems[0];
    } else {
        isBulk.value = false;
        selectedProducts.value = [itemOrItems];
        product.value = itemOrItems;
    }

    try {
        const res = await productService.getPriceDetail(product.value.id);
        const data = res.data?.data || res.data;

        if (isBulk.value) {
            form.customer_price = 0;
            form.member_prices = data.member_prices.map((m: any) => ({
                member_level_id: m.member_level_id,
                name: m.name,
                code: m.code,
                price: 0,
            }));
        } else {
            form.customer_price = data.customer_price || 0;
            form.member_prices = data.member_prices.map((m: any) => ({
                member_level_id: m.member_level_id,
                name: m.name,
                code: m.code,
                price: m.price || 0,
            }));
        }
    } catch (error) {
        console.error("Gagal memuat detail harga produk", error);
        snackbar.showMessage("Gagal memuat detail harga produk", "error");
    } finally {
        fetching.value = false;
    }
};

const close = () => {
    dialog.value = false;
};

const save = async () => {
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    loading.value = true;
    try {
        if (isBulk.value) {
            const payload = selectedProducts.value.map((p) => ({
                product_id: p.id,
                customer_price: form.customer_price,
                member_prices: form.member_prices.map((m) => ({
                    member_level_id: m.member_level_id,
                    price: m.price,
                })),
            }));

            await productService.bulkUpdatePrices(payload);
            snackbar.showMessage(
                `Berhasil mengubah harga ${selectedProducts.value.length} produk massal`,
                "success",
            );
        } else {
            const payload = {
                customer_price: form.customer_price,
                member_prices: form.member_prices.map((m) => ({
                    member_level_id: m.member_level_id,
                    price: m.price,
                })),
            };

            await productService.updatePrice(product.value.id, payload);
            snackbar.showMessage("Berhasil mengubah harga produk", "success");
        }

        emit("saved");
        close();
    } catch (error) {
        console.error("Gagal menyimpan harga produk:", error);
        snackbar.showMessage("Gagal menyimpan harga produk", "error");
    } finally {
        loading.value = false;
    }
};

defineExpose({ open });
</script>
