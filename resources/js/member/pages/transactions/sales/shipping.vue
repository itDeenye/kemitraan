<template>
    <div class="screen-body">
        <div v-if="order && !isLoading" class="pb-24">
            <div class="card mb-4" style="padding: 16px">
                <div class="d-flex align-center justify-space-between mb-2">
                    <div>
                        <div class="text-[12px] text-[var(--muted)]">
                            Kode Transaksi
                        </div>
                        <div class="font-weight-bold text-[14px] break-all">
                            {{ order.code }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4" style="padding: 16px">
                <p class="text-[13px] text-[var(--muted)] mb-4">
                    <template v-if="isPosOrder">
                        Selesaikan pesanan POS lama dengan mengisi jumlah,
                        nomor batch, dan tanggal kedaluwarsa produk.
                    </template>
                    <template v-else>
                        Pesanan ini via
                        <strong>{{
                            getMethodLabel(order.shipping_method)
                        }}</strong
                        >.
                    </template>
                </p>

                <v-form ref="formRef" @submit.prevent="submit">
                    <div class="flex flex-col gap-4">
                        <template
                            v-if="order.shipping_method === 'courier_manual'"
                        >
                            <v-text-field
                                v-model="form.tracking_number"
                                label="Nomor Resi / AWB *"
                                variant="outlined"
                                hide-details="auto"
                                :rules="[
                                    (v) => !!v || 'Nomor Resi wajib diisi',
                                ]"
                            ></v-text-field>
                        </template>

                        <template
                            v-else-if="order.shipping_method === 'pickup'"
                        >
                            <div
                                v-if="!isPosOrder"
                                class="rounded-lg"
                                style="
                                    padding: 12px 14px;
                                    border: 1px solid
                                        rgba(var(--v-theme-primary), 0.18);
                                    background: rgba(
                                        var(--v-theme-primary),
                                        0.06
                                    );
                                "
                            >
                                <div
                                    class="text-[12px] font-semibold text-[var(--primary)] mb-1"
                                >
                                    Kode verifikasi pengambilan
                                </div>
                                <div class="text-[12px] text-[var(--muted)]">
                                    Masukkan kode yang ditunjukkan oleh pembeli
                                    saat barang diambil.
                                </div>
                            </div>

                            <v-text-field
                                v-if="!isPosOrder"
                                v-model="form.pickup_pin"
                                label="Kode Verifikasi Pengambilan *"
                                variant="outlined"
                                hide-details="auto"
                                maxlength="5"
                                inputmode="numeric"
                                :rules="[
                                    (v) => !!v || 'Kode verifikasi wajib diisi',
                                    (v) =>
                                        /^\d{5}$/.test(v || '') ||
                                        'Kode verifikasi harus 5 angka',
                                ]"
                            ></v-text-field>
                        </template>

                        <div class="mt-2">
                            <h4 class="text-subtitle-2 font-weight-bold mb-2">
                                Produk yang dikirim
                            </h4>
                            <div class="flex flex-col gap-4 w-100">
                                <v-card
                                    v-for="(prod, pIdx) in formProducts"
                                    :key="pIdx"
                                    variant="outlined"
                                    class="rounded-lg overflow-hidden w-100"
                                >
                                    <div
                                        class="bg-surface-light px-3 py-2"
                                        style="
                                            border-bottom: 1px solid
                                                rgba(0, 0, 0, 0.05);
                                        "
                                    >
                                        <div
                                            class="font-weight-bold text-[13px]"
                                        >
                                            {{ prod.product_name }}
                                            <span
                                                class="text-[var(--muted)] font-normal whitespace-nowrap"
                                                >(Pesanan:
                                                {{ prod.total_quantity }})</span
                                            >
                                        </div>
                                    </div>
                                    <div
                                        v-if="
                                            getBatchTotal(prod) !==
                                            prod.total_quantity
                                        "
                                        class="px-3 py-2 text-[12px]"
                                        style="
                                            background: rgba(
                                                var(--v-theme-error),
                                                0.1
                                            );
                                            color: rgb(var(--v-theme-error));
                                        "
                                    >
                                        Total qty batch ({{
                                            getBatchTotal(prod)
                                        }}) tidak sama dengan qty pesanan ({{
                                            prod.total_quantity
                                        }}).
                                    </div>
                                    <div class="pa-3 d-flex flex-column gap-4">
                                        <div
                                            style="
                                                display: flex;
                                                justify-content: space-between;
                                                align-items: center;
                                                margin-bottom: -4px;
                                            "
                                        >
                                            <span
                                                style="
                                                    font-size: 12px;
                                                    font-weight: 600;
                                                "
                                                >Batch Produk</span
                                            >
                                            <button
                                                type="button"
                                                class="btn-text-primary d-flex align-center"
                                                @click="addBatch(pIdx)"
                                                style="
                                                    color: var(--primary);
                                                    font-size: 12px;
                                                    font-weight: 600;
                                                "
                                            >
                                                <v-icon
                                                    icon="mdi-plus"
                                                    size="14"
                                                    class="mr-1"
                                                ></v-icon>
                                                Tambah Batch
                                            </button>
                                        </div>
                                        <div
                                            v-for="(
                                                batch, bIdx
                                            ) in prod.batches"
                                            :key="bIdx"
                                            class="d-flex align-start gap-2"
                                        >
                                            <div
                                                class="d-flex align-center justify-center pt-3 text-[12px] font-weight-bold text-[var(--muted)]"
                                                style="width: 24px"
                                            >
                                                #{{ bIdx + 1 }}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <v-row dense>
                                                    <v-col
                                                        cols="12"
                                                        sm="3"
                                                        md="2"
                                                    >
                                                        <v-text-field
                                                            v-model.number="
                                                                batch.quantity
                                                            "
                                                            label="Qty *"
                                                            variant="outlined"
                                                            density="compact"
                                                            type="number"
                                                            min="1"
                                                            hide-details="auto"
                                                            :rules="[
                                                                (v) =>
                                                                    !!v ||
                                                                    'Qty wajib diisi',
                                                                (v) =>
                                                                    v > 0 ||
                                                                    'Minimal 1',
                                                            ]"
                                                        ></v-text-field>
                                                    </v-col>
                                                    <v-col
                                                        cols="12"
                                                        sm="5"
                                                        md="6"
                                                    >
                                                        <v-text-field
                                                            v-model="
                                                                batch.batch_number
                                                            "
                                                            label="No. Batch *"
                                                            variant="outlined"
                                                            density="compact"
                                                            hide-details="auto"
                                                            :rules="[
                                                                (v) =>
                                                                    !!v ||
                                                                    'No. Batch wajib diisi',
                                                            ]"
                                                        ></v-text-field>
                                                    </v-col>
                                                    <v-col
                                                        cols="12"
                                                        sm="4"
                                                        md="4"
                                                    >
                                                        <v-text-field
                                                            v-model="
                                                                batch.expiry_date
                                                            "
                                                            label="Exp Date *"
                                                            variant="outlined"
                                                            density="compact"
                                                            type="date"
                                                            hide-details="auto"
                                                            :rules="[
                                                                (v) =>
                                                                    !!v ||
                                                                    'Exp Date wajib diisi',
                                                            ]"
                                                        ></v-text-field>
                                                    </v-col>
                                                </v-row>
                                            </div>
                                            <button
                                                type="button"
                                                @click="removeBatch(pIdx, bIdx)"
                                                class="pt-3 pl-1"
                                                style="
                                                    color: rgb(
                                                        var(--v-theme-error)
                                                    );
                                                "
                                            >
                                                <v-icon
                                                    icon="mdi-delete-outline"
                                                    size="22"
                                                ></v-icon>
                                            </button>
                                        </div>
                                    </div>
                                </v-card>
                            </div>
                        </div>
                    </div>
                </v-form>
            </div>
        </div>

        <div v-else-if="isLoading" style="padding: 40px; text-align: center">
            <v-progress-circular
                indeterminate
                color="primary"
            ></v-progress-circular>
            <div class="mt-2 text-[var(--muted)] text-[13px]">
                Memuat data...
            </div>
        </div>
        <div v-else style="padding: 40px; text-align: center">
            <div class="text-[var(--muted)] text-[13px]">
                Pesanan tidak ditemukan.
            </div>
        </div>

        <button
            v-if="order && !isLoading"
            class="primary-button block w-full mt-2"
            @click="submit"
            :disabled="submitting"
        >
            <v-progress-circular
                v-if="submitting"
                indeterminate
                size="20"
                width="2"
                color="white"
                class="mr-2"
            />
            {{
                submitting
                    ? "Menyimpan..."
                    : isPosOrder
                      ? "Serahkan Pesanan"
                      : "Kirim Pesanan"
            }}
        </button>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, reactive, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import saleService from "@/member/services/sale.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import type { SaleOrderDetail, SaleShipPayload } from "@/member/types/sale";
