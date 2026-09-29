<template>
    <v-dialog v-model="dialog" max-width="600" persistent scrollable>
        <v-card class="rounded-xl elevation-10">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-avatar
                        :color="isEdit ? 'info-lighten-1' : 'primary-lighten-1'"
                        size="40"
                        rounded
                    >
                        <v-icon
                            :color="isEdit ? 'info' : 'primary'"
                            :icon="
                                isEdit ? 'mdi-account-edit' : 'mdi-account-plus'
                            "
                        ></v-icon>
                    </v-avatar>
                    <span class="text-h6 font-weight-bold">
                        {{
                            isEdit
                                ? "Edit Administrator"
                                : "Tambah Administrator"
                        }}
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

            <v-card-text class="pa-6">
                <v-form ref="formRef" @submit.prevent="submit">
                    <div class="d-flex flex-column align-center mb-6">
                        <MediaUpload
                            v-model="form.image_url"
                            collection="admin"
                            :size="100"
                            color="primary"
                            fallbackIcon="mdi-camera"
                            @uploading="isUploadingAvatar = $event"
                        />
                        <span class="text-caption text-medium-emphasis mt-2"
                            >Foto Profil</span
                        >
                    </div>

                    <v-row dense>
                        <v-col cols="12">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Role / Hak Akses
                                <span class="text-error">*</span>
                            </label>
                            <v-autocomplete
                                v-model="form.role_id"
                                :items="roles"
                                item-title="title"
                                item-value="id"
                                placeholder="Pilih role"
                                variant="outlined"
                                density="comfortable"
                                color="primary"
                                :rules="[rules.requiredSelect('Role')]"
                                class="mb-4"
                                :loading="loadingRoles"
                            ></v-autocomplete>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Nama Lengkap <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.name"
                                placeholder="Masukkan nama"
                                variant="outlined"
                                density="comfortable"
                                color="primary"
                                :rules="[rules.required('Nama lengkap')]"
                                class="mb-4"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Alamat Email <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.email"
                                type="email"
                                placeholder="Masukkan email"
                                variant="outlined"
                                density="comfortable"
                                color="primary"
                                :rules="[rules.required('Email'), rules.email]"
                                class="mb-4"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Username <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.username"
                                placeholder="Masukkan username"
                                variant="outlined"
                                density="comfortable"
                                color="primary"
                                :rules="[rules.required('Username')]"
                                class="mb-4"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6" v-if="!isEdit">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Password <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.password"
                                type="password"
                                placeholder="Masukkan password"
                                variant="outlined"
                                density="comfortable"
                                color="primary"
                                :rules="[
                                    rules.required('Password'),
                                    rules.minLength(8),
                                ]"
                                class="mb-4"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6" v-if="!isEdit">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-medium-emphasis"
                            >
                                Konfirmasi Password
                                <span class="text-error">*</span>
                            </label>
                            <v-text-field
                                v-model="form.password_confirmation"
                                type="password"
                                placeholder="Ketik ulang password"
                                variant="outlined"
                                density="comfortable"
                                color="primary"
                                :rules="[
                                    rules.required('Konfirmasi password'),
                                    passwordMatch,
                                ]"
                                class="mb-4"
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <label
                                class="text-caption font-weight-medium mb-1 d-block text-transparent"
                                style="user-select: none"
                            >
                                Status
                            </label>
                            <v-switch
                                v-model="form.is_active"
                                color="success"
                                hide-details
                                inset
                                :class="{ 'inactive-switch': !form.is_active }"
                            >
                                <template v-slot:label>
                                    <span
                                        :class="
                                            form.is_active
                                                ? 'text-success font-weight-medium'
                                                : 'text-error font-weight-medium'
                                        "
                                    >
                                        Status:
                                        {{
                                            form.is_active
                                                ? "Aktif"
                                                : "Nonaktif"
                                        }}
                                    </span>
                                </template>
                            </v-switch>
                        </v-col>
                    </v-row>
                </v-form>
            </v-card-text>

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
                    :loading="loading || isUploadingAvatar"
                    :disabled="isUploadingAvatar"
                >
                    Simpan
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from "vue";
import type {
    Administrator,
    AdministratorPayload,
} from "@/admin/types/administrator";
import type { Role } from "@/admin/types/role";
import roleService from "@/admin/services/role.service";
import administratorService from "@/admin/services/administrator.service";
import MediaUpload from "@/shared/components/MediaUpload.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const emit = defineEmits(["saved"]);
const snackbar = useSnackbarStore();

const dialog = ref(false);
const loading = ref(false);
const loadingRoles = ref(false);
const isUploadingAvatar = ref(false);
const formRef = ref<any>(null);
const editId = ref<number | null>(null);

const roles = ref<Role[]>([]);

const form = reactive<AdministratorPayload>({
    role_id: null,
    username: "",
    name: "",
    email: "",
    image_url: "",
    is_active: true,
    password: "",
    password_confirmation: "",
});

const isEdit = computed(() => editId.value !== null);

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
    requiredSelect: (field: string) => (v: any) =>
        !!v || `${field} wajib dipilih`,
    email: (v: any) => /.+@.+\..+/.test(v) || "Email tidak valid",
    minLength: (min: number) => (v: any) =>
        (v && v.length >= min) || `Minimal ${min} karakter`,
};

const passwordMatch = (v: any) => v === form.password || "Password tidak cocok";

const fetchRoles = async () => {
    loadingRoles.value = true;
    try {
        roles.value = await roleService.getRoles();
    } catch (e) {
        snackbar.showMessage("Gagal memuat daftar role", "error");
    } finally {
        loadingRoles.value = false;
    }
};

onMounted(() => {
    fetchRoles();
});

const resetForm = () => {
    if (formRef.value) formRef.value.reset();
    form.role_id = null;
    form.username = "";
    form.name = "";
    form.email = "";
    form.image_url = "";
    form.is_active = true;
    form.password = "";
    form.password_confirmation = "";
    editId.value = null;
};

const open = (item?: Administrator) => {
    resetForm();
    if (item) {
        editId.value = item.id;
        form.role_id = item.role.id;
        form.username = item.username;
        form.name = item.name;
        form.email = item.email;
        form.image_url = item.image_url || "";
        form.is_active = item.is_active;
    }
    dialog.value = true;
};

const close = () => {
    dialog.value = false;
    resetForm();
};

const submit = async () => {
    if (!formRef.value) return;
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    loading.value = true;
    try {
        const payload = { ...form };
        if (isEdit.value) {
            delete payload.password;
            delete payload.password_confirmation;
            await administratorService.updateAdministrator(
                editId.value!,
                payload,
            );
            snackbar.showMessage(
                "Administrator berhasil diperbarui",
                "success",
            );
        } else {
            await administratorService.createAdministrator(payload);
            snackbar.showMessage(
                "Administrator berhasil ditambahkan",
                "success",
            );
        }
        emit("saved");
        close();
    } catch (e: any) {
        snackbar.showMessage(
            e.response?.data?.message || "Gagal menyimpan administrator",
            "error",
        );
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, close });
</script>

<style scoped>
.inactive-switch :deep(.v-label) {
    opacity: 1 !important;
}
.inactive-switch :deep(.v-switch__track) {
    background-color: rgb(var(--v-theme-error)) !important;
    opacity: 0.5 !important;
}
.inactive-switch :deep(.v-switch__thumb) {
    background-color: rgb(var(--v-theme-error)) !important;
    color: rgb(var(--v-theme-error)) !important;
}
</style>
