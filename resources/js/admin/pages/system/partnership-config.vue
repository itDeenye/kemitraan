<template>
    <div>
        <div class="mb-6">
            <h1 class="text-h4 font-weight-bold mb-1">Konfigurasi Kemitraan</h1>
            <p class="text-body-2 text-medium-emphasis">
                Halaman untuk mengelola konfigurasi sistem kemitraan DNY
                Skincare.
            </p>
        </div>

        <div v-if="isLoading" class="pa-16 text-center">
            <v-progress-circular
                indeterminate
                color="primary"
                size="48"
            ></v-progress-circular>
            <p class="text-body-2 text-medium-emphasis mt-4">
                Memuat konfigurasi...
            </p>
        </div>

        <div v-else-if="configs.length === 0" class="pa-16 text-center">
            <v-icon size="64" color="grey-lighten-1" class="mb-4"
                >mdi-cog-off</v-icon
            >
            <p class="text-body-1 text-medium-emphasis">
                Tidak ada konfigurasi yang ditemukan.
            </p>
        </div>

        <v-row v-else>
            <v-col
                v-for="config in configs"
                :key="config.id"
                cols="12"
                md="6"
                lg="4"
            >
                <v-card
                    variant="elevated"
                    color="card-secondary"
                    class="rounded-lg h-100"
                >
                    <v-card-title
                        class="pa-5 pb-3 d-flex align-center justify-space-between"
                    >
                        <div class="d-flex align-center ga-3">
                            <v-icon
                                :icon="getConfigIcon(config.key)"
                                color="primary"
                                size="24"
                            ></v-icon>
                            <span class="text-subtitle-1 font-weight-bold">{{
                                config.label
                            }}</span>
                        </div>
                        <v-btn
                            v-tooltip:top="'Edit Konfigurasi'"
                            icon="mdi-pencil-outline"
                            variant="text"
                            size="small"
                            color="info"
                            @click="openEditDialog(config)"
                        ></v-btn>
                    </v-card-title>

                    <v-divider></v-divider>

                    <v-card-text class="pa-0">
                        <v-list density="compact" bg-color="transparent">
                            <v-list-item
                                v-for="entry in config.entries"
                                :key="entry.key"
                                class="px-5"
                            >
                                <v-list-item-title
                                    class="text-body-2 text-medium-emphasis"
                                >
                                    {{ entry.label }}
                                </v-list-item-title>
                                <template #append>
                                    <v-chip
                                        v-if="typeof entry.value === 'boolean'"
                                        size="small"
                                        variant="tonal"
                                        :color="
                                            entry.value ? 'success' : 'error'
                                        "
                                        label
                                    >
                                        {{ entry.value ? "Aktif" : "Nonaktif" }}
                                    </v-chip>
                                    <span
                                        v-else
                                        class="font-weight-bold text-body-2"
                                    >
                                        {{ entry.value }}
                                    </span>
                                </template>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <SystemConfigForm
            ref="configFormRef"
            :save-handler="handleSaveConfig"
            @saved="fetchConfigs"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import type { SystemConfig } from "@/admin/types/system-config";
import systemConfigService from "@/admin/services/system-config.service";
import SystemConfigForm from "@/admin/components/system/SystemConfigForm.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const snackbar = useSnackbarStore();

const configs = ref<SystemConfig[]>([]);
const isLoading = ref(false);

const configFormRef = ref<InstanceType<typeof SystemConfigForm> | null>(null);

const configIcons: Record<string, string> = {
    upgrade: "mdi-arrow-up-bold-circle-outline",
    return: "mdi-package-variant-closed",
    development: "mdi-code-tags",
    reward_monthly: "mdi-trophy-outline",
    reward_stockist: "mdi-store-outline",
    partnership: "mdi-handshake-outline",
};

const getConfigIcon = (key: string): string => {
    return configIcons[key] || "mdi-cog-outline";
};

const fetchConfigs = async () => {
    isLoading.value = true;
    try {
        const response = await systemConfigService.getConfigs({
            sort: "key",
            limit: 50,
        });
        configs.value = response.results;
    } catch (error: any) {
        snackbar.showMessage("Gagal memuat konfigurasi", "error");
    } finally {
        isLoading.value = false;
    }
};

const handleSaveConfig = async (
    id: number,
    values: Record<string, any>,
    config: SystemConfig,
) => {
    await systemConfigService.updateConfig(id, {
        type: config.type,
        value: values,
    });
};

const openEditDialog = (config: SystemConfig) => {
    configFormRef.value?.open(config);
};

onMounted(() => {
    fetchConfigs();
});
</script>
