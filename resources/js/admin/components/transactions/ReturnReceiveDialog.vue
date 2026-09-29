<template>
    <v-dialog v-model="dialog" max-width="480" persistent>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-avatar color="primary" variant="tonal" size="48">
                        <v-icon>mdi-package-down</v-icon>
                    </v-avatar>
                    <div>
                        <div class="text-h6 font-weight-bold">
                            Konfirmasi Terima Barang
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            Barang retur dari mitra telah diterima perusahaan.
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

            <v-card-text class="pa-6">
                <v-alert
                    type="info"
                    variant="tonal"
                    density="compact"
                >
                    Pastikan barang retur telah diterima perusahaan. Nomor surat
                    jalan mengikuti data pengiriman retur yang sudah tercatat.
                </v-alert>
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
                    color="primary"
                    variant="flat"
                    class="rounded-lg px-6"
                    :loading="submitting"
                    @click="submit"
                >
                    Konfirmasi Terima
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import transactionReturnService from "@/admin/services/transaction-return.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const emit = defineEmits(["success"]);
const snackbar = useSnackbarStore();

const dialog = ref(false);
const submitting = ref(false);
const returnId = ref<number | string>("");

const open = (target: any) => {
    if (typeof target === "object" && target !== null) {
        returnId.value = target.id;
    } else {
        returnId.value = target;
    }
    dialog.value = true;
};

const close = () => {
    dialog.value = false;
};

const submit = async () => {
    submitting.value = true;
    try {
        await transactionReturnService.receive(returnId.value);
        snackbar.showMessage("Berhasil mengkonfirmasi terima retur", "success");
        close();
        emit("success");
    } catch (error: any) {
        console.error("Failed to receive return:", error);
        const message =
            error.response?.data?.message ||
            "Gagal mengkonfirmasi terima retur";
        snackbar.showMessage(message, "error");
    } finally {
        submitting.value = false;
    }
};

defineExpose({ open });
</script>
