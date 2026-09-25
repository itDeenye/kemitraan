<template>
    <v-dialog v-model="isOpen" max-width="480" scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="d-flex align-center justify-space-between pa-4"
            >
                <span class="text-body-1 font-weight-bold"
                    >Buat Penyesuaian</span
                >
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    @click="closeForm"
                />
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-4">
                <v-select
                    v-model="form.product_id"
                    :items="stockOptions"
                    label="Produk *"
                    item-title="title"
                    item-value="value"
                    placeholder="Pilih produk"
                    variant="outlined"
                    density="compact"
                    :loading="isLoadingStocks"
                    :disabled="isSubmitting"
                    :error-messages="fieldErrors.product_id"
                    @update:model-value="fieldErrors.product_id = []"
                    hide-details="auto"
                    class="mb-4"
                />

                <v-text-field
                    v-model.number="form.qty"
                    type="number"
                    label="Jumlah Penyesuaian *"
                    variant="outlined"
                    density="compact"
                    min="1"
                    :max="selectedStock?.balance"
                    :hint="
                        selectedStock
                            ? `Stok tersedia: ${selectedStock.balance} ${selectedStock.product.unit || 'pcs'}`
                            : ''
                    "
                    persistent-hint
                    :disabled="isSubmitting"
                    :error-messages="fieldErrors.qty"
                    @update:model-value="fieldErrors.qty = []"
                    hide-details="auto"
                    class="mb-4"
                />

                <v-textarea
                    v-model="form.reason"
                    label="Alasan Penyesuaian *"
                    placeholder="Contoh: Barang rusak"
                    variant="outlined"
                    density="compact"
                    rows="3"
                    counter="255"
                    maxlength="255"
                    :disabled="isSubmitting"
                    :error-messages="fieldErrors.reason"
                    @update:model-value="fieldErrors.reason = []"
                    hide-details="auto"
                />
            </v-card-text>
            <v-divider />
            <v-card-actions class="pa-4">
                <v-spacer />
                <v-btn
                    variant="text"
                    :disabled="isSubmitting"
                    @click="closeForm"
                >
                    Batal
                </v-btn>
                <v-btn
                    color="primary"
                    variant="flat"
                    class="text-none font-weight-bold"
                    :loading="isSubmitting"
                    @click="submitAdjustment"
                >
                    Simpan Penyesuaian
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import inventoryService from "@/member/services/inventory.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import type {
    StockAdjustmentPayload,
    StockItem,
} from "@/member/types/inventory";

const props = defineProps<{
    modelValue: boolean;
}>();

const emit = defineEmits<{
    (e: "update:modelValue", value: boolean): void;
    (e: "success"): void;
}>();

const snackbar = useSnackbarStore();

const isLoadingStocks = ref(false);
const isSubmitting = ref(false);
const stocks = ref<StockItem[]>([]);

const fieldErrors = reactive<Record<keyof StockAdjustmentPayload, string[]>>({
    product_id: [],
    qty: [],
    reason: [],
});

const form = reactive<StockAdjustmentPayload>({
    product_id: null as any,
    qty: 1,
    reason: "",
});

const isOpen = computed({
    get: () => props.modelValue,
    set: (val) => emit("update:modelValue", val),
});

const stockOptions = computed(() =>
    stocks.value
        .filter((stock) => stock.balance > 0)
        .map((stock) => ({
            title: `${stock.product.name} (${stock.balance} ${stock.product.unit || "pcs"})`,
            value: stock.product.id,
        })),
);

const selectedStock = computed(() =>
    stocks.value.find((stock) => stock.product.id === form.product_id),
);

watch(isOpen, async (val) => {
    if (val) {
        resetForm();
        await fetchStocks();
    }
});

function resetForm() {
    form.product_id = null as any;
    form.qty = 1;
    form.reason = "";
    fieldErrors.product_id = [];
    fieldErrors.qty = [];
    fieldErrors.reason = [];
}

function closeForm() {
    if (isSubmitting.value) return;
    isOpen.value = false;
    resetForm();
}

function validateForm() {
    fieldErrors.product_id = form.product_id ? [] : ["Produk wajib dipilih."];
    fieldErrors.qty = form.qty >= 1 ? [] : ["Jumlah minimal 1."];
    fieldErrors.reason = form.reason?.trim() ? [] : ["Alasan wajib diisi."];

    if (selectedStock.value && form.qty > selectedStock.value.balance) {
        fieldErrors.qty = ["Jumlah melebihi stok yang tersedia."];
    }

    return Object.values(fieldErrors).every((errors) => errors.length === 0);
}

async function fetchStocks() {
    isLoadingStocks.value = true;
    try {
        const response = await inventoryService.getCurrentStock({
            pagination: false,
        });
        stocks.value = response.results || [];
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Gagal memuat stok.",
            "error",
        );
    } finally {
        isLoadingStocks.value = false;
    }
}

async function submitAdjustment() {
    if (!validateForm()) return;
    isSubmitting.value = true;

    try {
        await inventoryService.createAdjustment({
            product_id: form.product_id,
            qty: Number(form.qty),
            reason: form.reason.trim(),
        });
        snackbar.showMessage("Penyesuaian stok berhasil disimpan.", "success");
        isOpen.value = false;
        emit("success");
    } catch (error: any) {
        const errors = error.response?.data?.errors || {};
        fieldErrors.product_id = errors.product_id || [];
        fieldErrors.qty = errors.qty || [];
        fieldErrors.reason = errors.reason || [];
        snackbar.showMessage(
            error.response?.data?.message ||
                "Gagal menyimpan penyesuaian stok.",
            "error",
        );
    } finally {
        isSubmitting.value = false;
    }
}
</script>
