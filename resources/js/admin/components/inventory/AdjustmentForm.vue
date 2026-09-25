<template>
    <v-dialog v-model="dialog" max-width="1300" persistent scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28"
                        >mdi-package-variant</v-icon
                    >
                    <span class="text-h6 font-weight-bold text-primary">
                        Tambah Penyesuaian Stok
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

            <v-card-text class="pa-6 pt-2 mb-2">
                <v-form
                    ref="formRef"
                    v-model="isFormValid"
                    @submit.prevent="save"
                >
                    <h4
                        class="text-subtitle-1 font-weight-bold mt-4 mb-2 d-flex align-center justify-space-between"
                    >
                        <span class="text-primary">Rincian Produk</span>
                        <v-btn
                            size="small"
                            color="primary"
                            variant="text"
                            prepend-icon="mdi-plus"
                            @click="addDetail"
                        >
                            Tambah Baris
                        </v-btn>
                    </h4>

                    <v-card
                        variant="outlined"
                        class="mb-4 rounded-lg bg-surface"
                    >
                        <v-list
                            class="bg-transparent pa-0"
                            v-if="form.details.length > 0"
                        >
                            <v-list-item
                                v-for="(detail, index) in form.details"
                                :key="index"
                                class="px-4 py-3"
                                :class="{
                                    'border-b': index < form.details.length - 1,
                                }"
                            >
                                <v-row dense align="start">
                                    <v-col cols="12" md="3">
                                        <label
                                            class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                                            >Pilih Produk
                                            <span class="text-error"
                                                >*</span
                                            ></label
                                        >
                                        <v-autocomplete
                                            v-model="detail.product_id"
                                            :items="products"
                                            item-title="name"
                                            item-value="id"
                                            placeholder="Pilih Produk"
                                            variant="outlined"
                                            density="compact"
                                            hide-details="auto"
                                            :rules="[
                                                rules.requiredSelect('Produk'),
                                            ]"
                                            :loading="loadingProducts"
                                        >
                                            <template #item="{ props, item }">
                                                <v-list-item
                                                    v-bind="props"
                                                    :subtitle="item.raw.code"
                                                ></v-list-item>
                                            </template>
                                        </v-autocomplete>
                                    </v-col>
                                    <v-col cols="12" md="2">
                                        <label
                                            class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                                            >No Batch
                                            <span class="text-error"
                                                >*</span
                                            ></label
                                        >
                                        <v-text-field
                                            v-model="detail.batch_number"
                                            placeholder="No Batch"
                                            variant="outlined"
                                            density="compact"
                                            hide-details="auto"
                                            :rules="[
                                                rules.required('No Batch'),
                                            ]"
                                        ></v-text-field>
                                    </v-col>
                                    <v-col cols="12" md="2">
                                        <label
                                            class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                                            >Tipe
                                            <span class="text-error"
                                                >*</span
                                            ></label
                                        >
                                        <v-select
                                            v-model="detail.type"
                                            :items="[
                                                { title: 'Masuk', value: 'in' },
                                                {
                                                    title: 'Keluar',
                                                    value: 'out',
                                                },
                                            ]"
                                            placeholder="Pilih Tipe"
                                            variant="outlined"
                                            density="compact"
                                            hide-details="auto"
                                            :rules="[
                                                rules.requiredSelect('Tipe'),
                                            ]"
                                        ></v-select>
                                    </v-col>
                                    <v-col cols="12" md="1">
                                        <label
                                            class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                                            >Qty
                                            <span class="text-error"
                                                >*</span
                                            ></label
                                        >
                                        <v-text-field
                                            :model-value="
                                                formatPrice(detail.quantity)
                                            "
                                            @update:model-value="
                                                (val) =>
                                                    (detail.quantity =
                                                        parseNumber(val))
                                            "
                                            type="text"
                                            placeholder="Qty"
                                            variant="outlined"
                                            density="compact"
                                            hide-details="auto"
                                            :rules="[
                                                rules.requiredNumber('Qty'),
                                                rules.minOne,
                                            ]"
                                        ></v-text-field>
                                    </v-col>
                                    <v-col cols="12" md="3">
                                        <label
                                            class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                                            >Catatan</label
                                        >
                                        <v-text-field
                                            v-model="detail.note"
                                            placeholder="Catatan opsional"
                                            variant="outlined"
                                            density="compact"
                                            hide-details="auto"
                                        ></v-text-field>
                                    </v-col>
                                    <v-col
                                        cols="12"
                                        md="1"
                                        class="d-flex align-center justify-center pt-6"
                                    >
                                        <v-btn
                                            icon="mdi-trash-can-outline"
                                            color="error"
                                            variant="text"
                                            size="small"
                                            class="rounded"
                                            @click="removeDetail(index)"
                                            :disabled="
                                                form.details.length === 1
                                            "
                                        ></v-btn>
                                    </v-col>
                                </v-row>
                            </v-list-item>
                        </v-list>
                        <div
                            v-else
                            class="text-center py-8 text-body-2 text-medium-emphasis"
                        >
                            <v-icon
                                size="48"
                                color="grey-lighten-1"
                                class="mb-3"
                                >mdi-package-variant-closed</v-icon
                            >
                            <br />
                            Belum ada produk yang ditambahkan.
                        </div>
                    </v-card>

                    <v-row class="mt-4">
                        <v-col cols="12">
                            <v-textarea
                                v-model="form.note"
                                label="Catatan Penyesuaian Stok"
                                variant="outlined"
                                density="comfortable"
                                rows="2"
                                auto-grow
                                hide-details="auto"
                                :rules="[
                                    rules.required('Catatan Penyesuaian Stok'),
                                ]"
                            ></v-textarea>
                        </v-col>
                    </v-row>
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
                    class="text-none px-6 ml-3"
                    @click="save"
                    :loading="loading"
                    :disabled="!isFormValid || form.details.length === 0"
                    >Simpan</v-btn
                >
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import inventoryService from "@/admin/services/inventory.service";
import api from "@/shared/services/api";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatPrice } = useFormatter();

