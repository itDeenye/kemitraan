<template>
    <div>
        <div class="d-flex align-center justify-space-between mb-4">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Data Pelanggan</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk melihat data pelanggan (customer / pasien /
                    end user).
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="dataTable"
            url="/admin/customers"
            :headers="headers"
        >
            <template #item.name="{ item }">
                <div class="font-weight-medium">
                    {{ item.name }}
                </div>
            </template>
            <template #item.whatsapp="{ item }">
                <div class="text-body-2 font-family-monospace">
                    {{ item.whatsapp || "-" }}
                </div>
            </template>
            <template #item.member.name="{ item }">
                <div v-if="item.member?.id" class="d-flex flex-column py-2">
                    <span class="text-body-2 font-weight-bold mb-0">{{
                        item.member?.name
                    }}</span>
                    <span class="text-caption text-medium-emphasis mb-0">
                        {{ item.member?.code }}
                    </span>
                    <BaseBadge
                        type="level"
                        :value="item.member.level?.name"
                        size="x-small"
                        variant="tonal"
                    />
                </div>
                <span v-else class="text-caption text-medium-emphasis">-</span>
            </template>
            <template #item.actions="{ item }">
                <div class="d-flex ga-2 justify-center">
                    <v-btn
                        v-tooltip:top="'Detail Pelanggan'"
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

        <CustomerDetailDialog ref="detailDialogRef" />
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import CustomerDetailDialog from "@/admin/components/customer/CustomerDetailDialog.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";

const dataTable = ref<InstanceType<typeof BaseDataTable> | null>(null);
const detailDialogRef = ref<InstanceType<typeof CustomerDetailDialog> | null>(
    null,
);

const headers = [
    {
        title: "Nama Pelanggan",
        key: "name",
        align: "start",
        type: "text",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Nama Pelanggan",
    },
    {
        title: "Nomor WhatsApp",
        key: "whatsapp",
        type: "text",
        align: "start",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Nomor WhatsApp Pelanggan",
    },
    {
        title: "Mitra Pencatat",
        key: "member.name",
        type: "text",
        align: "start",
        sortable: false,
        filter: true,
        filterLabel: "Nama Mitra Pencatat",
        placeholder: "Masukkan Nama Mitra Pencatat",
    },
    {
        title: "Kode Mitra Pencatat",
        key: "member.code",
        type: "text",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Mitra Pencatat",
    },
    {
        title: "Aksi",
        key: "actions",
        align: "center",
        sortable: false,
        width: "100px",
    },
];

const openDetail = (id: string | number) => {
    detailDialogRef.value?.open(id);
};
</script>
