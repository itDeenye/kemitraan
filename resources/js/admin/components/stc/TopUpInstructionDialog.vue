<template>
    <v-dialog v-model="dialog" max-width="500" scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28">mdi-credit-card</v-icon>
                    <span class="text-h6 font-weight-bold text-primary">
                        Bank VA STC
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

            <v-card-text class="px-6 py-4">
                <div v-if="loading" class="d-flex justify-center py-8">
                    <v-progress-circular
                        indeterminate
                        color="primary"
                    ></v-progress-circular>
                </div>
                <div
                    v-else-if="options.length === 0"
                    class="text-center py-8 text-medium-emphasis"
                >
                    Tidak ada opsi top up yang tersedia saat ini.
                </div>
                <div v-else>
                    <v-expansion-panels variant="accordion">
                        <v-expansion-panel
                            v-for="option in options"
                            :key="option.id"
                            class="mb-2 border rounded-lg overflow-hidden"
                            elevation="0"
                        >
                            <v-expansion-panel-title class="px-4 py-3">
                                <div class="d-flex align-center gap-3">
                                    <v-avatar
                                        v-if="false"
                                        size="40"
                                        rounded="lg"
                                        color="grey-lighten-4"
                                    >
                                        <v-img
                                            src=""
                                            :alt="option.bank_name"
                                            contain
                                        ></v-img>
                                    </v-avatar>
                                    <div
                                        v-else
                                        class="bg-grey-lighten-4 rounded-lg d-flex align-center justify-center"
                                        style="width: 40px; height: 40px"
                                    >
                                        <v-icon
                                            icon="mdi-bank"
                                            color="grey-darken-1"
                                        ></v-icon>
                                    </div>
                                    <div>
                                        <div
                                            class="font-weight-bold text-body-1"
                                        >
                                            {{ option.bank_name }}
                                        </div>
                                        <div
                                            class="mt-2 px-3 py-2 rounded bg-grey-lighten-4 d-inline-flex align-center border"
                                            style="gap: 16px"
                                        >
                                            <div>
                                                <div
                                                    class="text-caption text-medium-emphasis mb-1"
                                                    style="line-height: 1"
                                                >
                                                    Nomor Virtual Account
                                                </div>
                                                <div
                                                    class="font-weight-bold text-primary text-subtitle-2"
                                                    style="
                                                        line-height: 1;
                                                        letter-spacing: 0.5px;
                                                    "
                                                >
                                                    {{ option.virtual_account }}
                                                </div>
                                            </div>
                                            <v-btn
                                                icon="mdi-content-copy"
                                                size="small"
                                                variant="text"
                                                color="primary"
                                                density="comfortable"
                                                @click.stop="
                                                    copyToClipboard(
                                                        option.virtual_account,
                                                    )
                                                "
                                            ></v-btn>
                                        </div>
                                    </div>
                                </div>
                            </v-expansion-panel-title>
                            <v-expansion-panel-text
                                class="bg-grey-lighten-5 pt-3"
                            >
                                <div
                                    class="text-subtitle-2 font-weight-bold mb-2"
                                >
                                    Cara Top Up:
                                </div>
                                <div
                                    class="text-body-2 mb-2"
                                    style="line-height: 1.6"
                                >
                                    Silakan transfer ke nomor virtual account di
                                    atas sejumlah saldo yang ingin diisi.
                                    Pastikan menambahkan kode
                                    <span
                                        class="font-weight-bold text-primary"
                                        >{{ responseData?.top_up_code }}</span
                                    >
                                    di akhir nominal transfer Anda (Contoh: Rp
                                    1.000.<span
                                        class="font-weight-bold text-primary"
                                        >{{ responseData?.top_up_code }}</span
                                    >).
                                </div>
                            </v-expansion-panel-text>
                        </v-expansion-panel>
                    </v-expansion-panels>
                </div>
            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions class="pa-6 pt-4 border-t bg-surface">
                <v-spacer />
                <v-btn
                    variant="flat"
                    color="primary"
                    class="text-none px-6"
                    @click="close"
                    >Tutup</v-btn
                >
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, watch, computed } from "vue";
import stcService from "@/admin/services/stc.service";
import type { TopUpOptionsResponse } from "@/admin/types/stc";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const dialog = ref(false);
const loading = ref(false);
const responseData = ref<TopUpOptionsResponse | null>(null);
const options = computed(() => responseData.value?.banks || []);
const snackbar = useSnackbarStore();

const fetchOptions = async () => {
    loading.value = true;
    try {
        responseData.value = await stcService.getTopUpOptions();
    } catch (e: any) {
        console.error(e);
        snackbar.showMessage(
            e.response?.data?.message || "Gagal memuat opsi top up",
            "error",
        );
    } finally {
        loading.value = false;
    }
};

const open = () => {
    dialog.value = true;
    if (options.value.length === 0) {
        fetchOptions();
    }
};

const close = () => {
    dialog.value = false;
};

const copyToClipboard = async (text: string) => {
    try {
        await navigator.clipboard.writeText(text);
        snackbar.showMessage(
            "Nomor Virtual Account berhasil disalin",
            "success",
        );
    } catch (e) {
        snackbar.showMessage("Gagal menyalin nomor rekening", "error");
    }
};

defineExpose({
    open,
    close,
});
</script>

<style scoped>
.gap-3 {
    gap: 12px;
}
.border {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.border-b {
    border-bottom: 1px solid
        rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