const parseNumber = (value: string | number) => {
    if (typeof value === "number") return value;
    if (!value) return 0;
    return parseInt(value.replace(/\D/g, ""), 10) || 0;
};

const snackbar = useSnackbarStore();

const emit = defineEmits(["saved"]);

const dialog = ref(false);
const loading = ref(false);
const formRef = ref();
const isFormValid = ref(false);

const loadingWarehouses = ref(false);
const warehouses = ref<any[]>([]);
const loadingProducts = ref(false);
const products = ref<any[]>([]);

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
    requiredSelect: (field: string) => (v: any) =>
        !!v || `${field} wajib dipilih`,
    requiredNumber: (field: string) => (v: any) =>
        !!v || `${field} wajib diisi`,
    minOne: (v: any) => parseNumber(v) > 0 || "Minimal 1",
};

const defaultForm = {
    warehouse_id: 1,
    note: "",
    details: [
        {
            product_id: null,
            batch_number: "",
            type: "in",
            quantity: 1,
            unit_price: 0,
            note: "",
        },
    ],
};
const form = reactive<typeof defaultForm>(
    JSON.parse(JSON.stringify(defaultForm)),
);

const fetchWarehouses = async () => {
    if (warehouses.value.length > 0) return;
    loadingWarehouses.value = true;
    try {
        const response = await api.get("/admin/company/warehouses", {
            params: { limit: 100 },
        });
        warehouses.value = response.data?.data?.results || [];
    } catch (error) {
        console.error("Gagal mengambil gudang:", error);
    } finally {
        loadingWarehouses.value = false;
    }
};

const fetchProducts = async () => {
    if (products.value.length > 0) return;
    loadingProducts.value = true;
    try {
        const response = await api.get("/admin/products", {
            params: { limit: 100 },
        });
        products.value = response.data?.data?.results || [];
    } catch (error) {
        console.error("Gagal mengambil produk:", error);
    } finally {
        loadingProducts.value = false;
    }
};

const addDetail = () => {
    form.details.push({
        product_id: null,
        batch_number: "",
        type: "in",
        quantity: 1,
        unit_price: 0,
        note: "",
    });
};

const removeDetail = (index: number) => {
    if (form.details.length > 1) {
        form.details.splice(index, 1);
    }
};

const open = () => {
    Object.assign(form, JSON.parse(JSON.stringify(defaultForm)));
    dialog.value = true;
    fetchWarehouses();
    fetchProducts();
};

const close = () => {
    dialog.value = false;
};

const save = async () => {
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    loading.value = true;
    try {
        await inventoryService.createAdjustment(form as any);
        snackbar.showMessage("Penyesuaian stok berhasil disimpan.", "success");
        emit("saved");
        close();
    } catch (error: any) {
        console.error("Failed to create adjustment:", error);
        const errorMessage =
            error.response?.data?.message || "Gagal menyimpan penyesuaian stok";
        snackbar.showMessage(errorMessage, "error");
    } finally {
        loading.value = false;
    }
};

defineExpose({ open });
</script>