import { decodeRouteId, encodeRouteId } from "@/shared/utils/route-id";

const route = useRoute();
const router = useRouter();
const snackbar = useSnackbarStore();

const orderId = decodeRouteId(route.query.id);
const order = ref<SaleOrderDetail | null>(null);
const isLoading = ref(true);
const submitting = ref(false);
const formRef = ref<any>(null);

const form = reactive({
    tracking_number: "",
    pickup_pin: "",
});

interface FormProduct {
    product_id: number;
    product_name: string;
    total_quantity: number;
    batches: {
        quantity: number;
        batch_number: string;
        expiry_date: string;
    }[];
}

const formProducts = ref<FormProduct[]>([]);

const addBatch = (pIdx: number) => {
    formProducts.value[pIdx].batches.push({
        quantity: 1,
        batch_number: "",
        expiry_date: "",
    });
};

const removeBatch = (pIdx: number, bIdx: number) => {
    if (formProducts.value[pIdx].batches.length > 1) {
        formProducts.value[pIdx].batches.splice(bIdx, 1);
    }
};

const getBatchTotal = (prod: FormProduct) => {
    return prod.batches.reduce((sum, b) => sum + (Number(b.quantity) || 0), 0);
};

const isPosOrder = computed(
    () =>
        order.value?.type === "retail" ||
        order.value?.buyer?.type === "customer",
);

