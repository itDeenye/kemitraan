<template>
    <div class="dashboard-page">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="dashboard-header__content">
                <h1 class="dashboard-title text-primary">
                    {{ greeting }}, {{ authStore.user?.name }}!
                </h1>
                <p class="dashboard-subtitle text-medium-emphasis">
                    Pusat monitoring aktivitas operasional dan kemitraan
                    perusahaan.
                </p>
            </div>
        </header>

        <!-- Banner Utama -->
        <v-card class="dashboard-hero text-white elevation-0 border-0">
            <div class="dashboard-hero__content">
                <v-chip
                    color="white"
                    variant="flat"
                    size="x-small"
                    class="dashboard-hero__chip text-primary font-weight-bold"
                >
                    DNY SKINCARE
                </v-chip>
                <h2 class="dashboard-hero__title">Pusat Kendali Aplikasi</h2>
                <p class="dashboard-hero__description">
                    Kelola berbagai modul layanan dan pantau performa sistem
                    secara menyeluruh. Semua yang Anda butuhkan ada di sini.
                </p>
                <v-btn
                    color="white"
                    variant="flat"
                    size="small"
                    class="dashboard-hero__button text-primary font-weight-bold rounded-lg text-none"
                    to="/admin/system/administrators"
                    elevation="1"
                >
                    <v-icon start icon="mdi-cog" size="small"></v-icon>
                    Pengaturan Sistem
                </v-btn>
            </div>

            <!-- Ornamen Estetik -->
            <v-icon
                icon="mdi-monitor-dashboard"
                color="white"
                size="210"
                class="dashboard-hero__icon"
            ></v-icon>
            <div class="dashboard-hero__glow"></div>
        </v-card>

        <div class="dashboard-content-grid">
            <!-- Tindakan Cepat -->
            <section class="dashboard-section">
                <div class="section-heading">
                    <v-avatar
                        color="primary"
                        variant="tonal"
                        size="32"
                        rounded="lg"
                    >
                        <v-icon icon="mdi-flash" size="18"></v-icon>
                    </v-avatar>
                    <div class="section-heading__content">
                        <h2 class="section-title">Tindakan Cepat</h2>
                        <p class="section-subtitle text-medium-emphasis">
                            Jalan pintas ke menu operasional utama
                        </p>
                    </div>
                </div>

                <div class="quick-actions-grid">
                    <v-card
                        v-for="action in quickActions"
                        :key="action.title"
                        :to="action.to"
                        variant="flat"
                        class="action-card transition-all border"
                    >
                        <v-avatar
                            :color="action.color"
                            variant="tonal"
                            rounded="lg"
                            size="36"
                            class="action-card__icon"
                        >
                            <v-icon :icon="action.icon" size="19"></v-icon>
                        </v-avatar>
                        <h3 class="action-card__title">{{ action.title }}</h3>
                        <p class="action-card__subtitle text-medium-emphasis">
                            {{ action.subtitle }}
                        </p>
                    </v-card>
                </div>
            </section>

            <!-- Highlight Fitur -->
            <section class="dashboard-section">
                <div class="section-heading">
                    <v-avatar
                        color="info"
                        variant="tonal"
                        size="32"
                        rounded="lg"
                    >
                        <v-icon icon="mdi-server-network" size="18"></v-icon>
                    </v-avatar>
                    <div class="section-heading__content">
                        <h2 class="section-title">Infrastruktur Sistem</h2>
                        <p class="section-subtitle text-medium-emphasis">
                            Informasi keamanan dan fitur unggulan aplikasi
                        </p>
                    </div>
                </div>

                <div class="features-grid">
                    <v-card
                        v-for="feature in bottomFeatures"
                        :key="feature.title"
                        variant="flat"
                        class="feature-item transition-all border"
                    >
                        <v-avatar
                            :color="feature.color"
                            variant="tonal"
                            size="36"
                            rounded="lg"
                            class="feature-item__icon"
                        >
                            <v-icon :icon="feature.icon" size="19"></v-icon>
                        </v-avatar>
                        <div class="feature-item__content">
                            <h3 class="feature-item__title text-high-emphasis">
                                {{ feature.title }}
                            </h3>
                            <p
                                class="feature-item__subtitle text-medium-emphasis"
                            >
                                {{ feature.subtitle }}
                            </p>
                        </div>
                    </v-card>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useAuthStore } from "@/shared/stores/auth";

const authStore = useAuthStore();

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 11) return "Selamat Pagi";
    if (h < 15) return "Selamat Siang";
    if (h < 18) return "Selamat Sore";
    return "Selamat Malam";
});

const quickActions = [
    {
        title: "Manajemen Mitra",
        subtitle: "Kelola data kemitraan dan member",
        icon: "mdi-handshake-outline",
        color: "success",
        to: "/admin/partnership/members",
    },
    {
        title: "Katalog Produk",
        subtitle: "Atur produk dan varian skincare",
        icon: "mdi-package-variant-closed",
        color: "info",
        to: "/admin/product/products",
    },
    {
        title: "Laporan Transaksi",
        subtitle: "Pantau order dan pembayaran",
        icon: "mdi-chart-line",
        color: "primary",
        to: "/admin/transactions/sales-orders",
    },
    {
        title: "Stok Inventory",
        subtitle: "Cek ketersediaan barang",
        icon: "mdi-warehouse",
        color: "warning",
        to: "/admin/inventory/stock",
    },
];

