<template>
    <div>
        <div class="d-flex align-center justify-space-between mb-4">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Data Stokis</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk mengelola data stokis DNY Skincare.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="tableRef"
            url="/admin/partnership/stockists"
            :headers="headers"
            v-model:search="search"
        >
            <template #header-actions>
                <v-btn
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-store-plus"
                    @click="openForm()"
                    >Tambah Stokis</v-btn
                >
            </template>

            <template #item.name="{ item }">
                <span class="font-weight-medium">{{ item.name }}</span>
            </template>

            <template #item.member.name="{ item }">
                <div class="flex flex-col">
                    <span class="text-body-2 font-weight-bold mb-0">{{
                        item.member?.name
                    }}</span>
                    <span class="text-caption text-medium-emphasis mb-0">
                        {{ item.member?.code }}
                    </span>
                </div>
            </template>

            <template #item.region="{ item }">
                {{ formatLocation(item.region) }}
            </template>

            <template #item.is_active="{ item, column }">
                <BaseBadge
                    type="status"
                    :value="item.is_active"
                    :align="column.align"
                    :label="item.is_active ? 'Aktif' : 'Nonaktif'"
                />
            </template>

            <template #item.actions="{ item }">
                <div class="d-flex ga-2">
                    <v-btn
                        v-tooltip:top="'Edit Stokis'"
                        icon="mdi-pencil-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="info"
                        @click="openForm(item.id)"
                    />
                    <v-btn
                        v-tooltip:top="'Hapus Stokis'"
                        icon="mdi-delete-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="error"
                        @click="confirmDelete(item.id, item.name)"
                    />
                </div>
            </template>
        </BaseDataTable>

        <StockistForm ref="formRef" @saved="refreshTable" />
        <ConfirmDialog ref="confirmRef" />
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import StockistForm from "@/admin/components/partnership/StockistForm.vue";
import ConfirmDialog from "@/shared/components/BaseConfirm.vue";
import api from "@/shared/services/api";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatLocation } = useFormatter();
const snackbar = useSnackbarStore();

const search = ref("");
const tableRef = ref();
const formRef = ref();
const confirmRef = ref();

const headers = [
    {
        title: "Nama Stokis",
        key: "name",
        type: "text",
        align: "left",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Nama Stokis",
    },
    {
        title: "Pemilik (Mitra)",
        key: "member.name",
        type: "text",
        align: "left",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Nama Pemilik (Mitra)",
    },
    {
        title: "Kode Mitra",
        key: "member.code",
        align: "left",
        type: "text",
        visible: false,
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Mitra",
    },
    { title: "Wilayah", key: "region", align: "left", sortable: false },
    {
        title: "Nomor Telepon",
        key: "mobile_phone",
        type: "text",
        align: "left",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Nomor Telepon",
    },
    {
        title: "Status",
        key: "is_active",
        align: "center",
        type: "select",
        sortable: false,
        filter: true,
        options: [
            { value: 1, label: "Aktif" },
            { value: 0, label: "Tidak Aktif" },
        ],
        placeholder: "Pilih Status",
    },
    { title: "Aksi", key: "actions", align: "center", sortable: false },
];

const openForm = (id?: number) => {
    if (formRef.value) {
        formRef.value.open(id);
    }
};

const refreshTable = () => {
    if (tableRef.value) {
        tableRef.value.refresh();
    }
};

const confirmDelete = (id: number, name: string) => {
    confirmRef.value?.open({
        title: "Hapus Stockist",
        message: `Apakah Anda yakin ingin menghapus data stockist "${name}"? Data yang sudah dihapus tidak dapat dikembalikan.`,
        icon: "mdi-alert-circle",
        confirmColor: "error",
        onConfirm: async () => {
            try {
                await api.delete(`/admin/partnership/stockists/${id}`);
                snackbar.showMessage(
                    "Data stockist berhasil dihapus.",
                    "success",
                );
                refreshTable();
            } catch (error: any) {
                snackbar.showMessage(
                    error.response?.data?.message ||
                        "Terjadi kesalahan saat menghapus data.",
                    "error",
                );
            }
        },
    });
};
</script>
