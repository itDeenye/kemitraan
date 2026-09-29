<template>
    <div class="mobile-app-wrapper login-page">
        <div class="mobile-app-container login-body">
            <div class="login-hero">
                <img
                    src="/logo-fix.png"
                    class="login-logo"
                    alt="DNY SkinCare"
                />
                <p style="color: #7c0c18">
                    "Dari Ibu Istimewa Untuk Ibu Luar Biasa Seluruh Indonesia"
                </p>
            </div>
            <div class="login-card">
                <h3>Reset Password</h3>
                <p class="login-subtitle">
                    Silakan masukkan password baru Anda.
                </p>

                <div v-if="errorMessage" class="login-error">
                    <v-icon icon="mdi-alert-circle-outline" size="14" />
                    {{ errorMessage }}
                </div>

                <div v-if="!token" class="login-error">
                    <v-icon icon="mdi-alert-circle-outline" size="14" />
                    Token reset password tidak valid atau tidak ditemukan.
                </div>

                <form v-else @submit.prevent="handleReset">
                    <div class="field">
                        <v-icon icon="mdi-lock-outline" size="16" />
                        <input
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            placeholder="Password Baru"
                            autocomplete="new-password"
                        />
                        <v-icon
                            :icon="
                                showPassword
                                    ? 'mdi-eye-off-outline'
                                    : 'mdi-eye-outline'
                            "
                            size="16"
                            class="toggle-password"
                            @click="showPassword = !showPassword"
                        />
                    </div>
                    <div class="field">
                        <v-icon icon="mdi-lock-check-outline" size="16" />
                        <input
                            v-model="form.password_confirmation"
                            :type="showConfirmPassword ? 'text' : 'password'"
                            placeholder="Konfirmasi Password Baru"
                            autocomplete="new-password"
                        />
                        <v-icon
                            :icon="
                                showConfirmPassword
                                    ? 'mdi-eye-off-outline'
                                    : 'mdi-eye-outline'
                            "
                            size="16"
                            class="toggle-password"
                            @click="showConfirmPassword = !showConfirmPassword"
                        />
                    </div>
                    <button
                        type="submit"
                        class="primary-button block mt-4"
                        :disabled="isLoading"
                    >
                        <template v-if="isLoading">
                            <v-progress-circular
                                size="16"
                                width="2"
                                indeterminate
                                color="white"
                            />
                            Menyimpan...
                        </template>
                        <template v-else> Simpan Password Baru </template>
                    </button>

                    <div class="text-center mt-4" style="margin-top: 20px">
                        <router-link
                            to="/member/login"
                            style="
                                text-decoration: none;
                                color: var(--muted);
                                font-size: 13px;
                            "
                        >
                            Kembali ke Login
                        </router-link>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useViewportHeight } from "@/member/composables/useViewportHeight";

const route = useRoute();
const router = useRouter();
const snackbar = useSnackbarStore();

// Activate dynamic viewport height tracking (dvh with JS fallback)
useViewportHeight();

const isLoading = ref(false);
const errorMessage = ref("");
const showPassword = ref(false);
const showConfirmPassword = ref(false);

const token = ref((route.query.token as string) || "");

const form = reactive({
    password: "",
    password_confirmation: "",
});

onMounted(() => {
    if (!token.value) {
        errorMessage.value =
            "Link reset password tidak valid. Pastikan Anda mengklik link lengkap dari email.";
    }
});

async function handleReset() {
    if (!form.password || !form.password_confirmation) {
        errorMessage.value = "Password baru dan konfirmasi harus diisi.";
        return;
    }

    if (form.password !== form.password_confirmation) {
        errorMessage.value = "Password baru dan konfirmasi tidak cocok.";
        return;
    }

    if (form.password.length < 8) {
        errorMessage.value = "Password minimal 8 karakter.";
        return;
    }

    isLoading.value = true;
    errorMessage.value = "";

    try {
        const response = await axios.post(
            "/api/v1/member/auth/reset-password",
            {
                token: token.value,
                password: form.password,
                password_confirmation: form.password_confirmation,
            },
        );

        if (response.data?.success) {
            snackbar.showMessage(
                response.data.message ||
                    "Password berhasil diubah. Silakan login dengan password baru.",
            );
            router.push("/member/login");
        } else {
            errorMessage.value =
                response.data?.message || "Gagal mengubah password.";
        }
    } catch (error: any) {
        errorMessage.value =
            error.response?.data?.message || "Terjadi kesalahan jaringan.";
    } finally {
        isLoading.value = false;
    }
}
</script>
