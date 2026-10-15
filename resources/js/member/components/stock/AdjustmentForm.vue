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
                    label="Jumlah Pengurangan Stok *"
                    variant="outlined"
                    density="compact"
                    min="1"
                    :max="selectedStock?.balance"
                    hint="Jumlah yang dimasukkan akan dikurangi dari stok tersedia, bukan menjadi stok akhir."
                    persistent-hint
                    :disabled="isSubmitting"
                    :error-messages="fieldErrors.qty"
                    @update:model-value="fieldErrors.qty = []"
                    hide-details="auto"
                    class="mb-4"
                />

                <v-textarea
                    v-model="form.reason"
                    label="Alasan Pengurangan Stok *"
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

                <div v-if="selectedStock" class="stock-simulation">
                    <div class="stock-simulation__row">
                        <span>Stok saat ini</span>
                        <strong>
                            {{ currentBalance }} {{ selectedUnit }}
                        </strong>
                    </div>
                    <div class="stock-simulation__row">
                        <span>Pengurangan</span>
                        <strong class="stock-simulation__reduction">
                            −{{ adjustmentQuantity }} {{ selectedUnit }}
                        </strong>
                    </div>

                    <div class="stock-simulation__divider" />

                    <div class="stock-simulation__row stock-simulation__result">
                        <span>Sisa stok</span>
                        <strong
                            :class="{
                                'stock-simulation__remaining--invalid':
                                    remainingBalance < 0,
                            }"
                        >
                            {{ remainingBalance }} {{ selectedUnit }}
                        </strong>
                    </div>

                    <div
                        v-if="remainingBalance < 0"
                        class="stock-simulation__warning"
                    >
                        <v-icon icon="mdi-alert-circle-outline" size="16" />
                        Jumlah pengurangan melebihi stok yang tersedia.
                    </div>
                </div>
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

const currentBalance = computed(() =>
    Number(selectedStock.value?.balance || 0),
);

const adjustmentQuantity = computed(() => {
    const quantity = Number(form.qty);

    return Number.isFinite(quantity) && quantity > 0 ? quantity : 0;
});

const remainingBalance = computed(
    () => currentBalance.value - adjustmentQuantity.value,
);

const selectedUnit = computed(() => selectedStock.value?.product.unit || "pcs");

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
    fieldErrors.qty = form.qty >= 1 ? [] : ["Jumlah pengurangan minimal 1."];
    fieldErrors.reason = form.reason?.trim()
        ? []
        : ["Alasan pengurangan stok wajib diisi."];

    if (selectedStock.value && form.qty > selectedStock.value.balance) {
        fieldErrors.qty = ["Jumlah pengurangan melebihi stok yang tersedia."];
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
        snackbar.showMessage("Pengurangan stok berhasil disimpan.", "success");
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

<style scoped>
.stock-simulation {
    margin-top: 18px;
    padding: 14px;
    border: 1px solid #eadde0;
    border-radius: 12px;
    background: #fffaf7;
}

.stock-simulation__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    min-height: 28px;
    color: #75676a;
    font-size: 12px;
}

.stock-simulation__row strong {
    color: #241619;
    font-variant-numeric: tabular-nums;
    text-align: right;
}

.stock-simulation__row .stock-simulation__reduction {
    color: #dc2626;
}

.stock-simulation__divider {
    height: 1px;
    margin: 9px 0;
    background: #eadde0;
}

.stock-simulation__result {
    font-size: 13px;
    font-weight: 700;
}

.stock-simulation__result strong {
    color: #16845b;
}

.stock-simulation__result .stock-simulation__remaining--invalid {
    color: #dc2626;
}

.stock-simulation__warning {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    margin-top: 10px;
    padding: 9px 10px;
    border-radius: 8px;
    color: #b91c1c;
    background: #fef2f2;
    font-size: 11px;
    line-height: 1.45;
}
</style>
