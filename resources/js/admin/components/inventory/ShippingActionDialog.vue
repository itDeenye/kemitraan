<template>
    <v-dialog v-model="dialog" max-width="1000" persistent scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28">{{ getIcon }}</v-icon>
                    <span class="text-h6 font-weight-bold text-primary">
                        {{ getTitle }}
                    </span>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    color="medium-emphasis"
                    @click="close"
                    density="comfortable"
                    :disabled="loading"
                ></v-btn>
            </v-card-title>

            <v-divider></v-divider>

            <v-card-text class="pa-6">
                <v-form
                    ref="formRef"
                    v-model="isFormValid"
                    @submit.prevent="submit"
                >
                    <div class="mb-4">
                        <label
                            class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                        >
                            Nomor Surat Jalan
                            <span class="text-error">*</span>
                        </label>
                        <v-text-field
                            v-model="form.delivery_note_number"
                            placeholder="Masukkan No Surat Jalan (Contoh: SJ/2026/000001)"
                            variant="outlined"
                            density="compact"
                            hide-details="auto"
                            :rules="[rules.required('Nomor Surat Jalan')]"
                        ></v-text-field>
                    </div>

                    <div class="mb-4">
                        <label
                            class="text-caption font-weight-medium mb-2 d-block text-medium-emphasis"
                        >
                            Rincian Batch Pengiriman
                            <span class="text-error">*</span>
                        </label>

                        <div
                            v-if="loadingData"
                            class="d-flex justify-center align-center py-8"
                        >
                            <v-progress-circular
                                indeterminate
                                size="24"
                                color="primary"
                                class="mr-2"
                            ></v-progress-circular>
                            <span class="text-medium-emphasis"
                                >Memuat rincian pesanan...</span
                            >
                        </div>

                        <div
                            v-else
                            v-for="(
                                productGroup, groupIndex
                            ) in form.productGroups"
                            :key="groupIndex"
                            class="mb-3"
                        >
                            <v-card
                                variant="outlined"
                                class="rounded-lg border"
                            >
                                <div
                                    class="d-flex align-center justify-space-between pa-3 bg-grey-lighten-4"
                                >
                                    <div class="d-flex align-center ga-2">
                                        <v-icon size="small" color="primary"
                                            >mdi-package-variant-closed</v-icon
                                        >
                                        <div>
                                            <div
                                                class="text-body-2 font-weight-medium"
                                            >
                                                {{ productGroup.product_name }}
                                            </div>
                                            <div
                                                class="text-caption text-medium-emphasis"
                                            >
                                                Qty Pesanan:
                                                {{
                                                    formatPrice(
                                                        productGroup.order_quantity,
                                                    )
                                                }}
                                                <span
                                                    v-if="
                                                        getBatchQtyTotal(
                                                            groupIndex,
                                                        ) !==
                                                        productGroup.order_quantity
                                                    "
                                                    class="text-error ml-2"
                                                >
                                                    (Terisi:
                                                    {{
                                                        formatPrice(
                                                            getBatchQtyTotal(
                                                                groupIndex,
                                                            ),
                                                        )
                                                    }})
                                                </span>
                                                <span
                                                    v-else
                                                    class="text-success ml-2"
                                                >
                                                    <v-icon size="12"
                                                        >mdi-check-circle</v-icon
                                                    >
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <v-btn
                                        size="small"
                                        variant="text"
                                        color="primary"
                                        @click="addBatchRow(groupIndex)"
                                    >
                                        <v-icon size="small" class="mr-1"
                                            >mdi-plus</v-icon
                                        >
                                        Batch
                                    </v-btn>
                                </div>

                                <div
                                    v-if="
                                        getBatchQtyTotal(groupIndex) !==
                                        productGroup.order_quantity
                                    "
                                    class="pa-3 text-caption font-weight-medium border-b"
                                    style="
                                        background-color: #feeceb;
                                        color: #e53935;
                                    "
                                >
                                    Total qty batch ({{
                                        formatPrice(
                                            getBatchQtyTotal(groupIndex),
                                        )
                                    }}) tidak sama dengan qty pesanan ({{
                                        formatPrice(
                                            productGroup.order_quantity,
                                        )
                                    }}).
                                </div>

                                <v-table density="compact">
                                    <thead>
                                        <tr>
                                            <th
                                                style="width: 25%"
                                                class="font-weight-medium text-uppercase text-caption"
                                            >
                                                Qty
                                            </th>
                                            <th
                                                style="width: 35%"
                                                class="font-weight-medium text-uppercase text-caption"
                                            >
                                                No Batch
                                            </th>
                                            <th
                                                style="width: 35%"
                                                class="font-weight-medium text-uppercase text-caption"
                                            >
                                                Exp Date
                                            </th>
                                            <th style="width: 5%"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(
                                                batch, batchIndex
                                            ) in productGroup.batches"
                                            :key="batchIndex"
                                            class="align-start justify-start"
                                        >
                                            <td
                                                class="pa-2 pt-4"
                                                style="vertical-align: top"
                                            >
                                                <v-text-field
                                                    :model-value="
                                                        formatPrice(
                                                            batch.quantity,
                                                        )
                                                    "
                                                    @update:model-value="
                                                        (
                                                            val:
                                                                | string
                                                                | number,
                                                        ) =>
                                                            (batch.quantity =
                                                                parseNumber(
                                                                    val,
                                                                ))
                                                    "
                                                    type="text"
                                                    variant="outlined"
                                                    density="compact"
                                                    hide-details="auto"
                                                    placeholder="Qty"
                                                    :rules="[rules.minQty]"
                                                ></v-text-field>
                                            </td>
                                            <td
                                                class="pa-2 pt-4"
                                                style="vertical-align: top"
                                            >
                                                <v-text-field
                                                    v-model="batch.batch_number"
                                                    placeholder="Nomor Batch"
                                                    variant="outlined"
                                                    density="compact"
                                                    hide-details="auto"
                                                    :rules="[
                                                        rules.required(
                                                            'No Batch',
                                                        ),
                                                    ]"
                                                ></v-text-field>
                                            </td>
                                            <td
                                                class="pa-2 pt-4"
                                                style="vertical-align: top"
                                            >
                                                <v-text-field
                                                    v-model="batch.expiry_date"
                                                    type="date"
                                                    variant="outlined"
                                                    density="compact"
                                                    hide-details="auto"
                                                    :rules="[
                                                        rules.required(
                                                            'Exp Date',
                                                        ),
                                                    ]"
                                                ></v-text-field>
                                            </td>
                                            <td
                                                class="pa-2 pt-4"
                                                style="vertical-align: top"
                                            >
                                                <v-btn
                                                    icon="mdi-close"
                                                    variant="text"
                                                    color="error"
                                                    size="x-small"
                                                    @click="
                                                        removeBatchRow(
                                                            groupIndex,
                                                            batchIndex,
                                                        )
                                                    "
                                                    :disabled="
                                                        productGroup.batches
                                                            .length <= 1
                                                    "
                                                ></v-btn>
                                            </td>
                                        </tr>
                                    </tbody>
                                </v-table>
                            </v-card>
                        </div>
                    </div>

                    <template
                        v-if="order?.shipping_method === 'courier_express'"
                    >
                        <div class="mb-4">
                            <label
                                class="text-caption font-weight-medium mb-2 d-block text-medium-emphasis"
                            >
                                Metode Pengiriman
                                <span class="text-error">*</span>
                            </label>
                            <v-radio-group
                                v-model="form.pickup_method"
                                inline
                                hide-details="auto"
                                class="mt-0"
                                :rules="[rules.requiredSelect('Metode')]"
                                @change="onPickupMethodChange"
                            >
                                <v-radio
                                    label="Pickup Kurir"
                                    value="PICKUP"
                                    color="primary"
                                ></v-radio>
                                <v-radio
                                    label="Drop-off di Agen"
                                    value="DROP-OFF"
                                    color="primary"
                                ></v-radio>
                            </v-radio-group>
                        </div>

                        <div
                            v-if="form.pickup_method === 'PICKUP'"
                            class="mb-2"
                        >
                            <v-alert
                                type="info"
                                variant="tonal"
                                class="mb-4"
                                density="compact"
                            >
                                Pastikan Anda berada di tempat pada jadwal yang
                                ditentukan untuk meminimalisir pembatalan dari
                                pihak ekspedisi.
                            </v-alert>
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Jadwal Pickup <span class="text-error">*</span>
                            </label>
                            <v-select
                                v-model="form.pickup_schedule"
                                :items="schedules"
                                item-title="time_formatted"
                                item-value="time"
                                placeholder="Pilih jadwal pickup"
                                variant="outlined"
                                density="compact"
                                :loading="loadingSchedules"
                                :disabled="loadingSchedules"
                                :rules="[rules.requiredSelect('Jadwal pickup')]"
                                hide-details="auto"
                            >
                                <template #item="{ props, item }">
                                    <v-list-item
                                        v-bind="props"
                                        :disabled="!item.raw.is_available"
                                    >
                                        <template #title>
                                            <div
                                                class="d-flex align-center justify-space-between w-100"
                                            >
                                                <span>{{
                                                    item.raw.time_formatted
                                                }}</span>
                                                <v-chip
                                                    v-if="
                                                        !item.raw.is_available
                                                    "
                                                    size="x-small"
                                                    color="error"
                                                    variant="flat"
                                                    >Penuh</v-chip
                                                >
                                            </div>
                                        </template>
                                    </v-list-item>
                                </template>
                            </v-select>
                        </div>
                    </template>

                    <template
                        v-else-if="order?.shipping_method === 'courier_manual'"
                    >
                        <div class="mb-2">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Nomor Resi
                                <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.tracking_number"
                                placeholder="Masukkan No Resi (Contoh: JNE-0001)"
                                variant="outlined"
                                density="compact"
                                hide-details="auto"
                                :rules="[rules.required('Nomor resi')]"
                            ></v-text-field>
                        </div>
                    </template>

                    <!-- Pickup (Ambil Sendiri) -->
                    <template v-else-if="order?.shipping_method === 'pickup'">
                        <div class="mb-2">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Kode Verifikasi Pengambilan
                                <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.pickup_pin"
                                placeholder="Masukkan Kode Verifikasi Pengambilan (Contoh: 12345)"
                                inputmode="numeric"
                                maxlength="5"
                                variant="outlined"
                                density="compact"
                                hide-details="auto"
                                :rules="[rules.required('Kode verifikasi')]"
                            ></v-text-field>
                        </div>
                    </template>

                    <template v-else>
                        <v-alert type="warning" variant="tonal" class="mb-0">
                            Metode pengiriman tidak didukung atau belum disetup.
                        </v-alert>
                    </template>
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
                    @click="submit"
                    :loading="loading"
                    :disabled="!isFormValid || !order?.shipping_method"
                >
                    {{ getSubmitText }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from "vue";
import inventoryService from "@/admin/services/inventory.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime, formatPrice } = useFormatter();

const parseNumber = (value: string | number): number => {
    if (!value) return 0;
    if (typeof value === "number") return value;
    const parsed = parseInt(value.toString().replace(/\D/g, ""), 10);
    return isNaN(parsed) ? 0 : parsed;
};
const snackbar = useSnackbarStore();
const emit = defineEmits(["success"]);

const dialog = ref(false);
const loading = ref(false);
const loadingData = ref(false);
const loadingSchedules = ref(false);
const formRef = ref();
const isFormValid = ref(false);
const order = ref<any>(null);
const schedules = ref<any[]>([]);

interface BatchRow {
    quantity: number;
    batch_number: string;
    expiry_date: string;
}

interface ProductGroup {
    product_id: number;
    product_name: string;
    product_code: string;
    order_quantity: number;
    batches: BatchRow[];
}

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
    requiredSelect: (field: string) => (v: any) =>
        !!v || `${field} wajib dipilih`,
    minQty: (v: any) => {
        const num = typeof v === "number" ? v : parseNumber(v);
        return num > 0 || "Qty harus lebih dari 0";
    },
};

