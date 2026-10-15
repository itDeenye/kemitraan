<template>
    <v-app>
        <main class="version-page">
            <div class="version-page__glow version-page__glow--top" />
            <div class="version-page__glow version-page__glow--bottom" />

            <v-container class="version-container px-4 py-8 py-sm-12">
                <header class="version-header mb-7 mb-sm-9">
                    <div class="version-header__icon">
                        <v-icon icon="mdi-source-branch" size="32" />
                    </div>
                    <div>
                        <h1 class="text-h4 text-sm-h3 font-weight-bold mb-2">
                            Versi Aplikasi
                        </h1>
                        <p class="text-body-2 text-medium-emphasis mb-0">
                            Informasi Rilis Aplikasi DNY Kemitraan.
                        </p>
                    </div>
                </header>

                <v-row class="mb-2" align="stretch">
                    <v-col cols="12" md="6">
                        <v-card class="version-card h-100 pa-5 pa-sm-6">
                            <div
                                class="d-flex align-start justify-space-between ga-3 mb-7"
                            >
                                <div
                                    class="version-card__icon version-card__icon--frontend"
                                >
                                    <v-icon
                                        icon="mdi-monitor-dashboard"
                                        size="28"
                                    />
                                </div>
                                <v-chip
                                    color="success"
                                    variant="tonal"
                                    size="small"
                                >
                                    Build Tersedia
                                </v-chip>
                            </div>

                            <p class="version-card__label mb-2">Frontend App</p>
                            <div class="d-flex align-center ga-2 flex-wrap">
                                <strong class="version-number">
                                    {{ frontendVersion }}
                                </strong>
                                <v-btn
                                    v-tooltip:top="'Salin versi frontend'"
                                    icon="mdi-content-copy"
                                    variant="text"
                                    density="comfortable"
                                    color="medium-emphasis"
                                    aria-label="Salin versi frontend"
                                    @click="copyVersion(frontendVersion)"
                                />
                            </div>
                            <p
                                class="text-body-2 text-medium-emphasis mt-3 mb-0"
                            >
                                Tanggal Rilis Versi:
                                <time
                                    v-if="frontendReleasedAt"
                                    :datetime="frontendReleasedAt"
                                    >{{
                                        formatReleaseDate(frontendReleasedAt)
                                    }}</time
                                >
                                <span v-else>Belum tercatat</span>
                            </p>
                        </v-card>
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-card class="version-card h-100 pa-5 pa-sm-6">
                            <div
                                class="d-flex align-start justify-space-between ga-3 mb-7"
                            >
                                <div
                                    class="version-card__icon version-card__icon--backend"
                                >
                                    <v-icon icon="mdi-server" size="28" />
                                </div>
                                <v-chip
                                    :color="backendStatusColor"
                                    variant="tonal"
                                    size="small"
                                >
                                    {{ backendStatusLabel }}
                                </v-chip>
                            </div>

                            <p class="version-card__label mb-2">Backend API</p>
                            <div
                                v-if="loadingBackendVersion"
                                class="d-flex align-center ga-3 version-loading"
                            >
                                <v-progress-circular
                                    indeterminate
                                    color="primary"
                                    size="28"
                                    width="3"
                                />
                                <span class="text-body-2 text-medium-emphasis">
                                    Mengambil versi API...
                                </span>
                            </div>
                            <template v-else-if="backendVersion">
                                <div class="d-flex align-center ga-2 flex-wrap">
                                    <strong class="version-number">
                                        {{ backendVersion }}
                                    </strong>
                                    <v-btn
                                        v-tooltip:top="'Salin versi backend'"
                                        icon="mdi-content-copy"
                                        variant="text"
                                        density="comfortable"
                                        color="medium-emphasis"
                                        aria-label="Salin versi backend"
                                        @click="copyVersion(backendVersion)"
                                    />
                                </div>
                                <p
                                    class="text-body-2 text-medium-emphasis mt-3 mb-0"
                                >
                                    Tanggal Rilis Versi:
                                    <time
                                        v-if="backendReleasedAt"
                                        :datetime="backendReleasedAt"
                                        >{{
                                            formatReleaseDate(backendReleasedAt)
                                        }}</time
                                    >
                                    <span v-else>Belum tercatat</span>
                                </p>
                                <p
                                    v-if="versionError"
                                    class="text-body-2 text-warning mt-3 mb-0"
                                >
                                    {{ versionError }}
                                </p>
                            </template>
                            <div v-else class="version-error">
                                <p class="text-body-2 text-error mb-3">
                                    {{ versionError }}
                                </p>
                                <v-btn
                                    color="primary"
                                    variant="outlined"
                                    prepend-icon="mdi-refresh"
                                    class="text-none"
                                    @click="loadVersions"
                                >
                                    Coba Lagi
                                </v-btn>
                            </div>
                        </v-card>
                    </v-col>
                </v-row>

                <p
                    class="version-footer text-caption text-medium-emphasis mt-6 mb-0"
                >
                    Halaman publik untuk kebutuhan pemeriksaan versi aplikasi.
                </p>
            </v-container>

            <v-snackbar v-model="copySnackbar" timeout="1800" color="success">
                Versi berhasil disalin.
            </v-snackbar>
        </main>
    </v-app>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";

interface VersionResponse {
    success: boolean;
    message: string;
    data?: {
        backend?: string;
        frontend?: string;
        released_at?: {
            backend?: string | null;
            frontend?: string | null;
        };
        released_at_datetime?: {
            backend?: string | null;
            frontend?: string | null;
        };
    };
}

