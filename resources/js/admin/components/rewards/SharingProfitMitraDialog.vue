<template>
    <v-dialog v-model="isOpen" max-width="1200" scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-avatar color="primary" variant="tonal" size="48">
                        <v-icon>mdi-account-cash</v-icon>
                    </v-avatar>
                    <div>
                        <div class="text-h6 font-weight-bold">
                            Detail Sharing Profit Mitra
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ mitra?.upline?.name || "Memuat..." }}
                            ({{ mitra?.upline?.code || "Memuat..." }})
                        </div>
                    </div>
                </div>
                <div class="d-flex align-center ga-2">
                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        size="small"
                        @click="close"
                    />
                </div>
            </v-card-title>

            <v-card-text class="pa-0" style="background-color: #f8f9fa">
                <div class="px-4 pb-4 bg-white pt-4">
                    <BaseDataTable
                        v-if="mitra"
                        ref="detailTable"
                        :url="tableUrl"
                        :headers="headers"
                        :show-search="true"
                    >
                        <template #item.trx_price="{ item }">
                            {{ formatPrice(item.trx_price) }}
                        </template>

                        <template #item.amount="{ item }">
                            <span class="font-weight-bold">{{
                                formatPrice(item.amount)
                            }}</span>
                        </template>

                        <template #item.receipt_url="{ item }">
                            <div class="d-flex justify-center">
                                <v-img
                                    v-if="item.receipt_url"
                                    :src="item.receipt_url"
                                    width="48"
                                    height="48"
                                    cover
                                    class="rounded cursor-pointer border bg-grey-lighten-4"
                                    @click="openImagePreview(item.receipt_url)"
                                >
                                    <template v-slot:placeholder>
                                        <div
                                            class="d-flex align-center justify-center fill-height"
                                        >
                                            <v-progress-circular
                                                indeterminate
                                                color="primary"
                                                size="16"
                                            ></v-progress-circular>
                                        </div>
                                    </template>
                                </v-img>
                                <span
                                    v-else
                                    class="text-caption text-medium-emphasis"
                                    >-</span
                                >
                            </div>
                        </template>

                        <template #item.status="{ item }">
                            <BaseBadge
                                type="sharing_reward_status"
                                :value="item.status"
                                inline
                            />
                        </template>

                        <template #item.submitted_datetime="{ item }">
                            {{
                                item.submitted_datetime
                                    ? formatDateTime(item.submitted_datetime)
                                    : "-"
                            }}
                        </template>
                    </BaseDataTable>
                </div>
            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions class="pa-6 pt-4 border-t bg-surface">
                <v-spacer></v-spacer>
                <v-btn
                    variant="flat"
                    color="primary"
                    class="text-none px-6"
                    @click="close"
                >
                    Tutup
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="imagePreview" max-width="800">
        <v-card class="rounded-xl overflow-hidden bg-black">
            <v-toolbar
                color="transparent"
                class="position-absolute w-100"
                style="z-index: 1"
            >
                <v-spacer></v-spacer>
                <v-btn
                    icon="mdi-close"
                    variant="tonal"
                    color="white"
                    class="ma-2 bg-black opacity-70"
                    @click="imagePreview = false"
                ></v-btn>
            </v-toolbar>
            <v-img
                :src="previewImageUrl"
                class="bg-grey-darken-4"
                max-height="90vh"
            ></v-img>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import type { SharingProfitMitra } from "@/admin/types/sharing-profit";
import sharingProfitService from "@/admin/services/sharing-profit.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";

const emit = defineEmits(["refresh"]);

const { formatPrice, formatDateTime } = useFormatter();
const snackbar = useSnackbarStore();

const isOpen = ref(false);
const mitra = ref<SharingProfitMitra | null>(null);
const detailTable = ref<InstanceType<typeof BaseDataTable> | null>(null);

const imagePreview = ref(false);
const previewImageUrl = ref("");

const tableUrl = computed(() => {
    return mitra.value
        ? `/admin/rewards/sharing-profits/${mitra.value.upline.id}`
        : null;
});

const headers = [
    {
        title: "Kode Transaksi",
        key: "trx_code",
        align: "start",
        sortable: false,
        search: true,
    },
    {
        title: "Pembeli",
        key: "buyer_name",
        align: "start",
        sortable: false,
        search: true,
    },
    {
        title: "Nilai Transaksi (Rp)",
        key: "trx_price",
        align: "right",
        sortable: false,
    },
    {
        title: "Komisi (Rp)",
        key: "amount",
        align: "right",
        sortable: false,
    },
    {
        title: "Bukti Bayar",
        key: "receipt_url",
        align: "center",
        sortable: false,
    },
    {
        title: "Status",
        key: "status",
        align: "center",
        sortable: false,
    },

    {
        title: "Waktu Pengajuan",
        key: "submitted_datetime",
        align: "start",
        sortable: false,
    },
];

const open = (data: SharingProfitMitra) => {
    mitra.value = data;
    isOpen.value = true;
};

const close = () => {
    isOpen.value = false;
    mitra.value = null;
};

const openImagePreview = (url: string) => {
    previewImageUrl.value = url;
    imagePreview.value = true;
};

defineExpose({
    open,
    close,
});
</script>
