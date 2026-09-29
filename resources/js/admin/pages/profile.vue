<template>
    <div>
        <div class="mb-6">
            <h1 class="text-h4 font-weight-bold mb-1">Profil Administrator</h1>
            <p class="text-body-2 text-medium-emphasis">
                Kelola informasi akun administrator Anda dengan mudah di sini.
            </p>
        </div>

        <v-row>
            <!-- Kolom Kiri: Ringkasan Profil -->
            <v-col cols="12" md="4">
                <v-card
                    variant="flat"
                    class="pa-6 text-center rounded-xl bg-surface elevation-1 h-100 d-flex flex-column align-center"
                    :loading="loadingData"
                >
                    <!-- MediaUpload component for Avatar -->
                    <MediaUpload
                        v-model="form.image_url"
                        collection="profile"
                        :size="120"
                        color="primary"
                        :fallbackText="authStore.user?.name"
                        class="mb-5"
                        @uploading="isUploadingAvatar = $event"
                        @success="handleAvatarSuccess"
                    />

                    <h2 class="text-h5 font-weight-bold mb-1">
                        {{ authStore.user?.name || "Administrator" }}
                    </h2>
                    <p class="text-body-1 text-medium-emphasis mb-4">
                        {{ authStore.user?.email || "admin@dny.id" }}
                    </p>

                    <v-chip
                        color="primary"
                        variant="tonal"
                        prepend-icon="mdi-shield-check"
                        class="mb-6 font-weight-medium"
                    >
                        {{ authStore.user?.role?.name || "Administrator" }}
                    </v-chip>

                    <v-divider class="mb-4 border-opacity-50 w-100" />

                    <div
                        class="d-flex align-center justify-center ga-2 text-medium-emphasis text-caption"
                    >
                        <v-icon size="16">mdi-clock-outline</v-icon>
                        <span>Terakhir Login:</span>
                        <span class="font-weight-medium">
                            {{
                                authStore.user?.last_login_at
                                    ? formatDateTime(
                                          authStore.user.last_login_at,
                                      )
                                    : "-"
                            }}
                        </span>
                    </div>
                </v-card>
            </v-col>

            <!-- Kolom Kanan: Form Edit & Password -->
            <v-col cols="12" md="8">
                <!-- Informasi Akun -->
                <v-card
                    variant="flat"
                    class="pa-6 mb-6 rounded-xl bg-surface elevation-1"
                >
                    <div class="d-flex align-center ga-3 mb-6">
                        <v-avatar color="primary-lighten-1" size="40" rounded>
                            <v-icon color="primary"
                                >mdi-account-edit-outline</v-icon
                            >
                        </v-avatar>
                        <h2 class="text-h6 font-weight-bold">Informasi Akun</h2>
                    </div>

                    <v-form ref="profileFormRef" @submit.prevent="saveProfile">
                        <v-row>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="form.name"
                                    :rules="[rules.required('Nama Lengkap')]"
                                    label="Nama Lengkap"
                                    prepend-inner-icon="mdi-account"
                                    variant="outlined"
                                    color="primary"
                                    density="comfortable"
                                />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="form.email"
                                    :rules="[
                                        rules.required('Alamat Email'),
                                        rules.email,
                                    ]"
                                    label="Alamat Email"
                                    type="email"
                                    prepend-inner-icon="mdi-email"
                                    variant="outlined"
                                    color="primary"
                                    density="comfortable"
                                />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="form.username"
                                    :rules="[rules.required('Username')]"
                                    label="Username"
                                    prepend-inner-icon="mdi-account-circle"
                                    variant="outlined"
                                    color="primary"
                                    density="comfortable"
                                    readonly
                                    class="opacity-70"
                                    persistent-hint
                                />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    :model-value="authStore.user?.role?.name"
                                    label="Role (Hak Akses)"
                                    prepend-inner-icon="mdi-shield-lock-outline"
                                    variant="outlined"
                                    density="comfortable"
                                    readonly
                                    class="opacity-70"
                                    hint="Role hanya dapat diubah oleh Super Admin"
                                    persistent-hint
                                />
                            </v-col>
                            <v-col cols="12" class="d-flex justify-end mt-2">
                                <v-btn
                                    type="submit"
                                    color="primary"
                                    prepend-icon="mdi-content-save-outline"
                                    class="text-none px-6"
                                    elevation="2"
                                    :loading="
                                        isSavingProfile || isUploadingAvatar
                                    "
                                >
                                    Simpan Perubahan
                                </v-btn>
                            </v-col>
                        </v-row>
                    </v-form>
                </v-card>

                <!-- Ubah Password -->
                <v-card
                    variant="flat"
                    class="pa-6 rounded-xl bg-surface elevation-1"
                >
                    <div class="d-flex align-center ga-3 mb-6">
                        <v-avatar color="warning-lighten-1" size="40" rounded>
                            <v-icon color="warning">mdi-lock-reset</v-icon>
                        </v-avatar>
                        <h2 class="text-h6 font-weight-bold">Ubah Password</h2>
                    </div>

                    <v-form
                        ref="passwordFormRef"
                        @submit.prevent="updatePassword"
                    >
                        <v-row>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="pwdForm.current_password"
                                    :rules="[rules.required('Password Lama')]"
                                    label="Password Lama"
                                    :type="showCurrentPassword ? 'text' : 'password'"
                                    prepend-inner-icon="mdi-lock-outline"
                                    :append-inner-icon="showCurrentPassword ? 'mdi-eye-off' : 'mdi-eye'"
                                    @click:append-inner="showCurrentPassword = !showCurrentPassword"
                                    variant="outlined"
                                    color="warning"
                                    density="comfortable"
                                    placeholder="Masukkan password saat ini"
                                />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="pwdForm.password"
                                    :rules="[
                                        rules.required('Password Baru'),
                                        rules.minLength(8),
                                    ]"
                                    label="Password Baru"
                                    :type="showNewPassword ? 'text' : 'password'"
                                    prepend-inner-icon="mdi-key-variant"
                                    :append-inner-icon="showNewPassword ? 'mdi-eye-off' : 'mdi-eye'"
                                    @click:append-inner="showNewPassword = !showNewPassword"
                                    variant="outlined"
                                    color="warning"
                                    density="comfortable"
                                    placeholder="Masukkan password baru"
                                />
                            </v-col>
                            <v-col cols="12" sm="6">
                                <v-text-field
                                    v-model="pwdForm.password_confirmation"
                                    :rules="[
                                        rules.required(
                                            'Konfirmasi Password Baru',
                                        ),
                                        rules.match,
                                    ]"
                                    label="Konfirmasi Password Baru"
                                    :type="showConfirmPassword ? 'text' : 'password'"
                                    prepend-inner-icon="mdi-key-variant"
                                    :append-inner-icon="showConfirmPassword ? 'mdi-eye-off' : 'mdi-eye'"
                                    @click:append-inner="showConfirmPassword = !showConfirmPassword"
                                    variant="outlined"
                                    color="warning"
                                    density="comfortable"
                                    placeholder="Ketik ulang password baru"
                                />
                            </v-col>
                            <v-col cols="12" class="d-flex justify-end mt-2">
                                <v-btn
                                    type="submit"
                                    color="warning"
                                    prepend-icon="mdi-lock-check-outline"
                                    class="text-none px-6"
                                    elevation="2"
                                    :loading="isUpdatingPassword"
                                >
                                    Update Password
                                </v-btn>
                            </v-col>
                        </v-row>
                    </v-form>
                </v-card>
            </v-col>
        </v-row>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from "vue";