const defaultVersion = "v1.0.0";
const frontendVersion = ref(defaultVersion);
const backendVersion = ref(defaultVersion);
const frontendReleasedAt = ref<string | null>(null);
const backendReleasedAt = ref<string | null>(null);
const versionError = ref("");
const loadingBackendVersion = ref(true);
const copySnackbar = ref(false);

function formatReleaseDate(date: string): string {
    const hasTime = date.includes("T");
    const parsedDate = new Date(hasTime ? date : `${date}T00:00:00+07:00`);
    if (Number.isNaN(parsedDate.getTime())) return "Belum tercatat";
    const formattedDate = new Intl.DateTimeFormat("id-ID", {
        day: "numeric",
        month: "long",
        year: "numeric",
        timeZone: "Asia/Jakarta",
    }).format(parsedDate);
    if (!hasTime) return formattedDate;
    const formattedTime = new Intl.DateTimeFormat("en-GB", {
        hour: "2-digit",
        minute: "2-digit",
        hourCycle: "h23",
        timeZone: "Asia/Jakarta",
    }).format(parsedDate);
    return `${formattedDate}, ${formattedTime} WIB`;
}

const backendStatusColor = computed(() => {
    if (loadingBackendVersion.value) {
        return "info";
    }

    if (versionError.value) {
        return "warning";
    }

    return backendVersion.value ? "success" : "error";
});

const backendStatusLabel = computed(() => {
    if (loadingBackendVersion.value) {
        return "Memuat";
    }

    if (versionError.value) {
        return "Versi Default";
    }

    return backendVersion.value ? "API Terhubung" : "Tidak Tersedia";
});

async function loadVersions(): Promise<void> {
    loadingBackendVersion.value = true;
    versionError.value = "";

    try {
        const response = await fetch("/api/v1/version", {
            headers: {
                Accept: "application/json",
            },
            cache: "no-store",
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = (await response.json()) as VersionResponse;
        const backend = result.data?.backend?.trim() || defaultVersion;
        const frontend = result.data?.frontend?.trim() || defaultVersion;

        if (!result.success) {
            throw new Error(result.message || "Versi API tidak tersedia");
        }

        backendVersion.value = backend;
        frontendVersion.value = frontend;
        backendReleasedAt.value =
            result.data?.released_at_datetime?.backend ||
            result.data?.released_at?.backend ||
            null;
        frontendReleasedAt.value =
            result.data?.released_at_datetime?.frontend ||
            result.data?.released_at?.frontend ||
            null;
    } catch (error) {
        console.error("Gagal mengambil versi API:", error);
        backendVersion.value = defaultVersion;
        frontendVersion.value = defaultVersion;
        backendReleasedAt.value = null;
        frontendReleasedAt.value = null;
        versionError.value = "Versi API gagal dimuat; menampilkan versi awal.";
    } finally {
        loadingBackendVersion.value = false;
    }
}

async function copyVersion(version: string): Promise<void> {
    if (!version || version === "-") {
        return;
    }

    try {
        await navigator.clipboard.writeText(version);
        copySnackbar.value = true;
    } catch (error) {
        console.error("Gagal menyalin versi:", error);
    }
}

onMounted(() => {
    document.title = "Versi Aplikasi - DNY SkinCare";
    void loadVersions();
});
</script>

<style scoped>
.version-page {
    position: relative;
    min-height: 100vh;
    overflow: hidden;
    background:
        radial-gradient(
            circle at top left,
            rgba(160, 21, 38, 0.1),
            transparent 34%
        ),
        linear-gradient(145deg, #fbf7f7 0%, #ffffff 50%, #f7eeee 100%);
}

.version-container {
    position: relative;
    z-index: 1;
    max-width: 960px;
}

.version-page__glow {
    position: absolute;
    border-radius: 999px;
    filter: blur(2px);
    pointer-events: none;
}

.version-page__glow--top {
    top: -140px;
    right: -100px;
    width: 360px;
    height: 360px;
    background: rgba(160, 21, 38, 0.08);
}

.version-page__glow--bottom {
    bottom: -180px;
    left: -140px;
    width: 420px;
    height: 420px;
    background: rgba(212, 160, 91, 0.1);
}

.version-header {
    display: flex;
    align-items: center;
    gap: 18px;
}

.version-header__icon {
    display: flex;
    width: 64px;
    height: 64px;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    border-radius: 18px;
    color: white;
    background: linear-gradient(145deg, #a01526, #76101c);
    box-shadow: 0 12px 28px rgba(160, 21, 38, 0.22);
}

.version-card {
    border: 1px solid rgba(var(--v-border-color), 0.22);
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 16px 36px rgba(79, 38, 45, 0.08);
    backdrop-filter: blur(12px);
}

.version-card__icon {
    display: flex;
    width: 52px;
    height: 52px;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
}

.version-card__icon--frontend {
    color: #a01526;
    background: #f8e9ec;
}

.version-card__icon--backend {
    color: #8a5a17;
    background: #fbf0dc;
}

.version-card__label {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.version-number {
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
    font-size: clamp(30px, 5vw, 46px);
    line-height: 1;
    letter-spacing: -0.035em;
}

.version-loading {
    min-height: 46px;
}

.version-error {
    min-height: 78px;
}

.version-note {
    border: 1px solid rgba(160, 21, 38, 0.12);
    background: rgba(255, 255, 255, 0.72);
}

.version-footer {
    text-align: center;
}

code {
    padding: 2px 6px;
    border-radius: 5px;
    color: #8d1828;
    background: #f7e9eb;
    font-family: Consolas, "Courier New", monospace;
    font-size: 0.9em;
}

@media (max-width: 600px) {
    .version-header {
        align-items: flex-start;
    }

    .version-header__icon {
        width: 54px;
        height: 54px;
        border-radius: 15px;
    }

    .version-card {
        border-radius: 16px !important;
    }
}
</style>