const form = reactive({
    delivery_note_number: "",
    pickup_method: "",
    pickup_schedule: "",
    tracking_number: "",
    pickup_pin: "",
    productGroups: [] as ProductGroup[],
});

const addBatchRow = (groupIndex: number) => {
    form.productGroups[groupIndex].batches.push({
        quantity: 0,
        batch_number: "",
        expiry_date: "",
    });
};

const removeBatchRow = (groupIndex: number, batchIndex: number) => {
    const group = form.productGroups[groupIndex];
    if (group.batches.length > 1) {
        group.batches.splice(batchIndex, 1);
    }
};

const getBatchQtyTotal = (groupIndex: number): number => {
    return form.productGroups[groupIndex].batches.reduce(
        (sum: number, b: BatchRow) => sum + (b.quantity || 0),
        0,
    );
};

const getTitle = computed(() => {
    if (order.value?.shipping_method === "pickup")
        return "Verifikasi Pengambilan";
    return "Proses Pengiriman Pesanan";
});

const getIcon = computed(() => {
    if (order.value?.shipping_method === "pickup") return "mdi-store-check";
    if (order.value?.shipping_method === "courier_express")
        return "mdi-truck-fast";
    return "mdi-truck-outline";
});

const getSubmitText = computed(() => {
    if (order.value?.shipping_method === "pickup") return "Verifikasi";
    return "Kirim Pesanan";
});

