<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-6 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Mitra Distributor</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman khusus pendaftaran mitra level distributor.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="distributorTable"
            url="/admin/partnership/distributors"
            :headers="headers"
            v-model:search="search"
        >
            <template #header-actions>
                <v-btn
                    color="primary"
                    prepend-icon="mdi-account-plus-outline"
                    @click="openFormDialog"
                    variant="outlined"
                    class="rounded-lg text-none px-4"
                >
                    Tambah Distributor
                </v-btn>
            </template>

            <template #item.code="{ item }">
                <span class="font-weight-bold">{{ item.code }}</span>
            </template>

            <template #item.status="{ item, column }">
                <BaseBadge
                    type="status"
                    :value="item.status?.code === 1"
                    :align="column.align"
                />
            </template>

            <template #item.joined_at="{ item }">
                {{ formatDateTime(item.joined_at) }}
            </template>

            <template #item.actions="{ item }">
                <div class="d-flex ga-2">
                    <v-btn
                        v-tooltip:top="'Detail Distributor'"
                        icon="mdi-eye-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="primary"
                        @click="openDetailDialog(item.id)"
                    />
                </div>
            </template>
        </BaseDataTable>

        <DistributorForm ref="formRef" @saved="refreshTable" />
        <MemberDetail ref="detailRef" />
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import DistributorForm from "@/admin/components/partnership/DistributorForm.vue";
import MemberDetail from "@/admin/components/partnership/MemberDetail.vue";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime } = useFormatter();

const search = ref("");
const distributorTable = ref();
const formRef = ref();
const detailRef = ref();

const headers = [
    {
        title: "Kode Mitra",
        key: "code",
        align: "left",
        type: "text",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Mitra",
    },
    {
        title: "Nama Mitra",
        key: "name",
        align: "left",
        type: "text",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Nama Mitra",
    },
    {
        title: "Nomor Telepon",
        key: "mobile_phone",
        align: "left",
        type: "text",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Nomor Telepon",
    },
    {
        title: "Status",
        key: "status",
        align: "center",
        type: "select",
        sortable: false,
        filter: true,
        options: [
            { label: "Aktif", value: 1 },
            { label: "Tidak Aktif", value: 0 },
        ],
        placeholder: "Pilih Status",
    },
    {
        title: "Tanggal Daftar",
        key: "joined_at",
        align: "left",
        type: "date",
        sortable: false,
        filter: true,
    },
    { title: "Aksi", key: "actions", align: "center", sortable: false },
];

const openFormDialog = () => {
    if (formRef.value) {
        formRef.value.open();
    }
};

const openDetailDialog = (id: number) => {
    if (detailRef.value) {
        detailRef.value.open(id);
    }
};

const refreshTable = () => {
    if (distributorTable.value) {
        distributorTable.value.refresh();
    }
};
</script>
