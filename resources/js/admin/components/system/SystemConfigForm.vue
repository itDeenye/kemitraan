<template>
    <v-dialog v-model="dialog" max-width="600" persistent scrollable>
        <v-card class="rounded-xl elevation-10">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28">mdi-cog-outline</v-icon>
                    <span class="text-h6 font-weight-bold text-primary">
                        {{ config?.label || "Konfigurasi" }}
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

            <v-card-text class="pa-6 pt-5" style="max-height: 70vh">
                <v-form
                    ref="formRef"
                    v-model="isFormValid"
                    @submit.prevent="submit"
                >
                    <v-row>
                        <v-col
                            v-for="entry in editableEntries"
                            :key="entry.key"
                            cols="12"
                        >
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                {{ entry.label }}
                            </label>

                            <v-switch
                                v-if="typeof entry.value === 'boolean'"
                                v-model="formValues[entry.key]"
                                :label="
                                    formValues[entry.key] ? 'Aktif' : 'Nonaktif'
                                "
                                color="success"
                                hide-details
                                inset
                                class="mb-2"
                            ></v-switch>

                            <v-text-field
                                v-else-if="typeof entry.value === 'number'"
                                v-model.number="formValues[entry.key]"
                                :rules="[rules.required(entry.label)]"
                                type="number"
                                :suffix="
                                    isPercentageField(entry.key) ? '%' : ''
                                "
                                :prefix="isCurrencyField(entry.key) ? 'Rp' : ''"
                                :step="
                                    isPercentageField(entry.key) ? '0.01' : '1'
                                "
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-2"
                            ></v-text-field>

                            <v-text-field
                                v-else
                                v-model="formValues[entry.key]"
                                :rules="[rules.required(entry.label)]"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-2"
                                :disabled="entry.key === 'seeded_at'"
                            ></v-text-field>
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
                    @click="submit"
                    :loading="loading"
                    :disabled="!isFormValid"
                >
                    Simpan
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import type { SystemConfig } from "@/admin/types/system-config";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const props = defineProps<{
    saveHandler: (
        id: number,
        values: Record<string, any>,
        config: SystemConfig,
    ) => Promise<void>;
}>();

const emit = defineEmits(["saved"]);
const snackbar = useSnackbarStore();

const dialog = ref(false);
const loading = ref(false);
const formRef = ref<any>(null);
const isFormValid = ref(true);

const config = ref<SystemConfig | null>(null);
const editableEntries = ref<{ label: string; key: string; value: any }[]>([]);
const formValues = reactive<Record<string, any>>({});

const isPercentageField = (key: string) =>
    key.includes("percentage") || key.includes("basis_points");
const isCurrencyField = (key: string) => key.includes("amount");

const rules = {
    required: (field: string) => (v: any) =>
        v !== null && v !== undefined && v !== ""
            ? true
            : `${field} wajib diisi`,
};

const open = (item: SystemConfig) => {
    config.value = item;

    // Clear reactive form values
    Object.keys(formValues).forEach((key) => delete formValues[key]);

    // Populate from entries
    editableEntries.value = item.entries.map((entry) => ({
        label: entry.label,
        key: entry.key,
        value: entry.value,
    }));

    for (const entry of item.entries) {
        let val = entry.value;
        if (entry.key.includes("basis_points") && typeof val === "number") {
            val = val / 100;
        }
        formValues[entry.key] = val;
    }

    if (formRef.value) {
        formRef.value.resetValidation();
    }

    dialog.value = true;
};

const close = () => {
    dialog.value = false;
};

const submit = async () => {
    if (formRef.value) {
        const { valid } = await formRef.value.validate();
        if (!valid) return;
    }

    if (!config.value) return;

    loading.value = true;
    try {
        const payload = { ...formValues };
        for (const entry of config.value.entries) {
            if (
                entry.key.includes("basis_points") &&
                typeof payload[entry.key] === "number"
            ) {
                payload[entry.key] = Math.round(payload[entry.key] * 100);
            }
        }

        await props.saveHandler(config.value.id, payload, config.value);
        snackbar.showMessage("Konfigurasi berhasil diperbarui", "success");
        emit("saved");
        close();
    } catch (error: any) {
        const message =
            error.response?.data?.message || "Gagal memperbarui konfigurasi";
        snackbar.showMessage(message, "error");
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, close });
</script>
