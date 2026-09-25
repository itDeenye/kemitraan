<template>
    <v-dialog v-model="dialog" max-width="850" scrollable persistent>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-avatar color="success" variant="tonal" size="48">
                        <v-icon>{{
                            isPickupReplacement
                                ? "mdi-barcode-scan"
                                : "mdi-truck-fast-outline"
                        }}</v-icon>
                    </v-avatar>
                    <div>
                        <div class="text-h6 font-weight-bold">
                            {{
                                isPickupReplacement
                                    ? "Input No Batch Baru"
                                    : "Kirim Barang Pengganti"
                            }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ returnData?.code || "" }}
                        </div>
                    </div>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    @click="close"
                    :disabled="submitting"
                />
            </v-card-title>

            <v-card-text class="pa-6" style="max-height: 65vh">
                <v-form
                    ref="formRef"
                    v-model="isFormValid"
                    @submit.prevent="submit"
                >
                    <div class="d-flex flex-column ga-5">
                        <v-text-field
                            v-model="form.delivery_note_number"
                            :label="
                                isPickupReplacement
                                    ? 'Nomor Surat Jalan Baru *'
                                    : 'Nomor Surat Jalan *'
                            "
                            variant="outlined"
                            density="compact"
                            hide-details="auto"
                            :rules="[rules.required('Nomor Surat Jalan')]"
                        />

                        <v-select
                            v-if="!isPickupReplacement"
                            v-model="form.shipping_method"
                            :items="shippingMethodOptions"
                            item-title="label"
                            item-value="value"
                            label="Metode Pengiriman *"
                            variant="outlined"
                            density="compact"
                            :disabled="isExpressReplacement"
                            hide-details="auto"
                            :rules="[rules.requiredSelect('Metode Pengiriman')]"
                            @update:model-value="onShippingMethodChange"
                        />

                        <v-card
                            v-if="!isPickupReplacement"
                            variant="outlined"
                            class="rounded-lg pa-4"
                        >
                            <h4 class="text-subtitle-2 font-weight-bold mb-3">
                                Detail Kurir
                            </h4>

                            <div
                                v-if="
                                    form.shipping_method === 'courier_express'
                                "
                            >
                                <div
                                    v-if="loadingCouriers"
                                    class="d-flex justify-center py-6"
                                >
                                    <v-progress-circular
                                        indeterminate
                                        color="primary"
                                        size="32"
                                    />
                                </div>
                                <div
                                    v-else-if="courierOptions.length === 0"
                                    class="text-body-2 text-medium-emphasis text-center py-4"
                                >
                                    Tidak ada pilihan kurir tersedia.
                                </div>
                                <div v-else class="d-flex flex-column ga-2">
                                    <v-card
                                        v-for="(option, idx) in courierOptions"
                                        :key="idx"
                                        variant="outlined"
                                        class="rounded-lg cursor-pointer"
                                        :class="{
                                            'border-primary':
                                                selectedCourierIdx === idx,
                                        }"
                                        :color="
                                            selectedCourierIdx === idx
                                                ? 'primary'
                                                : undefined
                                        "
                                        @click="selectedCourierIdx = idx"
                                    >
                                        <div
                                            class="d-flex align-center pa-3 ga-3"
                                        >
                                            <v-avatar
                                                size="40"
                                                rounded="lg"
                                                class="bg-white border"
                                            >
                                                <v-img
                                                    v-if="option.logo_url"
                                                    :src="option.logo_url"
                                                    :alt="option.courier_name"
                                                    contain
                                                />
                                                <v-icon v-else color="grey"
                                                    >mdi-truck-outline</v-icon
                                                >
                                            </v-avatar>
                                            <div class="flex-grow-1">
                                                <div
                                                    class="text-body-2 font-weight-bold"
                                                >
                                                    {{ option.courier_name }}
                                                </div>
                                                <div
                                                    class="text-caption text-medium-emphasis"
                                                >
                                                    {{ option.service_type }}
                                                    <span v-if="option.etd">
                                                        ·
                                                        {{ option.etd }}
                                                        hari</span
                                                    >
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div
                                                    class="text-body-2 font-weight-bold text-primary"
                                                >
                                                    Rp{{
                                                        option.cost.toLocaleString(
                                                            "id-ID",
                                                        )
                                                    }}
                                                </div>
                                                <div
                                                    v-if="option.insurance > 0"
                                                    class="text-caption text-medium-emphasis"
                                                >
                                                    + Asuransi Rp{{
                                                        option.insurance.toLocaleString(
                                                            "id-ID",
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                            <v-radio-group
                                                :model-value="
                                                    selectedCourierIdx
                                                "
                                                hide-details
                                                class="flex-grow-0"
                                            >
                                                <v-radio :value="idx" />
                                            </v-radio-group>
                                        </div>
                                    </v-card>
                                </div>

                                <template
                                    v-if="
                                        selectedCourierIdx !== null &&
                                        selectedCourier?.drop_off_available
                                    "
                                >
                                    <h4
                                        class="text-subtitle-2 font-weight-bold mt-4 mb-2 d-flex align-center ga-2"
                                    >
                                        <v-icon size="small" color="primary"
                                            >mdi-package-variant-closed</v-icon
                                        >
                                        Tipe Pickup Kurir
                                    </h4>
                                    <v-row dense>
                                        <v-col cols="6">
                                            <v-card
                                                variant="outlined"
                                                class="d-flex align-start rounded-lg cursor-pointer h-100"
                                                :class="{
                                                    'border-primary':
                                                        pickupMethod ===
                                                        'PICKUP',
                                                }"
                                                :color="
                                                    pickupMethod === 'PICKUP'
                                                        ? 'primary'
                                                        : undefined
                                                "
                                                @click="pickupMethod = 'PICKUP'"
                                            >
                                                <div
                                                    class="pa-3 d-flex align-start ga-3"
                                                >
                                                    <v-radio
                                                        :model-value="
                                                            pickupMethod
                                                        "
                                                        value="PICKUP"
                                                        hide-details
                                                        density="compact"
                                                        class="mt-n1"
                                                    />
                                                    <div>
                                                        <div
                                                            class="text-body-2 font-weight-bold"
                                                        >
                                                            Dijemput Kurir
                                                        </div>
                                                        <div
                                                            class="text-caption text-medium-emphasis"
                                                        >
                                                            Kurir akan menjemput
                                                            paket (Pickup)
                                                        </div>
                                                    </div>
                                                </div>
                                            </v-card>
                                        </v-col>
                                        <v-col cols="6">
                                            <v-card
                                                variant="outlined"
                                                class="d-flex align-start rounded-lg cursor-pointer h-100"
                                                :class="{
                                                    'border-primary':
                                                        pickupMethod ===
                                                        'DROP-OFF',
                                                }"
                                                :color="
                                                    pickupMethod === 'DROP-OFF'
                                                        ? 'primary'
                                                        : undefined
                                                "
                                                @click="
                                                    pickupMethod = 'DROP-OFF'
                                                "
                                            >
                                                <div
                                                    class="pa-3 d-flex align-start ga-3"
                                                >
                                                    <v-radio
                                                        :model-value="
                                                            pickupMethod
                                                        "
                                                        value="DROP-OFF"
                                                        hide-details
                                                        density="compact"
                                                        class="mt-n1"
                                                    />
                                                    <div>
                                                        <div
                                                            class="text-body-2 font-weight-bold"
                                                        >
                                                            Antar ke Gerai
                                                        </div>
                                                        <div
                                                            class="text-caption text-medium-emphasis"
                                                        >
                                                            Serahkan paket ke
                                                            gerai kurir terdekat
                                                        </div>
                                                    </div>
                                                </div>
                                            </v-card>
                                        </v-col>
                                    </v-row>
                                </template>

                                <template
                                    v-if="
                                        selectedCourierIdx !== null &&
                                        pickupMethod === 'PICKUP'
                                    "
                                >
                                    <div
                                        v-if="loadingSchedules"
                                        class="d-flex justify-center py-4"
                                    >
                                        <v-progress-circular
                                            indeterminate
                                            color="primary"
                                            size="24"
                                        />
                                    </div>
                                    <div
                                        v-else-if="pickupSchedules.length === 0"
                                        class="text-body-2 text-medium-emphasis text-center py-3"
                                    >
                                        Tidak ada jadwal tersedia.
                                    </div>
                                    <v-select
                                        v-else
                                        v-model="selectedSchedule"
                                        :items="availableSchedules"
                                        item-title="label"
                                        item-value="time"
                                        label="Pilih Jadwal *"
                                        placeholder="Pilih jadwal pengambilan paket"
                                        variant="outlined"
                                        density="compact"
                                        hide-details="auto"
                                        class="mt-4"
                                        :rules="[
                                            rules.requiredSelect(
                                                'Jadwal Pengambilan',
                                            ),
                                        ]"
                                    />
                                </template>
                            </div>

                            <div
                                v-else-if="
                                    form.shipping_method === 'courier_manual'
                                "
                                class="d-flex flex-column ga-3"
                            >
                                <v-row dense>
                                    <v-col cols="6">
                                        <v-text-field
                                            v-model="courier.name"
                                            label="Nama Kurir *"
                                            variant="outlined"
                                            density="compact"
                                            hide-details="auto"
                                            placeholder="Contoh: JNE / J&T"
                                            :rules="[
                                                rules.required('Nama Kurir'),
                                            ]"
                                        />
                                    </v-col>
                                    <v-col cols="6">
                                        <v-text-field
                                            v-model="courier.service"
                                            label="Layanan *"
                                            variant="outlined"
                                            density="compact"
                                            hide-details="auto"
                                            placeholder="Contoh: REG / YES"
                                            :rules="[rules.required('Layanan')]"
                                        />
                                    </v-col>
                                </v-row>
                                <v-row dense>
                                    <v-col cols="6">
                                        <v-text-field
                                            v-model="courier.tracking_number"
                                            label="Nomor Resi *"
                                            variant="outlined"
                                            density="compact"
                                            hide-details="auto"
                                            placeholder="Masukkan Nomor Resi"
                                            :rules="[
                                                rules.required('Nomor Resi'),
                                            ]"
                                        />
                                    </v-col>
                                    <v-col cols="6">
                                        <v-text-field
                                            v-model.number="form.shipping_cost"
                                            label="Biaya Pengiriman (Rp)"
                                            variant="outlined"
                                            density="compact"
                                            type="number"
                                            hide-details="auto"
                                            :rules="[
                                                rules.requiredNumber(
                                                    'Biaya Pengiriman',
                                                ),
                                            ]"
                                        />
                                    </v-col>
                                </v-row>
                            </div>

                            <div
                                v-else-if="form.shipping_method === 'pickup'"
                                class="d-flex flex-column ga-3"
                            >
                                <v-text-field
                                    v-model="courier.pickup_schedule"
                                    label="Jadwal Pengambilan"
                                    variant="outlined"
                                    density="compact"
                                    type="datetime-local"
                                    hide-details="auto"
                                />
                            </div>

                            <div
                                v-else
                                class="text-medium-emphasis text-body-2"
                            >
                                Pilih metode pengiriman terlebih dahulu.
                            </div>
                        </v-card>

                        <div>
                            <h4 class="text-subtitle-2 font-weight-bold mb-3">
                                Produk Pengganti
                            </h4>
                            <div
                                v-for="(item, idx) in formItems"
                                :key="idx"
                                class="border rounded-lg overflow-hidden mb-4"
                            >
                                <div
                                    class="bg-surface-light px-4 py-3 border-b d-flex align-center justify-space-between"
                                >
                                    <div class="d-flex align-center ga-3">
                                        <div class="font-weight-medium">
                                            {{ item.product_name }}
                                        </div>
                                    </div>
                                    <div class="d-flex align-center ga-4">
                                        <div class="text-caption">
                                            Qty Pesanan:
                                            <span
                                                class="font-weight-bold ml-1"
                                                >{{
                                                    formatPrice(
                                                        item.order_quantity,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                        <v-btn
                                            size="small"
                                            variant="text"
                                            color="primary"
                                            @click="addBatchRow(idx)"
                                        >
                                            <v-icon size="small" class="mr-1"
                                                >mdi-plus</v-icon
                                            >
                                            Batch
                                        </v-btn>
                                    </div>
                                </div>

                                <div
                                    v-if="
                                        getBatchQtyTotal(idx) !==
                                        item.order_quantity
                                    "
                                    class="pa-3 text-caption font-weight-medium border-b"
                                    style="
                                        background-color: #feeceb;
                                        color: #e53935;
                                    "
                                >
                                    Total qty batch ({{
                                        formatPrice(getBatchQtyTotal(idx))
                                    }}) tidak sama dengan qty pesanan ({{
                                        formatPrice(item.order_quantity)
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
                                            ) in item.batches"
                                            :key="batchIndex"
                                        >
                                            <td
                                                class="pa-2 pt-4"
                                                style="vertical-align: top"
                                            >
                                                <v-text-field
                                                    v-model.number="
                                                        batch.quantity
                                                    "
                                                    type="number"
                                                    variant="outlined"
                                                    density="compact"
                                                    hide-details="auto"
                                                    min="1"
                                                    placeholder="Qty"
                                                    :readonly="
                                                        isPickupReplacement
                                                    "
                                                    :rules="[
                                                        rules.requiredNumber(
                                                            'Qty',
                                                        ),
                                                        rules.minOne,
                                                    ]"
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
                                                    v-if="
                                                        item.batches.length > 1
                                                    "
                                                    icon="mdi-delete"
                                                    color="error"
                                                    variant="text"
                                                    size="small"
                                                    @click="
                                                        removeBatchRow(
                                                            idx,
                                                            batchIndex,
                                                        )
                                                    "
                                                ></v-btn>
                                            </td>
                                        </tr>
                                    </tbody>
                                </v-table>
                            </div>
                        </div>

                        <v-textarea
                            v-model="form.note"
                            :label="
                                isPickupReplacement
                                    ? 'Catatan Batch'
                                    : 'Catatan Pengiriman'
                            "
                            variant="outlined"
                            density="compact"
                            rows="2"
                            hide-details="auto"
                            placeholder="Opsional"
                        />
                    </div>
                </v-form>
            </v-card-text>

            <v-divider />

            <v-card-actions class="pa-4">
                <v-spacer />
                <v-btn
                    variant="outlined"
                    class="rounded-lg px-6"
                    @click="close"
                    :disabled="submitting"
                >
                    Batal
                </v-btn>
                <v-btn
                    color="success"
                    variant="flat"
                    class="rounded-lg px-6"
                    :loading="submitting"
                    :disabled="!canSubmit"
                    @click="submit"
                >
                    {{
                        isPickupReplacement
                            ? "Simpan Batch Baru"
                            : "Kirim Pengganti"
                    }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from "vue";
import transactionReturnService from "@/admin/services/transaction-return.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import type {
    TransactionReturnDetail,
    ReturnShipReplacementPayload,
    ReturnCourierOption,
    ReturnPickupSchedule,
} from "@/admin/types/transaction-return";

import { useSnackbarStore } from "@/shared/stores/snackbar";

const { formatPrice } = useFormatter();
const emit = defineEmits(["success"]);
const snackbar = useSnackbarStore();

const dialog = ref(false);
const submitting = ref(false);
const returnData = ref<TransactionReturnDetail | null>(null);

const formRef = ref();
const isFormValid = ref(false);

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
    requiredSelect: (field: string) => (v: any) =>
        !!v || `${field} wajib dipilih`,
    requiredNumber: (field: string) => (v: any) =>
        !!v || `${field} wajib diisi`,
    minOne: (v: any) => v > 0 || "Minimal 1",
};

const form = reactive({
    note: "",
    delivery_note_number: "",
    shipping_method: "",
    shipping_cost: 0,
});

const courier = reactive<Record<string, any>>({});

interface FormItemBatch {
    batch_number: string;
    expiry_date: string;
    quantity: number;
}

interface FormItem {
    product_id: number;
    product_name: string;
    order_quantity: number;
    batches: FormItemBatch[];
}

const formItems = ref<FormItem[]>([]);

const addBatchRow = (groupIndex: number) => {
    formItems.value[groupIndex].batches.push({
        batch_number: "",
        expiry_date: "",
        quantity: 1,
    });
};

const removeBatchRow = (groupIndex: number, batchIndex: number) => {
    if (formItems.value[groupIndex].batches.length > 1) {
        formItems.value[groupIndex].batches.splice(batchIndex, 1);
    }
};

const getBatchQtyTotal = (groupIndex: number) => {
    return formItems.value[groupIndex].batches.reduce(
        (acc, batch) => acc + (Number(batch.quantity) || 0),
        0,
    );
};

const allShippingMethodOptions = [
    { value: "courier_express", label: "Kurir Ekspres" },
    { value: "courier_manual", label: "Kurir" },
    { value: "pickup", label: "Ambil Sendiri" },
];

const { formatDateTime } = useFormatter();

const loadingCouriers = ref(false);
const courierOptions = ref<ReturnCourierOption[]>([]);
const selectedCourierIdx = ref<number | null>(null);
const pickupMethod = ref<string>("PICKUP");

const loadingSchedules = ref(false);
const pickupSchedules = ref<ReturnPickupSchedule[]>([]);
const selectedSchedule = ref<string>("");

const selectedCourier = computed(() => {
    if (selectedCourierIdx.value === null) return null;
    return courierOptions.value[selectedCourierIdx.value] ?? null;
});

const isPickupReplacement = computed(
    () =>
        returnData.value?.return_shipping?.method === "pickup" ||
        returnData.value?.return_shipping?.shipping_method === "pickup",
);

const isExpressReplacement = computed(
    () =>
        returnData.value?.return_shipping?.method === "courier_express" ||
        returnData.value?.return_shipping?.shipping_method ===
            "courier_express",
);

const shippingMethodOptions = computed(() =>
    isExpressReplacement.value
        ? allShippingMethodOptions.filter(
              (option) => option.value === "courier_express",
          )
        : allShippingMethodOptions,
);

const availableSchedules = computed(() =>
    pickupSchedules.value
        .filter((s) => s.is_available)
        .map((s) => ({
            time: s.time,
            label: formatDateTime(s.time),
        })),
);

const canSubmit = computed(() => {
    if (!isFormValid.value || formItems.value.length === 0) return false;

    const hasInvalidQty = formItems.value.some(
        (g, idx) => getBatchQtyTotal(idx) !== g.order_quantity,
    );
    if (hasInvalidQty) return false;

    if (form.shipping_method === "courier_express") {
        if (selectedCourierIdx.value === null) return false;
        if (pickupMethod.value === "PICKUP" && !selectedSchedule.value)
            return false;
    }
    return true;
});

const loadCourierOptions = async () => {
    if (!returnData.value) return;
    loadingCouriers.value = true;
    courierOptions.value = [];
    selectedCourierIdx.value = null;
    selectedSchedule.value = "";
    try {
        const items = formItems.value.map((item) => ({
            product_id: item.product_id,
            quantity: item.order_quantity,
        }));
        const result = await transactionReturnService.getCourierOptions(
            returnData.value.id,
            "return_replacement",
            { couriers: ["jne", "jnt"], items },
        );
        courierOptions.value = result.results || [];
    } catch (error: any) {
        console.error("Failed to load courier options:", error);
        snackbar.showMessage(
            error.response?.data?.message || "Gagal memuat pilihan kurir",
            "error",
        );
    } finally {
        loadingCouriers.value = false;
    }
};

const loadSchedules = async () => {
    if (!returnData.value) return;
    loadingSchedules.value = true;
    pickupSchedules.value = [];
    selectedSchedule.value = "";
    try {
        const result = await transactionReturnService.getPickupSchedules(
            returnData.value.id,
            "return_replacement",
        );
        pickupSchedules.value = result.results || [];
        const firstAvailable = availableSchedules.value[0];
        if (firstAvailable) selectedSchedule.value = firstAvailable.time;
    } catch (error: any) {
        console.error("Failed to load schedules:", error);
        snackbar.showMessage(
            error.response?.data?.message || "Gagal memuat jadwal pengambilan",
            "error",
        );
    } finally {
        loadingSchedules.value = false;
    }
};

const onShippingMethodChange = async () => {
    Object.keys(courier).forEach((key) => delete courier[key]);
    if (form.shipping_method === "pickup") {
        form.shipping_cost = 0;
    }
    if (form.shipping_method === "courier_express") {
        await loadCourierOptions();
    }
};

watch([selectedCourierIdx, pickupMethod], async ([courierIdx, method]) => {
    if (
        courierIdx === null ||
        method !== "PICKUP" ||
        !returnData.value ||
        form.shipping_method !== "courier_express"
    ) {
        return;
    }
    await loadSchedules();
});

const resetForm = () => {
    form.note = "";
    form.delivery_note_number = "";
    form.shipping_method = "";
    form.shipping_cost = 0;
    Object.keys(courier).forEach((key) => delete courier[key]);
    formItems.value = [];
};

const open = (detail: TransactionReturnDetail) => {
    resetForm();
    returnData.value = detail;
    formItems.value = (detail.details || []).map((d) => ({
        product_id: d.product.id,
        product_name: d.product.name,
        order_quantity:
            d.replacement_quantity ?? d.received_quantity ?? d.quantity,
        batches: [
            {
                batch_number: "",
                expiry_date: "",
                quantity:
                    d.replacement_quantity ?? d.received_quantity ?? d.quantity,
            },
        ],
    }));
    if (
        detail.return_shipping?.method === "pickup" ||
        detail.return_shipping?.shipping_method === "pickup"
    ) {
        form.shipping_method = "pickup";
    } else if (
        detail.return_shipping?.method === "courier_express" ||
        detail.return_shipping?.shipping_method === "courier_express"
    ) {
        form.shipping_method = "courier_express";
        void onShippingMethodChange();
    }
    if (formRef.value) formRef.value.resetValidation();
    dialog.value = true;
};

const close = () => {
    dialog.value = false;
};

const buildCourierPayload = (): any => {
    if (form.shipping_method === "courier_express") {
        if (!selectedCourier.value) return {};
        return {
            name: selectedCourier.value.courier_code,
            service: selectedCourier.value.service_type,
            type: selectedCourier.value.service_type,
            cost: selectedCourier.value.cost,
            insurance: selectedCourier.value.insurance || 0,
            force_insurance: selectedCourier.value.force_insurance || false,
            etd: selectedCourier.value.etd || "",
            pickup_method: pickupMethod.value,
            ...(pickupMethod.value === "PICKUP"
                ? {
                      pickup_schedule: selectedSchedule.value,
                  }
                : { pickup_schedule: "2099-12-31 23:59:59" }),
        };
    }
    if (form.shipping_method === "courier_manual") {
        return {
            name: courier.name || "",
            service: courier.service || "",
            tracking_number: courier.tracking_number || "",
            cost: form.shipping_cost,
        };
    }
    if (form.shipping_method === "pickup") {
        return {};
    }
    return {};
};

const submit = async () => {
    const { valid } = await formRef.value.validate();
    if (!valid) return;
    if (!returnData.value) return;

    submitting.value = true;
    try {
        const payload = {
            note: form.note,
            delivery_note_number: form.delivery_note_number,
            shipping_method: form.shipping_method,
            items: formItems.value.flatMap((item) =>
                item.batches.map((batch) => ({
                    product_id: item.product_id,
                    quantity: batch.quantity,
                    batch_number: batch.batch_number,
                    expiry_date: batch.expiry_date,
                })),
            ),
            courier: buildCourierPayload(),
        } as ReturnShipReplacementPayload;
        await transactionReturnService.shipReplacement(
            returnData.value.id,
            payload,
        );
        snackbar.showMessage(
            isPickupReplacement.value
                ? "Batch barang pengganti berhasil disimpan"
                : "Berhasil mengirim pengganti",
            "success",
        );
        close();
        emit("success");
    } catch (error: any) {
        console.error("Failed to ship replacement:", error);
        const message =
            error.response?.data?.message ||
            (isPickupReplacement.value
                ? "Gagal menyimpan batch barang pengganti"
                : "Gagal mengirim barang pengganti");
        snackbar.showMessage(message, "error");
    } finally {
        submitting.value = false;
    }
};

defineExpose({ open });
</script>
