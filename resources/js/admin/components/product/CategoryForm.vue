<template>
    <v-dialog v-model="dialog" max-width="600" persistent scrollable>
        <v-card class="rounded-xl elevation-10">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28">mdi-tag-multiple</v-icon>
                    <span class="text-h6 font-weight-bold text-primary">
                        {{ isEdit ? "Edit Kategori" : "Tambah Kategori" }}
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
                                Nama Kategori <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.name"
                                :rules="[rules.required('Nama kategori')]"
                                placeholder="Masukkan nama kategori"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-4"
                            ></v-text-field>

                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Deskripsi
                            </label>
                            <v-textarea
                                v-model="form.description"
                                placeholder="Masukkan deskripsi kategori"
                                variant="outlined"
                                density="comfortable"
                                rows="3"
                                hide-details="auto"
                                class="mb-4"
                            ></v-textarea>

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
                    Simpan
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from "vue";
import type { Category, CategoryPayload } from "@/admin/types/category";
import categoryService from "@/admin/services/category.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const snackbar = useSnackbarStore();

const emit = defineEmits(["saved"]);

const dialog = ref(false);
const loading = ref(false);
const formRef = ref<any>(null);
const isFormValid = ref(false);

const form = reactive<CategoryPayload>({
    name: "",
    description: "",
    is_active: true,
});

const editId = ref<number | null>(null);
const isEdit = computed(() => editId.value !== null);

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
};

const resetForm = () => {
    if (formRef.value) formRef.value.resetValidation();
    form.name = "";
    form.description = "";
    form.is_active = true;
    editId.value = null;
};

const open = (category?: Category) => {
    resetForm();
    if (category) {
        editId.value = category.id;
        form.name = category.name;
        form.description = category.description || "";
        form.is_active = category.is_active;
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
            await categoryService.updateCategory(editId.value, form);
        } else {
            await categoryService.createCategory(form);
        }
        emit("saved");
        close();
        snackbar.showMessage("Berhasil menyimpan kategori", "success");
    } catch (error) {
        console.error("Gagal menyimpan kategori", error);
        snackbar.showMessage("Gagal menyimpan kategori", "error");
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, close });
</script>