const bottomFeatures = [
    {
        title: "Keamanan Terpusat",
        subtitle: "Jaga keamanan dan privasi data.",
        icon: "mdi-shield-check-outline",
        color: "success",
    },
    {
        title: "Analitik & Laporan",
        subtitle: "Pantau ringkasan performa aplikasi.",
        icon: "mdi-poll",
        color: "info",
    },
    {
        title: "Manajemen Layanan",
        subtitle: "Kelola seluruh layanan aplikasi.",
        icon: "mdi-lightning-bolt-outline",
        color: "primary",
    },
    {
        title: "Monitoring Operasional",
        subtitle: "Pantau aktivitas operasional sistem.",
        icon: "mdi-monitor-eye",
        color: "warning",
    },
];
</script>

<style scoped>
.dashboard-page {
    width: 100%;
    max-width: 1640px;
    margin: 0 auto;
    padding: 0 0 12px;
}

.dashboard-header {
    margin-bottom: 12px;
}

.dashboard-title {
    margin: 0;
    font-size: clamp(1.45rem, 1.55vw, 1.7rem);
    font-weight: 800;
    line-height: 1.25;
    letter-spacing: -0.025em;
}

.dashboard-subtitle {
    margin: 4px 0 0;
    font-size: 0.875rem;
    line-height: 1.4;
}

.dashboard-hero {
    position: relative;
    overflow: hidden;
    min-height: 160px;
    margin-bottom: 18px;
    padding: clamp(20px, 1.8vw, 24px);
    border-radius: 20px !important;
    background: linear-gradient(
        135deg,
        rgba(var(--v-theme-primary), 1) 0%,
        rgba(var(--v-theme-primary), 0.76) 100%
    );
}

.dashboard-hero__content {
    position: relative;
    z-index: 2;
    display: flex;
    max-width: 960px;
    flex-direction: column;
    align-items: flex-start;
}

.dashboard-hero__chip {
    margin-bottom: 8px;
    padding-inline: 10px !important;
}

.dashboard-hero__title {
    margin: 0;
    font-size: clamp(1.3rem, 1.55vw, 1.5rem);
    font-weight: 750;
    line-height: 1.3;
    letter-spacing: -0.015em;
}

.dashboard-hero__description {
    margin: 4px 0 12px;
    max-width: 900px;
    font-size: 0.875rem;
    line-height: 1.4;
    opacity: 0.92;
}

.dashboard-hero__button {
    min-height: 32px;
    padding-inline: 14px !important;
    letter-spacing: 0.035em;
}

.dashboard-hero__icon {
    position: absolute;
    right: 10px;
    bottom: -36px;
    z-index: 1;
    opacity: 0.1;
    transform: rotate(-10deg);
}

.dashboard-hero__glow {
    position: absolute;
    top: -35%;
    right: 13%;
    z-index: 1;
    width: 340px;
    height: 340px;
    border-radius: 50%;
    background: radial-gradient(
        circle,
        rgba(255, 255, 255, 0.16) 0%,
        rgba(255, 255, 255, 0) 70%
    );
}

.dashboard-content-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
}

.section-heading {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.section-heading__content {
    min-width: 0;
}

.section-title,
.section-subtitle,
.action-card__title,
.action-card__subtitle,
.feature-item__title,
.feature-item__subtitle {
    margin: 0;
}

.section-title {
    font-size: 0.975rem;
    font-weight: 750;
    line-height: 1.35;
}

.section-subtitle {
    margin-top: 1px;
    font-size: 0.775rem;
    line-height: 1.35;
}

.quick-actions-grid,
.features-grid {
    display: grid;
    gap: 14px;
}

.quick-actions-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.features-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.transition-all {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.action-card {
    min-height: 112px;
    padding: 14px;
    border-radius: 16px !important;
    border-color: rgba(var(--v-border-color), 0.08) !important;
}

.action-card__icon {
    margin-bottom: 10px;
}

.action-card__title,
.feature-item__title {
    font-size: 0.875rem;
    font-weight: 700;
    line-height: 1.4;
}

.action-card__subtitle,
.feature-item__subtitle {
    margin-top: 3px;
    font-size: 0.78rem;
    line-height: 1.35;
}

.action-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px -10px rgba(var(--v-theme-primary), 0.25) !important;
    border-color: rgba(var(--v-theme-primary), 0.3) !important;
    background-color: white;
}

.feature-item {
    display: flex;
    min-height: 112px;
    flex-direction: column;
    align-items: flex-start;
    padding: 14px;
    border-radius: 16px !important;
    border-color: rgba(var(--v-border-color), 0.08) !important;
}

.feature-item__icon {
    flex: 0 0 auto;
    margin-bottom: 10px;
}

.feature-item__content {
    min-width: 0;
}

.feature-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px -10px rgba(var(--v-theme-primary), 0.15) !important;
    border-color: rgba(var(--v-theme-primary), 0.2) !important;
    background-color: white;
}

@media (min-width: 1200px) {
    .action-card,
    .feature-item {
        height: 112px;
        min-height: 112px;
    }
}

@media (max-width: 1199px) {
    .dashboard-content-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }

    .quick-actions-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .features-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 767px) {
    .dashboard-page {
        padding-top: 2px;
    }

    .dashboard-header {
        margin-bottom: 16px;
    }

    .dashboard-subtitle {
        font-size: 0.925rem;
    }

    .dashboard-hero {
        min-height: 0;
        margin-bottom: 22px;
        padding: 24px 20px;
        border-radius: 20px !important;
    }

    .dashboard-hero__description {
        font-size: 0.925rem;
    }

    .dashboard-hero__icon {
        right: -46px;
        opacity: 0.07;
    }

    .dashboard-hero__glow {
        right: -45%;
    }

    .quick-actions-grid,
    .features-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .action-card {
        min-height: 0;
    }
}
</style>
