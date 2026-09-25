<template>
    <v-dialog v-model="dialog" max-width="550" persistent scrollable>
        <v-card class="rounded-xl elevation-10">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28"
                        >mdi-shield-account</v-icon
                    >
                    <span class="text-h6 font-weight-bold text-primary">
                        {{ isEdit ? "Edit Role" : "Tambah Role" }}
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
                                Nama Role <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.title"
                                :rules="[rules.required('Nama role')]"
                                placeholder="Masukkan nama role"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-4"
                            ></v-text-field>

                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Tipe Role <span class="text-error">*</span>
                            </label>
                            <v-select
                                v-model="form.type"
                                :items="typeOptions"
                                item-title="label"
                                item-value="value"
                                :rules="[rules.requiredSelect('Tipe role')]"
                                placeholder="Pilih tipe role"
                                variant="outlined"
                                density="comfortable"
                                hide-details="auto"
                                class="mb-4"
                            ></v-select>

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

            <!-- Actions -->
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
import type { Role, RolePayload } from "@/admin/types/role";
import roleService from "@/admin/services/role.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const emit = defineEmits(["saved"]);
const snackbar = useSnackbarStore();

const dialog = ref(false);
const loading = ref(false);
const formRef = ref<any>(null);
const isFormValid = ref(false);

const typeOptions = [
    { value: "administrator", label: "Administrator" },
    { value: "superuser", label: "Super User" },
];

const form = reactive<RolePayload>({
    title: "",
    type: "administrator",
    is_active: true,
});

const editId = ref<number | null>(null);
const isEdit = computed(() => editId.value !== null);

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
    requiredSelect: (field: string) => (v: any) =>
        !!v || `${field} wajib dipilih`,
};

const resetForm = () => {
    if (formRef.value) formRef.value.resetValidation();
    form.title = "";
    form.type = "administrator";
    form.is_active = true;
    editId.value = null;
};

const open = (role?: Role) => {
    resetForm();
    if (role) {
        editId.value = role.id;
        form.title = role.title;
        form.type = role.type;
        form.is_active = role.is_active;
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
            await roleService.updateRole(editId.value, form);
            snackbar.showMessage("Role berhasil diperbarui", "success");
        } else {
            await roleService.createRole(form);
            snackbar.showMessage("Role berhasil ditambahkan", "success");
        }
        emit("saved");
        close();
    } catch (error: any) {
        const message = error.response?.data?.message || "Gagal menyimpan role";
        snackbar.showMessage(message, "error");
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, close });
</script>
