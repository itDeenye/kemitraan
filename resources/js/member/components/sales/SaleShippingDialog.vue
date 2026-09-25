<template>
    <v-dialog v-model="dialog" max-width="600" persistent scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="text-h6 font-weight-bold">Pengiriman Pesanan</div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    @click="close"
                    :disabled="submitting"
                />
            </v-card-title>

            <v-form ref="formRef" @submit.prevent="submit">
                <v-card-text class="pa-6" style="max-height: 70vh">
                    <p class="text-body-2 text-medium-emphasis mb-4">
                        Kirim pesanan <strong>{{ orderData?.code }}</strong> via
                        <strong>{{
                            getMethodLabel(orderData?.shipping_method)
                        }}</strong
                        >.
                    </p>

                    <div class="d-flex flex-column gap-4">
                        <!-- Manual Courier Fields -->
                        <template
                            v-if="
                                orderData?.shipping_method === 'courier_manual'
                            "
                        >
                            <v-text-field
                                v-model="form.delivery_note_number"
                                label="Nomor Surat Jalan *"
                                variant="outlined"
                                hide-details="auto"
                                :rules="[v => !!v || 'Nomor Surat Jalan wajib diisi']"
                            ></v-text-field>

                            <v-text-field
                                v-model="form.tracking_number"
                                label="Nomor Resi / AWB *"
                                variant="outlined"
                                hide-details="auto"
                                :rules="[v => !!v || 'Nomor Resi wajib diisi']"
                            ></v-text-field>
                        </template>

                        <!-- Pickup Fields -->
                        <template
                            v-else-if="orderData?.shipping_method === 'pickup'"
                        >
                            <v-text-field
                                v-model="form.delivery_note_number"
                                label="Nomor Surat Jalan *"
                                variant="outlined"
                                hide-details="auto"
                                :rules="[v => !!v || 'Nomor Surat Jalan wajib diisi']"
                            ></v-text-field>
                            <!-- For pickup, usually no tracking number -->
                        </template>

                        <!-- Product Items to send -->
                        <div class="mt-2">
                            <h4 class="text-subtitle-2 font-weight-bold mb-2">
                                Item yang dikirim
                            </h4>
                            <v-card
                                variant="outlined"
                                class="rounded-lg overflow-hidden"
                            >
                                <v-table density="comfortable">
                                    <thead class="bg-surface-light">
                                        <tr>
                                            <th
                                                class="font-weight-bold text-left"
                                            >
                                                Produk
                                            </th>
                                            <th
                                                class="text-center font-weight-bold"
                                                style="width: 70px"
                                            >
                                                Qty
                                            </th>
                                            <th
                                                class="font-weight-bold text-left"
                                            >
                                                Batch <span class="text-error">*</span>
                                            </th>
                                            <th
                                                class="font-weight-bold text-left"
                                            >
                                                Exp Date <span class="text-error">*</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(item, idx) in formItems"
                                            :key="idx"
                                        >
                                            <td class="py-2">
                                                <div
                                                    class="text-body-2 font-weight-medium line-clamp-2"
                                                >
                                                    {{ item.product_name }}
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <v-text-field
                                                    v-model.number="
                                                        item.quantity
                                                    "
                                                    variant="outlined"
                                                    density="compact"
                                                    type="number"
                                                    min="1"
                                                    hide-details
                                                    style="width: 70px"
                                                ></v-text-field>
                                            </td>
                                            <td>
                                                <v-text-field
                                                    v-model="item.batch_number"
                                                    variant="outlined"
                                                    density="compact"
                                                    hide-details
                                                    style="min-width: 130px"
                                                ></v-text-field>
                                            </td>
                                            <td>
                                                <v-text-field
                                                    v-model="item.expiry_date"
                                                    variant="outlined"
                                                    density="compact"
                                                    type="date"
                                                    hide-details
                                                    style="min-width: 140px"
                                                ></v-text-field>
                                            </td>
                                        </tr>
                                    </tbody>
                                </v-table>
                            </v-card>
                        </div>
                    </div>
                </v-card-text>

                <v-divider />

                <v-card-actions class="pa-4">
                    <v-spacer />
                    <v-btn
                        variant="outlined"
                        class="rounded-lg px-6"
                        @click="close"
                        :disabled="submitting"
                        >Batal</v-btn
                    >
                    <v-btn
                        color="primary"
                        variant="flat"
                        class="rounded-lg px-6"
                        :loading="submitting"
                        type="submit"
                    >
                        Konfirmasi Pengiriman
                    </v-btn>
                </v-card-actions>
            </v-form>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import saleService from "@/member/services/sale.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import type { SaleOrderDetail, SaleShipPayload } from "@/member/types/sale";

const emit = defineEmits(["success"]);
const snackbar = useSnackbarStore();

const dialog = ref(false);
const formRef = ref<any>(null);
const submitting = ref(false);
const orderData = ref<SaleOrderDetail | null>(null);

const form = reactive({
    delivery_note_number: "",
    tracking_number: "",
});

interface FormItem {
    product_id: number;
    product_name: string;
    quantity: number;
    batch_number: string;
    expiry_date: string;
}

const formItems = ref<FormItem[]>([]);

const requiredRule = (v: any) => !!v || "Wajib diisi";

const getMethodLabel = (method: string | undefined) => {
    if (method === "pickup") return "Ambil Sendiri (Pickup)";
    if (method === "courier_manual") return "Kurir";
    return method;
};

const open = (order: SaleOrderDetail) => {
    orderData.value = order;

    // Reset
    form.delivery_note_number = "";
    form.tracking_number = "";

    formItems.value = (order.items || []).map((i) => ({
        product_id: i.product.id,
        product_name: i.product.name,
        quantity: i.quantity,
        batch_number: "",
        expiry_date: "",
    }));

    if (formRef.value) {
        formRef.value.resetValidation();
    }

    dialog.value = true;
};

const close = () => {
    dialog.value = false;
};

const submit = async () => {
    if (!orderData.value) return;

    if (formRef.value) {
        const { valid } = await formRef.value.validate();
        if (!valid) return;
    }

    submitting.value = true;
    try {
        const payload: SaleShipPayload = {
            delivery_note_number: form.delivery_note_number,
            tracking_number:
                orderData.value.shipping_method === "courier_manual"
                    ? form.tracking_number
                    : undefined,
            items: formItems.value.map((item) => ({
                product_id: item.product_id,
                quantity: item.quantity,
                batch_number: item.batch_number,
                expiry_date: item.expiry_date,
            })),
        };

        const res = await saleService.shipOrder(orderData.value.id, payload);
        if (res.success) {
            snackbar.showMessage(res.message, "success");
            close();
            emit("success");
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

defineExpose({ open });
</script>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