const getMethodLabel = (method: string | undefined) => {
    if (method === "pickup") return "Ambil Sendiri (Pickup)";
    if (method === "courier_manual") return "Kurir";
    return method;
};

const fetchDetail = async () => {
    if (!orderId) {
        isLoading.value = false;
        return;
    }

    isLoading.value = true;
    try {
        const response = await saleService.getOrderDetail(orderId);
        if (response.success && response.data) {
            order.value = response.data;

            formProducts.value = (order.value.items || []).map((i) => ({
                product_id: i.product.id,
                product_name: i.product.name,
                total_quantity: i.quantity,
                batches: [
                    {
                        quantity: i.quantity,
                        batch_number: "",
                        expiry_date: "",
                    },
                ],
            }));
        }
    } catch (error) {
        console.error("Failed to fetch order:", error);
        snackbar.showMessage("Gagal memuat pesanan", "error");
    } finally {
        isLoading.value = false;
    }
};

const submit = async () => {
    if (!order.value) return;

    if (formRef.value) {
        const { valid } = await formRef.value.validate();
        if (!valid) return;
    }

    for (const prod of formProducts.value) {
        if (getBatchTotal(prod) !== prod.total_quantity) {
            snackbar.showMessage(
                `Total qty batch untuk ${prod.product_name} belum sesuai dengan pesanan.`,
                "error",
            );
            return;
        }
    }

    submitting.value = true;
    try {
        const payload: SaleShipPayload = {
            tracking_number:
                order.value.shipping_method === "courier_manual"
                    ? form.tracking_number
                    : undefined,
            pickup_pin:
                order.value.shipping_method === "pickup" && !isPosOrder.value
                    ? form.pickup_pin
                    : undefined,
            items: formProducts.value.flatMap((prod) =>
                prod.batches.map((b) => ({
                    product_id: prod.product_id,
                    quantity: Number(b.quantity),
                    batch_number: b.batch_number,
                    expiry_date: b.expiry_date,
                })),
            ),
        };

        const res = await saleService.shipOrder(order.value.id, payload);
        if (res.success) {
            snackbar.showMessage(res.message, "success");
            router.replace(
                `/member/transactions/sales/${encodeRouteId(orderId)}`,
            );
        }
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Gagal memproses pengiriman",
            "error",
        );
    } finally {
        submitting.value = false;
    }
};

onMounted(() => {
    fetchDetail();
});
</script>
