<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-6 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Downgrade Mitra</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk eksekusi penurunan level kemitraan.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="downgradeTable"
            url="/admin/partnership/downgrades"
            :headers="headers"
            v-model:search="search"
        >
            <template #header-actions>
                <v-btn
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-plus"
                    @click="openDowngradeForm"
                    class="text-none"
                >
                    Jadwalkan Downgrade
                </v-btn>
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

            <template #item.from_level="{ item, column }">
                <BaseBadge
                    type="level"
                    :value="item.from_level?.name"
                    :align="column.align"
                />
            </template>

            <template #item.to_level="{ item, column }">
                <BaseBadge
                    type="level"
                    :value="item.to_level?.name"
                    :align="column.align"
                />
            </template>

            <template #item.status="{ item, column }">
                <BaseBadge
                    type="upgrade_status"
                    :value="item.status?.code"
                    :align="column.align"
                />
            </template>

            <template #item.created_at="{ item }">
                {{ formatDateTime(item.created_at || item.date) }}
            </template>

            <template #item.effective_date="{ item }">
                {{ formatDateTime(item.effective_date || item.date) }}
            </template>

            <template #item.actions="{ item }">
                <div class="d-flex ga-2 justify-center">
                    <v-btn
                        v-tooltip:top="'Detail Downgrade'"
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

        <DowngradeDetailDialog ref="detailDialogRef" />
        <DowngradeForm ref="downgradeFormRef" @saved="refreshTable" />
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import DowngradeForm from "@/admin/components/partnership/DowngradeForm.vue";
import DowngradeDetailDialog from "@/admin/components/partnership/DowngradeDetailDialog.vue";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime } = useFormatter();

const search = ref("");
const downgradeTable = ref();
const downgradeFormRef = ref();
const detailDialogRef = ref();

const headers = [
    {
        title: "Tanggal Kualifikasi",
        key: "created_at",
        align: "left",
        type: "date",
        sortable: false,
        filter: true,
    },
    {
        title: "Mitra",
        key: "member.name",
        align: "left",
        type: "text",
        sortable: false,
        filter: true,
        filterLabel: "Nama Mitra",
        placeholder: "Masukkan Nama Mitra",
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
    {
        title: "Level Saat Ini",
        key: "from_level",
        align: "center",
        sortable: false,
    },
    {
        title: "Level Tujuan",
        key: "to_level",
        align: "center",
        sortable: false,
    },
    {
        title: "Status",
        key: "status",
        align: "center",
        type: "select",
        sortable: false,
        filter: true,
        options: [
            { value: "requested", label: "Menunggu" },
            { value: "scheduled", label: "Dijadwalkan" },
            { value: "approved", label: "Selesai" },
            { value: "rejected", label: "Dibatalkan" },
        ],
        placeholder: "Pilih Status",
    },
    { title: "Tanggal Berlaku", key: "effective_date", align: "left" },
    { title: "Aksi", key: "actions", align: "center", sortable: false },
];

const openDowngradeForm = () => {
    if (downgradeFormRef.value) {
        downgradeFormRef.value.open();
    }
};

const openDetail = (id: number) => {
    if (detailDialogRef.value) {
        detailDialogRef.value.open(id);
    }
};

const refreshTable = () => {
    if (downgradeTable.value) {
        downgradeTable.value.refresh();
    }
};
</script>