import { useAuthStore } from "@/shared/stores/auth";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import MediaUpload from "@/shared/components/MediaUpload.vue";
import profileService from "@/admin/services/profile.service";
import type {
    ProfilePayload,
    PasswordPayload,
} from "@/admin/services/profile.service";

const { formatDateTime } = useFormatter();
const authStore = useAuthStore();
const snackbar = useSnackbarStore();

const loadingData = ref(false);
const isSavingProfile = ref(false);
const isUpdatingPassword = ref(false);
const isUploadingAvatar = ref(false);

const profileFormRef = ref<any>(null);
const passwordFormRef = ref<any>(null);

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const form = reactive<ProfilePayload>({
    name: "",
    email: "",
    username: "",
    image_url: "",
});

const pwdForm = reactive<PasswordPayload>({
    current_password: "",
    password: "",
    password_confirmation: "",
});

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
    email: (v: any) => /.+@.+\..+/.test(v) || "Email harus valid",
    minLength: (min: number) => (v: any) =>
        (v && String(v).length >= min) || `Minimal ${min} karakter`,
    match: (v: any) => v === pwdForm.password || "Password tidak cocok",
};

const fetchProfile = async () => {
    loadingData.value = true;
    try {
        const data = await profileService.getProfile();
        // Sync authStore user
        authStore.updateUser(data);

        form.name = data.name || "";
        form.email = data.email || "";
        form.username = data.username || "";
        form.image_url = data.image || "";
    } catch (e: any) {
        snackbar.showMessage(
            e.response?.data?.message || "Gagal memuat profil",
            "error",
        );
    } finally {
        loadingData.value = false;
    }
};

onMounted(() => {
    fetchProfile();
});

const handleAvatarSuccess = async (url: string) => {
    // Auto-save profile when avatar is uploaded
    form.image_url = url;
    await saveProfile();
};

const saveProfile = async () => {
    if (!profileFormRef.value) return;
    const { valid } = await profileFormRef.value.validate();
    if (!valid) return;

    isSavingProfile.value = true;
    try {
        await profileService.updateProfile(form);
        snackbar.showMessage("Profil berhasil diperbarui", "success");
        await fetchProfile(); // re-fetch to sync
    } catch (e: any) {
        snackbar.showMessage(
            e.response?.data?.message || "Gagal menyimpan profil",
            "error",
        );
    } finally {
        isSavingProfile.value = false;
    }
};

const updatePassword = async () => {
    if (!passwordFormRef.value) return;
    const { valid } = await passwordFormRef.value.validate();
    if (!valid) return;

    isUpdatingPassword.value = true;
    try {
        await profileService.updatePassword(pwdForm);
        snackbar.showMessage("Password berhasil diubah", "success");
        // Reset form
        passwordFormRef.value.reset();
        pwdForm.current_password = "";
        pwdForm.password = "";
        pwdForm.password_confirmation = "";
    } catch (e: any) {
        snackbar.showMessage(
            e.response?.data?.message || "Gagal mengubah password",
            "error",
        );
    } finally {
        isUpdatingPassword.value = false;
    }
};
</script>
