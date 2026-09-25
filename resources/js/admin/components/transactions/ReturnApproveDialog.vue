<template>
    <v-dialog v-model="dialog" max-width="560" persistent>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-avatar color="success" variant="tonal" size="48">
                        <v-icon>mdi-check-decagram-outline</v-icon>
                    </v-avatar>
                    <div>
                        <div class="text-h6 font-weight-bold">
                            Setujui Pengajuan Retur
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ returnData?.code || "-" }}
                        </div>
                    </div>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    :disabled="submitting"
                    @click="close"
                />
            </v-card-title>

            <v-card-text class="pa-6">
                <v-alert
                    :type="isPickup ? 'info' : 'success'"
                    variant="tonal"
                    density="comfortable"
                    class="mb-5 text-body-2"
                >
                    <template v-if="isPickup">
                        Member memilih
                        <strong>Ambil di Tempat (Gudang Perusahaan)</strong>.
                        Masukkan PIN yang ditunjukkan member untuk menyetujui
                        dan menerima barang retur.
                    </template>
                    <template v-else>
                        Kurir, nomor surat jalan, dan nomor batch retur sudah
                        diinputkan member. Setelah disetujui, member memilih
                        jadwal pengiriman melalui halaman retur.
                    </template>
                </v-alert>

                <div class="rounded-lg border pa-4 mb-5">
                    <div class="d-flex justify-space-between ga-4 mb-2">
                        <span class="text-caption text-medium-emphasis">
                            Metode
                        </span>
                        <strong class="text-body-2 text-right">
                            {{ shippingMethodLabel }}
                        </strong>
                    </div>
                    <div
                        v-if="returnData?.return_shipping?.courier"
                        class="d-flex justify-space-between ga-4 mb-2"
                    >
                        <span class="text-caption text-medium-emphasis">
                            Kurir
                        </span>
                        <strong class="text-body-2 text-right">
                            {{ returnData.return_shipping.courier }}
                            {{ returnData.return_shipping.service || "" }}
                        </strong>
                    </div>
                    <div class="d-flex justify-space-between ga-4">
                        <span class="text-caption text-medium-emphasis">
                            Nomor Surat Jalan
                        </span>
                        <strong class="text-body-2 text-right">
                            {{
                                returnData?.return_shipping
                                    ?.delivery_note_number || "-"
                            }}
                        </strong>
                    </div>
                </div>

                <v-form ref="formRef" @submit.prevent="submit">
                    <v-text-field
                        v-if="isPickup"
                        v-model="form.pickup_pin"
                        label="Kode Verifikasi (5 Digit) *"
                        placeholder="Masukkan PIN dari member"
                        variant="outlined"
                        density="compact"
                        maxlength="5"
                        inputmode="numeric"
                        hide-details="auto"
                        :rules="[rules.required, rules.digits5]"
                        class="mb-4"
                    />
                    <v-textarea
                        v-model="form.note"
                        label="Catatan Penyetujuan (Opsional)"
                        placeholder="Tambahkan catatan jika diperlukan"
                        variant="outlined"
                        density="compact"
                        rows="3"
                        maxlength="1000"
                        counter
                        hide-details="auto"
                    />
                </v-form>
            </v-card-text>

            <v-divider />

            <v-card-actions class="pa-4">
                <v-spacer />
                <v-btn
                    variant="outlined"
                    class="rounded-lg px-6"
                    :disabled="submitting"
                    @click="close"
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
                    Setujui Retur
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import transactionReturnService from "@/admin/services/transaction-return.service";
import type {
    ReturnApprovePayload,
    TransactionReturnDetail,
} from "@/admin/types/transaction-return";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const emit = defineEmits(["success"]);
const snackbar = useSnackbarStore();

const dialog = ref(false);
const submitting = ref(false);
const returnData = ref<TransactionReturnDetail | null>(null);
const formRef = ref();
const form = reactive({
    pickup_pin: "",
    note: "",
});

const isPickup = computed(
    () => returnData.value?.return_shipping?.method === "pickup",
);
const canSubmit = computed(
    () => !isPickup.value || /^\d{5}$/.test(form.pickup_pin),
);
const shippingMethodLabel = computed(() => {
    return isPickup.value
        ? "Ambil di Tempat (Gudang Perusahaan)"
        : "Kurir Express";
});

const rules = {
    required: (value: string) => !!value || "Kode verifikasi wajib diisi",
    digits5: (value: string) =>
        /^\d{5}$/.test(value) || "Kode verifikasi harus 5 digit angka",
};

const open = (detail: TransactionReturnDetail) => {
    returnData.value = detail;
    form.pickup_pin = "";
    form.note = "";
    formRef.value?.resetValidation();
    dialog.value = true;
};

const close = () => {
    dialog.value = false;
};

const submit = async () => {
    if (!returnData.value || !canSubmit.value) return;

    const validation = await formRef.value?.validate();
    if (validation && !validation.valid) return;

    const payload: ReturnApprovePayload = {
        note: form.note || undefined,
        ...(isPickup.value ? { pickup_pin: form.pickup_pin } : {}),
    };

    submitting.value = true;
    try {
        await transactionReturnService.approve(returnData.value.id, payload);
        snackbar.showMessage("Berhasil menyetujui retur", "success");
        close();
        emit("success");
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Gagal menyetujui retur",
            "error",
        );
    } finally {
        submitting.value = false;
    }
};

defineExpose({ open });
</script>
