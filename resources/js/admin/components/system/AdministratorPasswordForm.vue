<template>
    <v-dialog v-model="dialog" max-width="500" persistent scrollable>
        <v-card class="rounded-xl elevation-10">
            <!-- Header -->
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-avatar color="warning-lighten-1" size="40" rounded>
                        <v-icon color="warning">mdi-lock-reset</v-icon>
                    </v-avatar>
                    <span class="text-h6 font-weight-bold">
                        Ubah Password
                    </span>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    density="comfortable"
                    @click="close"
                    :disabled="loading"
                ></v-btn>
            </v-card-title>

            <!-- Body -->
            <v-card-text class="pa-6">
                <v-form ref="formRef" @submit.prevent="submit">
                    <v-row dense>
                        <v-col cols="12">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Password Baru <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.password"
                                type="password"
                                placeholder="Masukkan password baru"
                                variant="outlined"
                                density="comfortable"
                                color="warning"
                                :rules="[
                                    rules.required('Password baru'),
                                    rules.minLength(8),
                                ]"
                                class="mb-4"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Konfirmasi Password
                                <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.password_confirmation"
                                type="password"
                                placeholder="Ketik ulang password baru"
                                variant="outlined"
                                density="comfortable"
                                color="warning"
                                :rules="[
                                    rules.required('Konfirmasi password'),
                                    passwordMatch,
                                ]"
                                class="mb-4"
                            ></v-text-field>
                        </v-col>
                    </v-row>
                </v-form>
            </v-card-text>

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
                    color="warning"
                    variant="flat"
                    class="text-none px-6 ml-3"
                    @click="submit"
                    :loading="loading"
                >
                    Simpan Password
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import type {
    Administrator,
    AdministratorPasswordPayload,
} from "@/admin/types/administrator";
import administratorService from "@/admin/services/administrator.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const emit = defineEmits(["saved"]);
const snackbar = useSnackbarStore();

const dialog = ref(false);
const loading = ref(false);
const formRef = ref<any>(null);
const adminId = ref<number | null>(null);

const form = reactive<AdministratorPasswordPayload>({
    password: "",
    password_confirmation: "",
});

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
    minLength: (min: number) => (v: any) =>
        (v && v.length >= min) || `Minimal ${min} karakter`,
};

const passwordMatch = (v: any) => v === form.password || "Password tidak cocok";

const resetForm = () => {
    if (formRef.value) formRef.value.reset();
    form.password = "";
    form.password_confirmation = "";
    adminId.value = null;
};

const open = (item: Administrator) => {
    resetForm();
    adminId.value = item.id;
    dialog.value = true;
};

const close = () => {
    dialog.value = false;
    resetForm();
};

const submit = async () => {
    if (!formRef.value) return;
    const { valid } = await formRef.value.validate();
    if (!valid || !adminId.value) return;

    loading.value = true;
    try {
        await administratorService.updatePassword(adminId.value, { ...form });
        snackbar.showMessage("Password berhasil diubah", "success");
        emit("saved");
        close();
    } catch (e: any) {
        snackbar.showMessage(
            e.response?.data?.message || "Gagal mengubah password",
            "error",
        );
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, close });
</script>
