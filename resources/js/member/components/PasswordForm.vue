<template>
    <div class="card" style="padding: 16px">
        <v-form ref="formRef" @submit.prevent="submit" :disabled="loading">
            <v-text-field
                v-model="formData.current_password"
                label="Kata Sandi Lama *"
                :type="showOldPassword ? 'text' : 'password'"
                :append-inner-icon="showOldPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                @click:append-inner="showOldPassword = !showOldPassword"
                variant="outlined"
                density="comfortable"
                :rules="[(v) => !!v || 'Kata sandi lama wajib diisi']"
                class="mb-2"
            />
            <v-text-field
                v-model="formData.password"
                label="Kata Sandi Baru *"
                :type="showNewPassword ? 'text' : 'password'"
                :append-inner-icon="showNewPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                @click:append-inner="showNewPassword = !showNewPassword"
                variant="outlined"
                density="comfortable"
                :rules="[
                    (v) => !!v || 'Kata sandi baru wajib diisi',
                    (v) => v.length >= 6 || 'Minimal 6 karakter',
                ]"
                class="mb-2"
            />
            <v-text-field
                v-model="formData.password_confirmation"
                label="Konfirmasi Kata Sandi *"
                :type="showConfirmPassword ? 'text' : 'password'"
                :append-inner-icon="showConfirmPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                @click:append-inner="showConfirmPassword = !showConfirmPassword"
                variant="outlined"
                density="comfortable"
                :rules="[
                    (v) => !!v || 'Konfirmasi wajib diisi',
                    (v) => v === formData.password || 'Kata sandi tidak cocok',
                ]"
                class="mb-4"
            />

            <div class="d-flex ga-3">
                <button
                    type="submit"
                    class="primary-button block"
                    :disabled="loading"
                >
                    {{ loading ? "Menyimpan..." : "Ubah Kata Sandi" }}
                </button>
            </div>
        </v-form>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import profileService from "@/member/services/profile.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const router = useRouter();
const snackbar = useSnackbarStore();
const loading = ref(false);
const formRef = ref<any>(null);

const showOldPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const formData = reactive({
    current_password: "",
    password: "",
    password_confirmation: "",
});

async function submit() {
    if (!formRef.value) return;
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    loading.value = true;
    try {
        const response = await profileService.updatePassword(formData);
        if (response.success) {
            snackbar.showMessage("Kata sandi berhasil diperbarui", "success");
            await router.push("/member/profile");
        }
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message ||
                "Kata sandi belum dapat diperbarui.",
            "error",
        );
    } finally {
        loading.value = false;
    }
}
</script>
