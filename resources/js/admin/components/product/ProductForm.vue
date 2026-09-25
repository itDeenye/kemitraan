<template>
    <v-dialog v-model="dialog" max-width="800" persistent scrollable>
        <v-card class="rounded-xl elevation-10">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" class="mr-2"
                        >mdi-package-variant-closed</v-icon
                    >
                    <span class="text-h6 font-weight-bold text-primary">{{
                        isEdit ? "Edit Produk" : "Tambah Produk"
                    }}</span>
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

            <v-card-text class="pa-6 pt-5" style="max-height: 70vh">
                <v-form
                    ref="formRef"
                    v-model="isFormValid"
                    @submit.prevent="save"
                >
                    <v-row>
                        <v-col
                            cols="12"
                            class="d-flex flex-column align-center mb-4"
                        >
                            <label
                                class="text-caption font-weight-medium mb-2 d-block text-medium-emphasis"
                                >Foto Produk</label
                            >
                            <MediaUpload
                                v-model="form.image_url"
                                collection="products"
                                :size="120"
                                color="primary"
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                                >Nama Produk
                                <span class="text-error">*</span></label
                            >
                            <v-text-field
                                v-model="form.name"
                                placeholder="Masukkan nama produk"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-4"
                                :rules="[rules.required('Nama produk')]"
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                                >Nomor BPOM</label
                            >
                            <v-text-field
                                v-model="form.bpom_number"
                                placeholder="Contoh: NA18250100001"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-4"
                                maxlength="50"
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                                >Kode Produk
                                <span class="text-error">*</span></label
                            >
                            <v-text-field
                                v-model="form.code"
                                placeholder="Masukkan Kode Produk"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-4"
                                :rules="[rules.required('Kode Produk')]"
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                                >Kategori
                                <span class="text-error">*</span></label
                            >
                            <v-autocomplete
                                v-model="form.category_id"
                                :items="categories"
                                item-title="name"
                                item-value="id"
                                placeholder="Pilih Kategori"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-4"
                                :rules="[rules.requiredSelect('Kategori')]"
                            />
                        </v-col>

                        <v-col cols="12"
                            ><v-divider class="my-2"></v-divider
                        ></v-col>

                        <v-col cols="12" md="6">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                                >Berat (Gram)</label
                            >
                            <v-text-field
                                :model-value="formatNumber(form.weight)"
                                @update:model-value="
                                    form.weight = parseNumber($event)
                                "
                                placeholder="0"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-4"
                                :rules="[rules.minGreaterThanZero('Berat')]"
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                                >Satuan <span class="text-error">*</span></label
                            >
                            <v-text-field
                                v-model="form.unit"
                                placeholder="Pcs, Box, dll"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-4"
                                :rules="[rules.required('Satuan')]"
                            />
                        </v-col>

                        <v-col cols="12">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Dimensi Produk (cm)
                                <span class="text-error">*</span>
                            </label>
                            <v-row dense>
                                <v-col cols="12" sm="4">
                                    <v-text-field
                                        :model-value="
                                            formatNumber(form.length || 0)
                                        "
                                        @update:model-value="
                                            form.length = parseNumber($event)
                                        "
                                        placeholder="Panjang"
                                        variant="outlined"
                                        density="comfortable"
                                        hide-details="auto"
                                        class="mb-4"
                                        :rules="[
                                            rules.minGreaterThanZero('Panjang'),
                                        ]"
                                    >
                                        <template #prepend-inner>
                                            <span
                                                class="text-caption text-medium-emphasis mr-1"
                                                >P</span
                                            >
                                        </template>
                                    </v-text-field>
                                </v-col>
                                <v-col cols="12" sm="4">
                                    <v-text-field
                                        :model-value="
                                            formatNumber(form.width || 0)
                                        "
                                        @update:model-value="
                                            form.width = parseNumber($event)
                                        "
                                        placeholder="Lebar"
                                        variant="outlined"
                                        density="comfortable"
                                        hide-details="auto"
                                        class="mb-4"
                                        :rules="[
                                            rules.minGreaterThanZero('Lebar'),
                                        ]"
                                    >
                                        <template #prepend-inner>
                                            <span
                                                class="text-caption text-medium-emphasis mr-1"
                                                >L</span
                                            >
                                        </template>
                                    </v-text-field>
                                </v-col>
                                <v-col cols="12" sm="4">
                                    <v-text-field
                                        :model-value="
                                            formatNumber(form.height || 0)
                                        "
                                        @update:model-value="
                                            form.height = parseNumber($event)
                                        "
                                        placeholder="Tinggi"
                                        variant="outlined"
                                        density="comfortable"
                                        hide-details="auto"
                                        class="mb-4"
                                        :rules="[
                                            rules.minGreaterThanZero('Tinggi'),
                                        ]"
                                    >
                                        <template #prepend-inner>
                                            <span
                                                class="text-caption text-medium-emphasis mr-1"
                                                >T</span
                                            >
                                        </template>
                                    </v-text-field>
                                </v-col>
                            </v-row>
                        </v-col>

                        <v-col cols="12">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                                >Deskripsi</label
                            >
                            <v-textarea
                                v-model="form.description"
                                placeholder="Masukkan deskripsi produk"
                                rows="3"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-4"
                            />
                        </v-col>

                        <v-col cols="12" sm="6">
                            <v-switch
                                v-model="form.is_active"
                                :label="
                                    form.is_active
                                        ? 'Status: Aktif'
                                        : 'Status: Nonaktif'
                                "
                                color="success"
                                hide-details
                                inset
                            ></v-switch>
                        </v-col>
                        <v-col cols="12" sm="6">
                            <v-switch
                                v-model="form.is_publish"
                                :label="
                                    form.is_publish
                                        ? 'Tampil di Katalog: Ya'
                                        : 'Tampil di Katalog: Tidak'
                                "
                                color="primary"
                                hide-details
                                inset
                            ></v-switch>
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
                    :disabled="!isFormValid"
                    >{{ isEdit ? "Update Produk" : "Simpan Produk" }}</v-btn
                >
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from "vue";
import type {
    Product,
    ProductPayload,
    ProductCategory,
} from "@/admin/types/product";
import MediaUpload from "@/shared/components/MediaUpload.vue";
import productService from "@/admin/services/product.service";
import categoryService from "@/admin/services/category.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const snackbar = useSnackbarStore();