const fetchSchedules = async () => {
    loadingSchedules.value = true;
    try {
        const res = await inventoryService.getExpressSchedules();
        if (res.data?.success) {
            schedules.value = res.data.data.results.map((s: any) => ({
                ...s,
                time_formatted: formatDateTime(s.time),
            }));

            // Auto-select first available schedule if none selected
            if (!form.pickup_schedule) {
                const firstAvailable = schedules.value.find(
                    (s) => s.is_available,
                );
                if (firstAvailable) {
                    form.pickup_schedule = firstAvailable.time;
                }
            }
        }
    } catch (error) {
        console.error("Gagal mengambil jadwal pickup express", error);
        snackbar.showMessage("Gagal memuat jadwal pickup.", "error");
    } finally {
        loadingSchedules.value = false;
    }
};

const onPickupMethodChange = () => {
    if (form.pickup_method !== "PICKUP") {
        form.pickup_schedule = "";
    } else if (schedules.value.length === 0) {
        fetchSchedules();
    }
};

const open = async (item: any) => {
    dialog.value = true;
    order.value = item;
    loadingData.value = true;

    // Reset form
    form.delivery_note_number = "";
    form.pickup_method = "";
    form.pickup_schedule = "";
    form.tracking_number = "";
    form.pickup_pin = "";
    form.productGroups = [];

    if (formRef.value) formRef.value.resetValidation();

    try {
        const res = await inventoryService.getShipmentDetail(item.id);

        if (res.data?.data) {
            order.value = res.data.data;
            form.productGroups =
                order.value.details?.map((d: any) => ({
                    product_id: d.product.id,
                    product_name: d.product.name,
                    product_code: d.product.code || "",
                    order_quantity: d.quantity,
                    batches: [
                        {
                            quantity: d.quantity,
                            batch_number: "",
                            expiry_date: "",
                        },
                    ],
                })) || [];
        }
    } catch (error) {
        console.error("Gagal mengambil detail pesanan:", error);
        snackbar.showMessage("Gagal memuat detail pesanan.", "error");
    } finally {
        loadingData.value = false;
    }

    // Auto-fetch if express
    if (order.value.shipping_method === "courier_express") {
        fetchSchedules();
    }
};

