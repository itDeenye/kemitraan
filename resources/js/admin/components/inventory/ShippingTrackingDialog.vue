<template>
    <v-dialog v-model="dialog" max-width="760" scrollable>
        <v-card class="rounded-xl">
            <v-card-title class="pa-6 pb-4 border-b d-flex align-center justify-space-between">
                <div class="d-flex align-center ga-3">
                    <v-icon color="info" size="28">mdi-map-marker-path</v-icon>
                    <div>
                        <div class="text-h6 font-weight-bold text-primary">Tracking Pengiriman</div>
                        <div class="text-caption text-medium-emphasis">
                            {{ order?.code || "Memuat tracking pengiriman..." }}
                        </div>
                    </div>
                </div>
                <v-btn icon="mdi-close" variant="text" color="medium-emphasis" density="comfortable" @click="close" />
            </v-card-title>

            <v-card-text class="pa-6" style="max-height: 70vh">
                <div v-if="loading" class="d-flex justify-center py-12">
                    <v-progress-circular indeterminate color="primary" size="48" />
                </div>

                <template v-else-if="shippingTracking && tracking">
                    <v-card variant="outlined" class="rounded-lg pa-4 mb-5">
                        <v-row>
                            <v-col cols="12" sm="6">
                                <div class="text-caption text-medium-emphasis">Nomor Resi / AWB</div>
                                <div class="font-weight-bold text-body-1">
                                    {{ trackingDetails.awb || shippingTracking.tracking_number || "-" }}
                                </div>
                            </v-col>
                            <v-col cols="12" sm="6">
                                <div class="text-caption text-medium-emphasis">Kurir & Layanan</div>
                                <div class="font-weight-medium text-capitalize">{{ courierLabel }}</div>
                            </v-col>
                            <v-col cols="12" sm="6">
                                <div class="text-caption text-medium-emphasis">Order ID</div>
                                <div class="font-weight-medium">
                                    {{ trackingDetails.order_id || shippingTracking.order_id || "-" }}
                                </div>
                            </v-col>
                            <v-col cols="12" sm="6">
                                <div class="text-caption text-medium-emphasis mb-1">Status Terakhir</div>
                                <v-chip
                                    :color="trackingDetails.delivered ? 'success' : 'info'"
                                    size="small"
                                    variant="tonal"
                                    class="font-weight-medium"
                                >
                                    {{ latestStatus }}
                                </v-chip>
                            </v-col>
                            <v-col v-if="trackingDetails.estimation" cols="12" sm="6">
                                <div class="text-caption text-medium-emphasis">Estimasi</div>
                                <div class="font-weight-medium">{{ trackingDetails.estimation }}</div>
                            </v-col>
                            <v-col v-if="trackingDetails.delivered_at" cols="12" sm="6">
                                <div class="text-caption text-medium-emphasis">Waktu Diterima</div>
                                <div class="font-weight-medium">
                                    {{ formatDateTime(trackingDetails.delivered_at) }}
                                </div>
                            </v-col>
                        </v-row>
                    </v-card>

                    <h3 class="text-subtitle-1 font-weight-bold mb-3">Riwayat Tracking</h3>
                    <v-card variant="outlined" class="rounded-lg overflow-hidden">
                        <v-list v-if="trackingHistories.length" lines="three">
                            <template
                                v-for="(history, index) in trackingHistories"
                                :key="`${history.status_code}-${history.created_at}-${index}`"
                            >
                                <v-list-item>
                                    <template #prepend>
                                        <v-avatar :color="trackingIconColor(history, index)" variant="tonal" size="36">
                                            <v-icon size="20">
                                                {{ trackingIcon(history, index) }}
                                            </v-icon>
                                        </v-avatar>
                                    </template>
                                    <v-list-item-title class="font-weight-bold text-wrap">
                                        {{ history.status || "Status pengiriman diperbarui" }}
                                    </v-list-item-title>
                                    <v-list-item-subtitle
                                        v-if="history.receiver || history.driver"
                                        class="text-wrap mt-1"
                                    >
                                        <span v-if="history.receiver">Penerima: {{ history.receiver }}</span>
                                        <span v-if="history.receiver && history.driver"> · </span>
                                        <span v-if="history.driver">Kurir: {{ history.driver }}</span>
                                    </v-list-item-subtitle>
                                    <div class="text-caption text-medium-emphasis mt-1">
                                        {{ formatDateTime(history.created_at) }}
                                    </div>
                                </v-list-item>
                                <v-divider v-if="index < trackingHistories.length - 1" />
                            </template>
                        </v-list>
                        <div v-else class="text-center pa-8 text-medium-emphasis">
                            Belum ada riwayat perjalanan paket.
                        </div>
                    </v-card>
                </template>
            </v-card-text>

            <v-card-actions class="pa-6 pt-4 border-t">
                <v-btn
                    v-if="canSimulateFinished"
                    variant="outlined"
                    color="warning"
                    prepend-icon="mdi-test-tube"
                    class="text-none"
                    :loading="simulating"
                    @click="simulateFinished"
                >
                    Simulasikan Selesai (Dev)
                </v-btn>
                <v-spacer />
                <v-btn variant="flat" color="primary" class="text-none px-8" @click="close">Tutup</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import inventoryService from "@/admin/services/inventory.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const emit = defineEmits<{ success: [] }>();
