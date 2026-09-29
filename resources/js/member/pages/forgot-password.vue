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
                <h3>Lupa Password</h3>
                <p class="login-subtitle">
                    Masukkan email atau kode mitra Anda untuk mendapatkan tautan
                    reset password.
                </p>

                <div v-if="errorMessage" class="login-error">
                    <v-icon icon="mdi-alert-circle-outline" size="14" />
                    {{ errorMessage }}
                </div>

                <form @submit.prevent="submit">
                    <div class="field" style="margin-bottom: 24px">
                        <v-icon icon="mdi-account-outline" size="16" />
                        <input
                            v-model="form.identifier"
                            type="text"
                            placeholder="Masukkan email atau kode mitra"
                            :disabled="isLoading"
                        />
                    </div>

                    <button
                        type="submit"
                        class="primary-button block mt-4"
                        :disabled="isLoading || !form.identifier"
                    >
                        <template v-if="isLoading">
                            <v-progress-circular
                                size="16"
                                width="2"
                                indeterminate
                                color="white"
                            />
                            Mengirim...
                        </template>
                        <template v-else> Kirim Tautan </template>
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
import { reactive, ref } from "vue";
import axios from "axios";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useViewportHeight } from "@/member/composables/useViewportHeight";

const snackbar = useSnackbarStore();
useViewportHeight();

const isLoading = ref(false);
const errorMessage = ref("");

const form = reactive({
    identifier: "",
});

const submit = async () => {
    if (!form.identifier) return;

    isLoading.value = true;
    errorMessage.value = "";

    try {
        const response = await axios.post(
            "/api/v1/member/auth/forgot-password",
            {
                identifier: form.identifier,
            },
        );

        if (response.data?.success) {
            snackbar.showMessage(
                response.data.message ||
                    "Instruksi reset password telah dikirim.",
            );
            // Optionally redirect to login or show success message.
            form.identifier = "";
        } else {
            errorMessage.value =
                response.data?.message || "Gagal memproses permintaan.";
        }
    } catch (error: any) {
        errorMessage.value =
            error.response?.data?.message || "Terjadi kesalahan jaringan.";
    } finally {
        isLoading.value = false;
    }
};
</script>
