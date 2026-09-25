<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-6 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Pengiriman Barang</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk melihat daftar pesanan yang siap atau dalam
                    proses pengiriman.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="shippingTable"
            url="/admin/inventory/shipments"
            :headers="headers"
            :query-params="queryParams"
            v-model:search="search"
        >
            <template #item.code="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-bold">
                        {{ item.code }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ formatDateTime(item.ordered_at) }}
                    </div>
                </div>
            </template>

            <template #item.order_type.code="{ item }">
                <v-chip
                    :color="
                        item.order_type?.code === 'preorder'
                            ? 'warning'
                            : 'info'
                    "
                    size="small"
                    variant="tonal"
                    class="font-weight-medium"
                >
                    {{ item.order_type?.label || "Reguler" }}
                </v-chip>
            </template>

            <template #item.buyer.name="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-bold">
                        {{ item.buyer?.name }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ item.buyer?.code || "-" }}
                    </div>
                </div>
            </template>

            <template #item.shipping_method="{ item }">
                <BaseBadge
                    type="shipping_method"
                    :value="item.shipping_method"
                />
            </template>

            <template #item.status="{ item }">
                <BaseBadge type="shipping_status" :value="item.status" />
            </template>

            <template #item.actions="{ item }">
                <div class="d-flex ga-2 justify-center">
                    <v-btn
                        v-tooltip:top="'Detail Pengiriman'"
                        icon="mdi-eye-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="primary"
                        @click="openDetail(item.id)"
                    />
                    <v-btn
                        v-if="['processing'].includes(item.status)"
                        v-tooltip:top="'Kirim Pesanan'"
                        icon="mdi-truck-fast"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="success"
                        @click="confirmComplete(item)"
                    />
                    <v-btn
                        v-if="['reship_required'].includes(item.status)"
                        v-tooltip:top="'Kirim Ulang Pesanan'"
                        icon="mdi-truck-cargo-container"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="warning"
                        @click="confirmReship(item)"
                    />
                    <v-btn
                        v-if="item.actions?.can_track"
                        v-tooltip:top="'Tracking Pengiriman'"
                        icon="mdi-map-marker-path"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="info"
                        @click="openTracking(item.id)"
                    />
                    <v-btn
                        v-if="item.actions?.can_simulate_finished"
                        v-tooltip:top="'Simulasikan Paket Selesai (Dev)'"
                        icon="mdi-test-tube"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="warning"
                        :loading="simulatingId === item.id"
                        :disabled="simulatingId !== null"
                        @click="simulateFinished(item)"
                    />
                </div>
            </template>
        </BaseDataTable>

        <ShippingDetail ref="detailRef" />

        <ShippingTrackingDialog
            ref="trackingDialogRef"
            @success="shippingTable?.refresh()"
        />

        <ShippingActionDialog
            ref="actionDialogRef"
            @success="shippingTable?.refresh()"
        />

        <ShippingReshipDialog
            ref="reshipDialogRef"
            @success="shippingTable?.refresh()"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import ShippingDetail from "@/admin/components/inventory/ShippingDetail.vue";
import ShippingActionDialog from "@/admin/components/inventory/ShippingActionDialog.vue";
import ShippingReshipDialog from "@/admin/components/inventory/ShippingReshipDialog.vue";
import ShippingTrackingDialog from "@/admin/components/inventory/ShippingTrackingDialog.vue";
import inventoryService from "@/admin/services/inventory.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const { formatDateTime } = useFormatter();
const search = ref("");
const shippingTable = ref();
const detailRef = ref();
const actionDialogRef = ref();
const reshipDialogRef = ref();
const trackingDialogRef = ref();
const simulatingId = ref<number | null>(null);
const snackbar = useSnackbarStore();

const queryParams = computed(() => ({
    "filter[buyer.type]": "distributor",
}));

const headers = [
    {
        title: "Kode Transaksi",
        key: "code",
        type: "text",
        align: "left",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Transaksi",
    },
    {
        title: "Tanggal Transaksi",
        key: "ordered_at",
        type: "date",
        align: "left",
        visible: false,
        sortable: false,
        filter: true,
    },
    {
        title: "Tipe Pesanan",
        key: "order_type.code",
        filterKey: "is_preorder",
        align: "center",
        type: "select",
        sortable: false,
        filter: true,
        options: [
            { label: "Reguler", value: 0 },
            { label: "Pre-Order", value: 1 },
        ],
        placeholder: "Pilih Tipe",
    },
    {
        title: "Mitra",
        key: "buyer.name",
        type: "text",
        align: "left",
        sortable: false,
        filter: true,
        filterLabel: "Nama Mitra",
        placeholder: "Masukkan Nama Mitra",
    },
    {
        title: "Kode Mitra",
        key: "buyer.code",
        type: "text",
        visible: false,
        align: "left",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Mitra",
    },
    {
        title: "Metode Pengiriman",
        key: "shipping_method",
        type: "select",
        align: "center",
        sortable: false,
        filter: true,
        options: [
            { label: "Kurir Ekspres", value: "courier_express" },
            { label: "Ambil Sendiri", value: "pickup" },
        ],
        placeholder: "Pilih Metode",
    },
    {
        title: "Status",
        key: "status",
        type: "select",
        align: "center",
        sortable: false,
        filter: true,
        options: [
            { label: "Menunggu Pengiriman", value: "processing" },
            { label: "Dikirim", value: "shipped" },
            { label: "Perlu Dikirim Ulang", value: "reship_required" },
            { label: "Siap Diambil", value: "ready_to_pickup" },
            { label: "Siap Diterima", value: "received" },
            { label: "Selesai", value: "completed" },
        ],
        placeholder: "Pilih Status",
    },
    {
        title: "Aksi",
        key: "actions",
        align: "center",
        sortable: false,
        width: "200px",
    },
];

const openDetail = (id: number) => {
    if (detailRef.value) {
        detailRef.value.open(id);
    }
};

const confirmComplete = (item: any) => {
    if (actionDialogRef.value) {
        actionDialogRef.value.open(item);
    }
};

const confirmReship = (item: any) => {
    if (reshipDialogRef.value) {
        reshipDialogRef.value.open(item);
    }
};

const openTracking = (id: number) => {
    trackingDialogRef.value?.open(id);
};

const callbackDateTime = () =>
    new Date().toISOString().slice(0, 19).replace("T", " ");

const simulateFinished = async (item: any) => {
    const tracking = item.tracking || item.shipping;
    if (!tracking?.order_id) return;

    simulatingId.value = item.id;
    const eventTime = callbackDateTime();
    const awb = tracking.tracking_number || `DEV-AWB-${item.id}-${Date.now()}`;

    try {
        await inventoryService.simulateStcFinishedPackage({
            order_id: tracking.order_id,
            awb,
            date: eventTime,
            finished_at: eventTime,
        });
        snackbar.showMessage(
            "Simulasi finished_packages berhasil diproses.",
            "success",
        );
        shippingTable.value?.refresh();
        trackingDialogRef.value?.open(item.id);
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message ||
                "Simulasi status pengiriman belum berhasil.",
            "error",
        );
    } finally {
        simulatingId.value = null;
    }
};
</script>