const emit = defineEmits(["saved"]);

const dialog = ref(false);
const loading = ref(false);
const editId = ref<number | null>(null);
const isFormValid = ref(false);
const isEdit = computed(() => editId.value !== null);
const formRef = ref();

const categories = ref<any[]>([]);

const fetchCategories = async () => {
    try {
        categories.value = await categoryService.getCategories({ limit: 100 });
    } catch (error) {
        console.error("Gagal memuat kategori", error);
    }
};

onMounted(() => {
    fetchCategories();
});

const formatNumber = (value: number | string) => {
    if (!value) return "";
    return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
};

const parseNumber = (value: string | number) => {
    if (typeof value === "number") return value;
    if (!value) return 0;
    return parseInt(value.replace(/\./g, ""), 10) || 0;
};

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
    requiredSelect: (field: string) => (v: any) =>
        !!v || `${field} wajib dipilih`,
    minGreaterThanZero: (field: string) => (v: any) =>
        parseNumber(v) > 0 || `${field} harus lebih dari 0`,
    minZero: (field: string) => (v: any) =>
        parseNumber(v) >= 0 || `${field} tidak boleh negatif`,
};

const initialFormState: ProductPayload = {
    category_id: null,
    code: "",
    name: "",
    bpom_number: "",
    image_url: null,
    description: "",
    customer_price: 0,
    distributor_price: 0,
    agent_price: 0,
    reseller_price: 0,
    member_prices: [
        { member_level_id: 1, price: 0 },
        { member_level_id: 2, price: 0 },
        { member_level_id: 3, price: 0 },
    ],
    length: 0,
    width: 0,
    height: 0,
    weight: 0,
    unit: "pcs",
    is_publish: true,
    is_active: true,
};

const form = reactive<ProductPayload>({ ...initialFormState });

const resetForm = () => {
    editId.value = null;
    Object.assign(form, initialFormState);
    if (formRef.value) formRef.value.resetValidation();
};

const open = async (product?: Product) => {
    resetForm();
    if (product) {
        editId.value = product.id;
        try {
            loading.value = true;
            // Fetch detailed product data before showing the form to ensure all fields are populated
            const detail = await productService.getProduct(product.id);
            Object.assign(form, {
                category_id: detail.category?.id || null,
                code: detail.code,
                name: detail.name,
                bpom_number: detail.bpom_number || "",
                image_url: detail.image || null,
                description: detail.description || "",
                customer_price: detail.prices.customer,
                member_prices:
                    detail.prices?.members?.map((m) => ({
                        member_level_id: m.member_level_id,
                        price: m.price,
                    })) || initialFormState.member_prices,
                length: detail.dimensions_cm?.length || 0,
                width: detail.dimensions_cm?.width || 0,
                height: detail.dimensions_cm?.height || 0,
                weight: detail.weight_grams,
                unit: detail.unit,
                is_publish: detail.is_publish,
                is_active: detail.is_active,
            });
        } catch (error) {
            console.error("Failed to fetch product details:", error);
            // Fallback to table data if API fails
            Object.assign(form, {
                category_id: product.category?.id || null,
                code: product.code,
                name: product.name,
                bpom_number: product.bpom_number || "",
                image_url: product.image || null,
                description: product.description || "",
                customer_price: product.prices.customer,
                member_prices:
                    product.prices?.members?.map((m) => ({
                        member_level_id: m.member_level_id,
                        price: m.price,
                    })) || initialFormState.member_prices,
                length: product.dimensions_cm?.length || 0,
                width: product.dimensions_cm?.width || 0,
                height: product.dimensions_cm?.height || 0,
                weight: product.weight_grams,
                unit: product.unit,
                is_publish: product.is_publish,
                is_active: product.is_active,
            });
        } finally {
            loading.value = false;
        }
    }
    dialog.value = true;
};

const close = () => {
    dialog.value = false;
};

const save = async () => {
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    loading.value = true;
    try {
        if (isEdit.value && editId.value) {
            await productService.updateProduct(editId.value, form);
            snackbar.showMessage("Berhasil mengubah produk", "success");
        } else {
            await productService.createProduct(form);
            snackbar.showMessage("Berhasil menambah produk", "success");
        }
        emit("saved");
        close();
    } catch (error) {
        console.error("Failed to save product:", error);
        snackbar.showMessage(
            "Gagal menyimpan produk. Silakan coba lagi.",
            "error",
        );
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, close });
</script>
