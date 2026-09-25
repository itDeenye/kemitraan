<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-6 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Approval Upgrade</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk evaluasi pengajuan kenaikan level kemitraan.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="upgradeTable"
            url="/admin/partnership/upgrades?status=requested"
            :headers="headers"
            v-model:search="search"
        >
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

            <template #item.requirements>
                <span class="text-caption text-medium-emphasis">-</span>
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
                <div class="d-flex ga-2">
                    <v-btn
                        v-tooltip:top="'Detail Upgrade'"
                        icon="mdi-eye-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="primary"
                        @click="openDetail(item.id)"
                    />
                    <v-btn
                        v-tooltip:top="'Setujui Kualifikasi'"
                        v-if="item.status?.code !== 'scheduled'"
                        icon="mdi-check-circle-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="success"
                        @click="
                            openActionDialog(
                                item.id,
                                item.member?.name,
                                'approve',
                            )
                        "
                    />
                    <v-btn
                        v-tooltip:top="'Tolak Kualifikasi'"
                        v-if="item.status?.code !== 'scheduled'"
                        icon="mdi-close-circle-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="error"
                        @click="
                            openActionDialog(
                                item.id,
                                item.member?.name,
                                'reject',
                            )
                        "
                    />
                </div>
            </template>
        </BaseDataTable>

        <UpgradeDetailDialog ref="detailDialogRef" />
        <ApprovalActionDialog ref="actionRef" @processed="refreshTable" />
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import UpgradeDetailDialog from "@/admin/components/partnership/UpgradeDetailDialog.vue";
import ApprovalActionDialog from "@/admin/components/partnership/ApprovalActionDialog.vue";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime } = useFormatter();

const search = ref("");
const upgradeTable = ref();
const detailDialogRef = ref();
const actionRef = ref();

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
            { value: "approved", label: "Disetujui" },
            { value: "rejected", label: "Ditolak" },
        ],
        placeholder: "Pilih Status",
    },
    { title: "Tanggal Berlaku", key: "effective_date", align: "left" },
    { title: "Aksi", key: "actions", align: "center", sortable: false },
];

const openDetail = (id: number) => {
    if (detailDialogRef.value) {
        detailDialogRef.value.open(id);
    }
};

const openActionDialog = (
    id: number,
    name: string,
    actionType: "approve" | "reject",
) => {
    if (actionRef.value) {
        // We pass 'upgrades' as the resource type to hit the correct API
        actionRef.value.open(id, name, actionType, "upgrades", "upgrade");
    }
};

const refreshTable = () => {
    if (upgradeTable.value) {
        upgradeTable.value.refresh();
    }
};
</script>
