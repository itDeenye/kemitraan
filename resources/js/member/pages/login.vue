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
                <h3>Selamat datang kembali</h3>
                <p class="login-subtitle">
                    Silakan masuk menggunakan akun mitra yang sudah terdaftar.
                </p>

                <div v-if="errorMessage" class="login-error">
                    <v-icon icon="mdi-alert-circle-outline" size="14" />
                    {{ errorMessage }}
                </div>

                <form @submit.prevent="handleLogin">
                    <div class="field">
                        <v-icon icon="mdi-account-outline" size="16" />
                        <input
                            v-model="form.username"
                            type="text"
                            placeholder="Masukkan Username Anda"
                            autocomplete="username"
                        />
                    </div>
                    <div class="field">
                        <v-icon icon="mdi-lock-outline" size="16" />
                        <input
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            placeholder="••••••••"
                            autocomplete="current-password"
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
                    <div class="login-links">
                        <router-link to="/member/forgot-password"
                            >Lupa password?</router-link
                        >
                    </div>
                    <button
                        type="submit"
                        class="primary-button block"
                        :disabled="isLoading"
                    >
                        <template v-if="isLoading">
                            <v-progress-circular
                                size="16"
                                width="2"
                                indeterminate
                                color="white"
                            />
                            Memuat...
                        </template>
                        <template v-else>
                            Masuk
                            <v-icon icon="mdi-arrow-right" size="14" />
                        </template>
                    </button>
                </form>
            </div>
        </div>

        <v-dialog
            v-model="showInstallDialog"
            max-width="calc(100vw - 32px)"
            width="380"
            persistent
        >
            <div
                class="mx-auto flex w-full flex-col items-center rounded-[28px] bg-white px-6 pb-6 pt-5 text-center shadow-2xl"
            >
                <div
                    class="mb-4 flex h-[76px] w-[76px] items-center justify-center overflow-hidden rounded-[20px] bg-[#fff4f5] p-2"
                >
                    <img
                        src="/pwa/icon-192.png"
                        class="h-full w-full object-contain"
                        alt="DNY Mitra"
                    />
                </div>
                <h2 class="mb-2 text-[20px] font-bold leading-tight text-[#261b1d]">
                    Instal Aplikasi DNY Mitra
                </h2>
                <p class="mb-6 max-w-[300px] text-[14px] leading-6 text-[#775e64]">
                    Pasang aplikasi di layar utama agar akses transaksi dan
                    kemitraan lebih cepat.
                </p>

                <div class="flex w-full gap-3">
                    <button
                        type="button"
                        class="min-h-12 flex-1 rounded-full bg-[#f3f4f6] px-4 py-3 text-[14px] font-bold text-[#4b5563] disabled:opacity-60"
                        :disabled="isInstalling"
                        @click="dismissInstallDialog"
                    >
                        Nanti
                    </button>
                    <button
                        type="button"
                        class="min-h-12 flex flex-1 items-center justify-center rounded-full bg-black px-4 py-3 text-[14px] font-bold text-white disabled:opacity-60"
                        :disabled="isInstalling"
                        @click="installApp"
                    >
                        <v-progress-circular
                            v-if="isInstalling"
                            class="mr-2"
                            indeterminate
                            size="18"
                            width="2"
                        />
                        Instal
                    </button>
                </div>
            </div>
        </v-dialog>
    </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/shared/stores/auth";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useViewportHeight } from "@/member/composables/useViewportHeight";

const router = useRouter();
const authStore = useAuthStore();
const snackbar = useSnackbarStore();

// Activate dynamic viewport height tracking (dvh with JS fallback)
useViewportHeight();

const isLoading = ref(false);
const errorMessage = ref("");
const showPassword = ref(false);
const showInstallDialog = ref(false);
const isInstalling = ref(false);
const deferredInstallPrompt = ref<BeforeInstallPromptEvent | null>(null);

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{
        outcome: "accepted" | "dismissed";
        platform: string;
    }>;
}

const installDismissedAtKey = "dny_pwa_install_dismissed_at";
const installDismissDuration = 7 * 24 * 60 * 60 * 1000;

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
        const intendedUrl = sessionStorage.getItem("member_intended_url");
        sessionStorage.removeItem("member_intended_url");
        router.push(intendedUrl || "/member");
    } else {
        errorMessage.value =
            result.message || "Login gagal. Periksa kembali kredensial Anda.";
        isLoading.value = false;
    }
}

function isInstalledApp(): boolean {
    return window.matchMedia("(display-mode: standalone)").matches;
}

function wasInstallRecentlyDismissed(): boolean {
    const dismissedAt = Number(localStorage.getItem(installDismissedAtKey));

    return Number.isFinite(dismissedAt)
        && dismissedAt > 0
        && Date.now() - dismissedAt < installDismissDuration;
}

function handleBeforeInstallPrompt(event: Event): void {
    event.preventDefault();

    if (isInstalledApp() || wasInstallRecentlyDismissed()) {
        return;
    }

    deferredInstallPrompt.value = event as BeforeInstallPromptEvent;
    showInstallDialog.value = true;
}

function dismissInstallDialog(): void {
    localStorage.setItem(installDismissedAtKey, String(Date.now()));
    showInstallDialog.value = false;
}

async function installApp(): Promise<void> {
    const installPrompt = deferredInstallPrompt.value;

    if (!installPrompt) {
        showInstallDialog.value = false;
        return;
    }

    isInstalling.value = true;

    try {
        await installPrompt.prompt();
        const choice = await installPrompt.userChoice;

        if (choice.outcome === "accepted") {
            localStorage.removeItem(installDismissedAtKey);
            snackbar.showMessage("Aplikasi DNY Mitra berhasil dipasang.");
        } else {
            localStorage.setItem(installDismissedAtKey, String(Date.now()));
        }
    } finally {
        deferredInstallPrompt.value = null;
        showInstallDialog.value = false;
        isInstalling.value = false;
    }
}

function handleAppInstalled(): void {
    deferredInstallPrompt.value = null;
    showInstallDialog.value = false;
    localStorage.removeItem(installDismissedAtKey);
}

onMounted(() => {
    window.addEventListener("beforeinstallprompt", handleBeforeInstallPrompt);
    window.addEventListener("appinstalled", handleAppInstalled);

    if (sessionStorage.getItem("auth_expired")) {
        snackbar.showError("Sesi Anda telah berakhir. Silakan login kembali.");
        sessionStorage.removeItem("auth_expired");
    }
});

onBeforeUnmount(() => {
    window.removeEventListener("beforeinstallprompt", handleBeforeInstallPrompt);
    window.removeEventListener("appinstalled", handleAppInstalled);
});
</script>
