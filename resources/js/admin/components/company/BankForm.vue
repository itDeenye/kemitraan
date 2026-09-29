<template>
    <v-dialog v-model="dialog" max-width="500" persistent scrollable>
        <v-card class="rounded-xl elevation-10">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28">mdi-bank</v-icon>
                    <span class="text-h6 font-weight-bold text-primary">
                        {{ isEdit ? "Edit Bank" : "Tambah Bank" }}
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

            <v-card-text class="pa-6 pt-5" style="max-height: 70vh">
                <v-form
                    ref="formRef"
                    v-model="isFormValid"
                    @submit.prevent="submit"
                >
                    <v-row>
                        <v-col cols="12">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Tipe Rekening <span class="text-error">*</span>
                            </label>
                            <v-select
                                v-model="form.type"
                                :items="[
                                    { title: 'Perusahaan', value: 'company' },
                                    { title: 'Kemitraan', value: 'spread_payment' }
                                ]"
                                :rules="[rules.requiredSelect('Tipe Rekening')]"
                                placeholder="Pilih Tipe Rekening"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-4"
                            ></v-select>

                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Bank <span class="text-error">*</span>
                            </label>
                            <v-autocomplete
                                v-model="form.bank_id"
                                :items="referenceBanks"
                                item-title="name"
                                item-value="id"
                                :rules="[rules.requiredSelect('Bank')]"
                                placeholder="Pilih Bank"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-4"
                            ></v-autocomplete>

                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Nomor Rekening <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.account_number"
                                :rules="[
                                    rules.required('Nomor rekening'),
                                    rules.numberOnly,
                                ]"
                                placeholder="Masukkan nomor rekening"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-4"
                            ></v-text-field>

                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Atas Nama <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.account_name"
                                :rules="[rules.required('Atas nama rekening')]"
                                placeholder="Masukkan atas nama rekening"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-4"
                            ></v-text-field>

                            <v-switch
                                v-model="form.is_active"
                                :label="
                                    form.is_active
                                        ? 'Status: Aktif'
                                        : 'Status: Nonaktif'
                                "
                                color="success"
                                hide-details
                                inset
                            ></v-switch>
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
                    Simpan Rekening
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from "vue";
import type { CompanyBank, CompanyBankPayload } from "@/admin/types/bank";
import type { ReferenceBank } from "@/shared/services/reference.service";
import bankService from "@/admin/services/bank.service";
import referenceService from "@/shared/services/reference.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const emit = defineEmits(["saved"]);
const snackbar = useSnackbarStore();

const dialog = ref(false);
const loading = ref(false);
const formRef = ref<any>(null);
const isFormValid = ref(false);

const referenceBanks = ref<ReferenceBank[]>([]);

const fetchReferenceBanks = async () => {
    try {
        referenceBanks.value = await referenceService.getBanks();
    } catch (error) {
        console.error("Gagal memuat daftar bank referensi", error);
    }
};

onMounted(() => {
    fetchReferenceBanks();
});

const form = reactive<CompanyBankPayload>({
    bank_id: null,
    type: null,
    account_number: "",
    account_name: "",
    is_active: true,
});

const editId = ref<number | null>(null);
const isEdit = computed(() => editId.value !== null);

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
    requiredSelect: (field: string) => (v: any) =>
        !!v || `${field} wajib dipilih`,
    numberOnly: (v: any) => /^\d+$/.test(v) || "Hanya boleh angka",
};

const resetForm = () => {
    if (formRef.value) formRef.value.resetValidation();
    form.bank_id = null;
    form.type = null;
    form.account_number = "";
    form.account_name = "";
    form.is_active = true;
    editId.value = null;
};

const open = (bank?: CompanyBank) => {
    resetForm();
    if (bank) {
        editId.value = bank.id;
        form.bank_id = bank.bank.id;
        form.type = bank.type;
        form.account_number = bank.account_number;
        form.account_name = bank.account_name;
        form.is_active = bank.is_active;
    }
    dialog.value = true;
};

const close = () => {
    dialog.value = false;
    setTimeout(() => {
        resetForm();
    }, 300);
};

const submit = async () => {
    if (!formRef.value) return;
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    loading.value = true;
    try {
        if (isEdit.value && editId.value) {
            await bankService.updateBank(editId.value, form);
            snackbar.showMessage("Bank berhasil diperbarui", "success");
        } else {
            await bankService.createBank(form);
            snackbar.showMessage("Bank berhasil ditambahkan", "success");
        }
        emit("saved");
        close();
    } catch (error: any) {
        const message =
            error.response?.data?.message || "Gagal menyimpan data bank";
        snackbar.showMessage(message, "error");
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, close });
</script>
