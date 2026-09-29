<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-6 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">
                    Approval Registrasi
                </h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman pengajuan mitra baru yang perlu disetujui atau
                    ditolak.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="approvalTable"
            url="/admin/partnership/registrations?filter[status]=requested"
            :headers="headers"
            v-model:search="search"
        >
            <template #item.submitted_at="{ item }">
                {{ formatDateTime(item.submitted_at) }}
            </template>

            <template #item.applicant.name="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-bold">
                        {{ item.applicant?.name }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ item.applicant?.mobile_phone }}
                    </div>
                </div>
            </template>

            <template #item.level="{ item, column }">
                <BaseBadge
                    type="level"
                    :value="item.level?.name"
                    :align="column.align"
                />
            </template>

            <template #item.sponsor.name="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-bold">
                        {{ item.sponsor?.name || "-" }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ item.sponsor?.code }}
                    </div>
                </div>
            </template>

            <template #item.status="{ item, column }">
                <BaseBadge
                    type="registration_status"
                    :value="item.status?.code"
                    :align="column.align"
                />
            </template>

            <template #item.actions="{ item }">
                <div class="d-flex ga-2">
                    <v-btn
                        v-tooltip:top="'Detail Pengajuan'"
                        icon="mdi-eye-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="primary"
                        @click="openDetailDialog(item.id)"
                    />
                    <v-btn
                        v-tooltip:top="'Setujui Pengajuan'"
                        icon="mdi-check-circle-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="success"
                        @click="
                            openActionDialog(
                                item.id,
                                item.applicant?.name,
                                'approve',
                            )
                        "
                    />
                    <v-btn
                        v-tooltip:top="'Tolak Pengajuan'"
                        icon="mdi-close-circle-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="error"
                        @click="
                            openActionDialog(
                                item.id,
                                item.applicant?.name,
                                'reject',
                            )
                        "
                    />
                </div>
            </template>
        </BaseDataTable>

        <!-- Modals -->
        <RegistrationDetailDialog ref="detailRef" />
        <ApprovalActionDialog ref="actionRef" @processed="refreshTable" />
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import RegistrationDetailDialog from "@/admin/components/partnership/RegistrationDetailDialog.vue";
import ApprovalActionDialog from "@/admin/components/partnership/ApprovalActionDialog.vue";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime } = useFormatter();

const search = ref("");
const approvalTable = ref();
const detailRef = ref();
const actionRef = ref();

const headers = [
    {
        title: "Tanggal Registrasi",
        key: "submitted_at",
        align: "left",
        type: "date",
        sortable: false,
        filter: true,
    },
    {
        title: "Calon Mitra",
        key: "applicant.name",
        filterKey: "name",
        align: "left",
        type: "text",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Nama Calon Mitra",
    },
    {
        title: "Level",
        key: "level",
        filterKey: "level_name",
        align: "center",
        type: "select",
        sortable: false,
        filter: true,
        options: [
            { value: "Distributor", label: "Distributor" },
            { value: "Agent", label: "Agent" },
            { value: "Reseller", label: "Reseller" },
        ],
        placeholder: "Pilih Level",
    },
    {
        title: "Upline",
        key: "sponsor.name",
        align: "left",
        type: "text",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Nama Upline",
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
            { value: "approved", label: "Disetujui" },
            { value: "rejected", label: "Ditolak" },
        ],
        placeholder: "Pilih Status",
    },
    { title: "Aksi", key: "actions", align: "center", sortable: false },
];

const openDetailDialog = (id: number) => {
    if (detailRef.value) {
        detailRef.value.open(id);
    }
};

const openActionDialog = (
    id: number,
    name: string,
    actionType: "approve" | "reject",
) => {
    if (actionRef.value) {
        actionRef.value.open(id, name, actionType);
    }
};

const refreshTable = () => {
    if (approvalTable.value) {
        approvalTable.value.refresh();
    }
};
</script>
