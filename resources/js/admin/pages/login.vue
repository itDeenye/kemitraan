<template>
    <v-layout class="min-vh-100 bg-white">
        <v-row no-gutters class="min-vh-100">
            <v-col
                cols="12"
                md="6"
                lg="7"
                class="d-none d-md-flex position-relative left-panel"
            >
                <div class="overlay"></div>

                <div
                    class="z-10 d-flex flex-column justify-center pa-10 pa-lg-16 w-100 h-100 text-grey-darken-4"
                >
                    <h1
                        class="text-h2 font-weight-bold mb-4"
                        style="line-height: 1.2; letter-spacing: -1px"
                    >
                        Sistem <br />Manajemen Internal
                    </h1>
                    <p
                        class="text-h6 font-weight-medium text-grey-darken-3"
                        style="max-width: 500px; line-height: 1.5"
                    >
                        Akses khusus administrator internal DNY Skincare.
                    </p>
                </div>
            </v-col>

            <v-col
                cols="12"
                md="6"
                lg="5"
                class="d-flex align-center justify-center pa-6 pa-sm-10 pa-md-12 bg-white"
            >
                <div
                    class="w-100 d-flex flex-column h-100 justify-center"
                    style="max-width: 480px"
                >
                    <div class="my-auto">
                        <div class="d-flex align-center mb-8">
                            <v-img
                                src="/logo-fix.png"
                                alt="DNY Skincare"
                                width="120"
                                height="100"
                                style="object-fit: contain"
                            />
                        </div>

                        <div class="mb-10">
                            <h3
                                class="text-h5 font-weight-bold text-grey-darken-4 mb-2"
                            >
                                Selamat Datang Admin DNY
                            </h3>
                            <p
                                class="text-body-2 text-medium-emphasis"
                                style="line-height: 1.6"
                            >
                                Silakan login untuk mengakses akun Anda.
                            </p>
                        </div>

                        <v-alert
                            v-if="errorMessage"
                            type="error"
                            variant="tonal"
                            class="mb-6 rounded-lg text-body-2"
                            density="compact"
                        >
                            {{ errorMessage }}
                        </v-alert>

                        <v-form @submit.prevent="handleLogin">
                            <div
                                class="text-caption font-weight-bold text-grey-darken-1 mb-2 text-uppercase"
                                style="letter-spacing: 0.5px"
                            >
                                Username
                            </div>
                            <v-text-field
                                v-model="form.username"
                                placeholder="Masukkan Username Anda"
                                variant="outlined"
                                density="comfortable"
                                color="#8e0a1f"
                                base-color="grey-lighten-1"
                                rounded="lg"
                                class="mb-6 custom-input"
                                hide-details="auto"
                            >
                                <template #prepend-inner>
                                    <v-icon
                                        icon="mdi-account-outline"
                                        color="grey-lighten-1"
                                        size="small"
                                        class="mr-2"
                                    ></v-icon>
                                </template>
                            </v-text-field>

                            <div
                                class="text-caption font-weight-bold text-grey-darken-1 mb-2 text-uppercase"
                                style="letter-spacing: 0.5px"
                            >
                                Password
                            </div>
                            <v-text-field
                                v-model="form.password"
                                placeholder="••••••••"
                                :type="showPassword ? 'text' : 'password'"
                                variant="outlined"
                                density="comfortable"
                                color="#8e0a1f"
                                base-color="grey-lighten-1"
                                rounded="lg"
                                class="mb-4 custom-input"
                                hide-details="auto"
                            >
                                <template #prepend-inner>
                                    <v-icon
                                        icon="mdi-lock-outline"
                                        color="grey-lighten-1"
                                        size="small"
                                        class="mr-2"
                                    ></v-icon>
                                </template>
                                <template #append-inner>
                                    <v-icon
                                        :icon="
                                            showPassword
                                                ? 'mdi-eye-off-outline'
                                                : 'mdi-eye-outline'
                                        "
                                        color="grey-darken-1"
                                        size="small"
                                        class="cursor-pointer"
                                        @click="showPassword = !showPassword"
                                    ></v-icon>
                                </template>
                            </v-text-field>

                            <div class="d-flex align-center justify-end mb-10">
                                <!-- <v-checkbox
                                    v-model="rememberMe"
                                    label="Ingat saya"
                                    density="compact"
                                    hide-details
                                    color="#8e0a1f"
                                    class="text-body-2 text-grey-darken-2"
                                /> -->
                                <router-link
                                    to="/admin/forgot-password"
                                    class="text-caption font-weight-bold text-decoration-none"
                                    style="color: #6a040f"
                                    >Lupa password?</router-link
                                >
                            </div>

                            <v-btn
                                type="submit"
                                :loading="isLoading"
                                block
                                color="#4a0008"
                                size="x-large"
                                rounded="lg"
                                elevation="0"
                                class="font-weight-bold text-white text-body-1 mt-5"
                                style="
                                    text-transform: none;
                                    letter-spacing: 0.5px;
                                    height: 56px;
                                "
                            >
                                <v-icon
                                    icon="mdi-login"
                                    class="mr-2"
                                    size="small"
                                ></v-icon>
                                MASUK
                            </v-btn>
                        </v-form>
                    </div>

                    <div
                        class="mt-2 pt-6 border-t"
                        style="border-top-color: #f0f0f0 !important"
                    >
                        <p class="text-caption text-grey-darken-1 mb-1">
                            &copy; 2026 DNY Skincare. All rights reserved.
                        </p>
                    </div>
                </div>
            </v-col>
        </v-row>
    </v-layout>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/shared/stores/auth";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const router = useRouter();
const authStore = useAuthStore();
const snackbar = useSnackbarStore();

const isLoading = ref(false);
const errorMessage = ref("");
const showPassword = ref(false);
const rememberMe = ref(false);

const form = reactive({
    username: "",
    password: "",
    device_name: "browser",
});

async function handleLogin() {
    if (!form.username || !form.password) {
        errorMessage.value = "Username dan password harus diisi.";
        return;
    }

    isLoading.value = true;
    errorMessage.value = "";

    const result = await authStore.login(form);

    if (result.success) {
        snackbar.showMessage("Login berhasil. Selamat datang!");
        const intendedUrl = sessionStorage.getItem("admin_intended_url");
        sessionStorage.removeItem("admin_intended_url");
        router.push(intendedUrl || "/admin");
    } else {
        errorMessage.value =
            result.message || "Login gagal. Periksa kembali kredensial Anda.";
        isLoading.value = false;
    }
}

onMounted(() => {
    if (sessionStorage.getItem("auth_expired")) {
        snackbar.showError("Sesi Anda telah berakhir. Silakan login kembali.");
        sessionStorage.removeItem("auth_expired");
    }
});
</script>

<style scoped>
.min-vh-100 {
    min-height: 100vh;
}

.left-panel {
    background-image: url("/admin-login.png");
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}

.overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.75);
    z-index: 1;
}

.z-10 {
    z-index: 10;
    position: relative;
}

:deep(.custom-input .v-field) {
    background-color: #ffffff !important;
}

:deep(.custom-input .v-field--focused) {
    border-color: #8e0a1f !important;
}

:deep(.v-checkbox .v-label) {
    font-size: 0.875rem !important;
    color: #616161 !important;
    margin-left: 4px;
}
</style>