const close = () => {
    dialog.value = false;
    order.value = null;
};

/** Flatten productGroups into items[] for the API payload */
const flattenItems = (): any[] => {
    const items: any[] = [];
    for (const group of form.productGroups) {
        for (const batch of group.batches) {
            if (batch.quantity > 0) {
                items.push({
                    product_id: group.product_id,
                    quantity: batch.quantity,
                    batch_number: batch.batch_number,
                    expiry_date: batch.expiry_date,
                });
            }
        }
    }
    return items;
};

const submit = async () => {
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    const items = flattenItems();

    loading.value = true;
    try {
        if (order.value.shipping_method === "pickup") {
            await inventoryService.shipOrder(order.value.id, {
                pickup_pin: form.pickup_pin,
                delivery_note_number: form.delivery_note_number,
                items,
            });
            snackbar.showMessage(
                "Pesanan berhasil diverifikasi dan diserahkan.",
                "success",
            );
        } else {
            let payload: any = {
                delivery_note_number: form.delivery_note_number,
                items,
            };
            if (order.value.shipping_method === "courier_express") {
                payload.pickup_method = form.pickup_method;
                if (form.pickup_method === "PICKUP") {
                    payload.pickup_schedule = form.pickup_schedule;
                } else if (form.pickup_method === "DROP-OFF") {
                    const d = new Date();
                    d.setHours(d.getHours() + 1);
                    payload.pickup_schedule =
                        d.getFullYear() +
                        "-" +
                        String(d.getMonth() + 1).padStart(2, "0") +
                        "-" +
                        String(d.getDate()).padStart(2, "0") +
                        " " +
                        String(d.getHours()).padStart(2, "0") +
                        ":" +
                        String(d.getMinutes()).padStart(2, "0") +
                        ":" +
                        String(d.getSeconds()).padStart(2, "0");
                }
            } else if (order.value.shipping_method === "courier_manual") {
                payload.tracking_number = form.tracking_number;
            }

            await inventoryService.shipOrder(order.value.id, payload);
            snackbar.showMessage("Pesanan berhasil dikirim.", "success");
        }

        emit("success");
        close();
    } catch (error: any) {
        console.error("Gagal memproses pengiriman", error);
        const errMessage =
            error.response?.data?.message ||
            "Terjadi kesalahan saat memproses pengiriman.";
        snackbar.showMessage(errMessage, "error");
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, close });
</script>