const { formatDateTime } = useFormatter();
const snackbar = useSnackbarStore();

const dialog = ref(false);
const loading = ref(false);
const simulating = ref(false);
const order = ref<any>(null);
const tracking = ref<any>(null);

const trackingDetails = computed(() => tracking.value?.details || {});
const shippingTracking = computed(() => order.value?.tracking || order.value?.shipping);
const trackingHistories = computed(() =>
    Array.isArray(tracking.value?.histories) ? tracking.value.histories : [],
);
const latestStatus = computed(() => {
    if (trackingDetails.value.delivered) return "Terkirim";
    return trackingHistories.value[0]?.status || "Dalam Pengiriman";
});
const courierLabel = computed(() => {
    const courier = trackingDetails.value.service || shippingTracking.value?.courier;
    const service = trackingDetails.value.service_name || shippingTracking.value?.service;
    return [courier, service].filter(Boolean).join(" - ") || "-";
});

const trackingIcon = (history: any, index: number) => {
    const status = String(history?.status || "").toLowerCase();
    if (history?.status_code === 200 || /delivered|diterima|terkirim/.test(status)) {
        return "mdi-check-circle-outline";
    }
    if (/courier|kurir|delivery|dikirim|transit/.test(status) || index === 0) {
        return "mdi-truck-fast";
    }
    if (/pickup|picked|manifest|shipment|received|paket/.test(status)) {
        return "mdi-package-variant-closed";
    }
    return "mdi-map-marker-outline";
};

const trackingIconColor = (history: any, index: number) => {
    const icon = trackingIcon(history, index);
    if (icon === "mdi-check-circle-outline") return "success";
    if (icon === "mdi-truck-fast") return "info";
    return "primary";
};
const canSimulateFinished = computed(
    () =>
        order.value?.actions?.can_simulate_finished === true &&
        !!shippingTracking.value?.order_id,
);

const callbackDateTime = () =>
    new Date().toISOString().slice(0, 19).replace("T", " ");

const load = async (id: number) => {
    loading.value = true;
    try {
        const [detailResponse, trackingResponse] = await Promise.all([
            inventoryService.getShipmentDetail(id),
            inventoryService.trackExpressShipment(id),
        ]);
        order.value = detailResponse.data?.data || detailResponse.data;
        tracking.value = trackingResponse.data?.data || trackingResponse.data;
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Gagal memuat tracking pengiriman.",
            "error",
        );
        close();
    } finally {
        loading.value = false;
    }
};

const open = async (id: number) => {
    dialog.value = true;
    order.value = null;
    tracking.value = null;
    await load(id);
};

const simulateFinished = async () => {
    if (!canSimulateFinished.value) return;

    simulating.value = true;
    const eventTime = callbackDateTime();
    const awb = shippingTracking.value.tracking_number || `DEV-AWB-${order.value.id}-${Date.now()}`;

    try {
        await inventoryService.simulateStcFinishedPackage({
            order_id: shippingTracking.value.order_id,
            awb,
            date: eventTime,
            finished_at: eventTime,
        });
        snackbar.showMessage("Simulasi finished_packages berhasil diproses.", "success");
        await load(order.value.id);
        emit("success");
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Simulasi status pengiriman belum berhasil.",
            "error",
        );
    } finally {
        simulating.value = false;
    }
};

const close = () => {
    dialog.value = false;
};

defineExpose({ open });
</script>
