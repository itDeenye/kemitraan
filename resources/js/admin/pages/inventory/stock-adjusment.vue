<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-6 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Penyesuaian Stok</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman input dan riwayat penyesuaian stok produk
                    perusahaan.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="adjustmentTable"
            url="/admin/inventory/adjustments"
            :headers="headers"
            v-model:search="search"
        >
            <template #header-actions>
                <v-btn
                    color="primary"
                    prepend-icon="mdi-plus"
                    @click="openFormDialog"
                    variant="outlined"
                    class="bg-surface rounded-lg"
                >
                    Tambah Penyesuaian
                </v-btn>
            </template>

            <template #item.code="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-bold">
                        {{ item.code }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ formatDateTime(item.happened_at) }}
                    </div>
                </div>
            </template>

            <template #item.warehouse="{ item }">
                <span class="font-weight-medium">{{
                    item.warehouse?.name
                }}</span>
            </template>

            <template #item.total_items="{ item }">
                <span class="font-weight-medium">{{
                    formatPrice(item.total_items)
                }}</span>
            </template>

            <template #item.total_quantity="{ item }">
                <span class="font-weight-bold">{{
                    formatPrice(item.total_quantity)
                }}</span>
            </template>

            <template #item.happened_at="{ item }">
                {{ formatDateTime(item.happened_at) }}
            </template>

            <template #item.actions="{ item }">
                <div class="d-flex ga-2 justify-center">
                    <v-btn
                        v-tooltip:top="'Detail Penyesuaian'"
                        icon="mdi-eye-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="primary"
                        @click="openDetail(item.id)"
                    />
                </div>
            </template>
        </BaseDataTable>

        <!-- Modals -->
        <AdjustmentDetail ref="detailRef" />
        <AdjustmentForm ref="formRef" @saved="refreshTable" />
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import AdjustmentDetail from "@/admin/components/inventory/AdjustmentDetail.vue";
import AdjustmentForm from "@/admin/components/inventory/AdjustmentForm.vue";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime, formatPrice } = useFormatter();
const search = ref("");
const adjustmentTable = ref();
const detailRef = ref();
const formRef = ref();

const headers = [
    {
        title: "Kode Penyesuaian",
        key: "code",
        align: "left",
        type: "text",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Penyesuaian",
    },
    // { title: "Gudang", key: "warehouse", align: "left", sortable: false },
    {
        title: "Total Produk",
        key: "total_items",
        align: "right",
        sortable: false,
    },
    {
        title: "Total Qty",
        key: "total_quantity",
        align: "right",
        sortable: false,
    },
    { title: "Catatan", key: "note", align: "left", sortable: false },
    {
        title: "Tanggal Penyesuaian",
        key: "happened_at",
        type: "date",
        align: "left",
        visible: false,
        sortable: false,
        filter: true,
    },
    {
        title: "Aksi",
        key: "actions",
        align: "center",
        sortable: false,
        width: "80px",
    },
];

const openDetail = (id: number) => {
    if (detailRef.value) {
        detailRef.value.open(id);
    }
};

const openFormDialog = () => {
    if (formRef.value) {
        formRef.value.open();
    }
};

const refreshTable = () => {
    if (adjustmentTable.value) {
        adjustmentTable.value.refresh();
    }
};
</script>
