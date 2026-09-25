<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-4 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Data Mitra</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk mengelola data mitra dan jaringan mitra DNY
                    Skincare.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="memberTable"
            class="member-table"
            url="/admin/partnership/members"
            :headers="headers"
            v-model:search="search"
        >
            <template #item.name="{ item }">
                <div class="d-flex align-center py-2">
                    <v-avatar
                        color="primary"
                        size="36"
                        class="mr-3 elevation-1"
                    >
                        <v-img
                            v-if="item.image"
                            :src="item.image"
                            cover
                        ></v-img>
                        <span
                            v-else
                            class="text-caption font-weight-bold text-white"
                            >{{ item.name?.charAt(0) || "-" }}</span
                        >
                    </v-avatar>
                    <div>
                        <p class="text-body-2 font-weight-bold mb-0">
                            {{ item.name }}
                        </p>
                        <p class="text-caption text-medium-emphasis mb-0">
                            {{ item.code }}
                        </p>
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

            <template #item.status="{ item, column }">
                <BaseBadge
                    type="status"
                    :value="item.status?.code === 1"
                    :align="column.align"
                />
            </template>

            <template #item.joined_at="{ item }">
                <div class="text-body-2">
                    {{ formatDateTime(item.joined_at) }}
                </div>
            </template>

            <template #item.actions="{ item }">
                <div class="member-actions py-2">
                    <v-btn
                        v-tooltip:top="'Jaringan Mitra'"
                        icon="mdi-family-tree"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="info"
                        @click="openGenealogyDialog(item.id)"
                    />
                    <v-btn
                        v-tooltip:top="'Detail Mitra'"
                        icon="mdi-eye-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="primary"
                        @click="openDetailDialog(item.id)"
                    />
                    <v-btn
                        v-tooltip:top="'Edit Mitra'"
                        icon="mdi-pencil-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="warning"
                        @click="openEditDialog(item.id)"
                    />
                    <v-btn
                        v-tooltip:top="'Reset Password'"
                        icon="mdi-lock-reset"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="secondary"
                        @click="openResetPasswordDialog(item)"
                    />
                    <v-btn
                        v-if="item.status?.code === 1"
                        v-tooltip:top="'Nonaktifkan Mitra'"
                        icon="mdi-account-off-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="error"
                        @click="openDeactivationDialog(item)"
                    />
                </div>
            </template>
        </BaseDataTable>

        <MemberDetail ref="memberDetailRef" />
        <GenealogyDialog ref="genealogyDialogRef" />
        <MemberEditDialog ref="memberEditDialogRef" @updated="refreshTable" />

        <v-dialog v-model="deactivationDialog" max-width="620" persistent scrollable>
            <v-card class="rounded-xl">
                <v-card-title class="pa-5 border-b d-flex align-center ga-3">
                    <v-icon color="error">mdi-account-off-outline</v-icon>
                    <span class="text-h6 font-weight-bold">Nonaktifkan Mitra</span>
                </v-card-title>
                <v-card-text class="pa-5" style="max-height: 75vh">
                    <div
                        v-if="deactivationLoading"
                        class="d-flex justify-center py-8"
                    >
                        <v-progress-circular indeterminate color="primary" />
                    </div>
                    <template v-else-if="deactivationOptions">
                        <p class="mb-4">
                            <strong>{{ deactivationOptions.member.name }}</strong>
                            ({{ deactivationOptions.member.code }}) akan langsung
                            dinonaktifkan.
                        </p>

                        <v-row dense class="mb-2">
                            <v-col cols="12" sm="4">
                                <v-sheet class="pa-3 rounded-lg bg-grey-lighten-4">
                                    <div class="text-caption text-medium-emphasis">Jaringan langsung</div>
                                    <strong>{{ deactivationOptions.summary.direct_downline_count }} mitra</strong>
                                </v-sheet>
                            </v-col>
                            <v-col cols="12" sm="4">
                                <v-sheet class="pa-3 rounded-lg bg-grey-lighten-4">
                                    <div class="text-caption text-medium-emphasis">Reward belum dibayar</div>
                                    <strong>{{ deactivationOptions.summary.outstanding_reward_count }} kewajiban</strong>
                                </v-sheet>
                            </v-col>
                            <v-col cols="12" sm="4">
                                <v-sheet class="pa-3 rounded-lg bg-grey-lighten-4">
                                    <div class="text-caption text-medium-emphasis">Transaksi aktif</div>
                                    <strong>{{ deactivationOptions.summary.active_transaction_count }} transaksi</strong>
                                </v-sheet>
                            </v-col>
                        </v-row>

                        <v-alert
                            v-if="!deactivationOptions.can_deactivate"
                            type="warning"
                            variant="tonal"
                            class="mb-4"
                        >
                            Penonaktifan belum dapat dilakukan. Selesaikan
                            {{ deactivationBlockers }} terlebih dahulu.
                        </v-alert>

                        <v-alert
                            v-if="deactivationOptions.requires_transaction_cancellation"
                            type="warning"
                            variant="tonal"
                            class="mb-4"
                        >
                            <div class="mb-2">
                                <strong>{{ deactivationOptions.summary.cancellable_transaction_count }} transaksi aktif</strong>
                                akan dibatalkan dan stok yang masih dicadangkan
                                akan dikembalikan sebelum mitra dinonaktifkan.
                            </div>
                            <v-checkbox
                                v-model="cancelActiveTransactions"
                                color="error"
                                density="compact"
                                hide-details
                                label="Saya memahami dan menyetujui pembatalan transaksi aktif tersebut."
                                :disabled="!deactivationOptions.can_deactivate"
                            />
                        </v-alert>

                        <v-autocomplete
                            v-if="deactivationOptions.requires_replacement_sponsor"
                            v-model="replacementSponsorId"
                            :items="deactivationOptions.results"
                            item-title="label"
                            item-value="id"
                            label="Upline pengganti"
                            hint="Jaringan dan kewajiban reward yang belum dibayar akan dialihkan ke upline ini."
                            persistent-hint
                            class="mb-4"
                            :disabled="!deactivationOptions.can_deactivate"
                        >
                            <template #item="{ props, item }">
                                <v-list-item v-bind="props">
                                    <template #subtitle>
                                        {{ item.raw.level.name }}
                                    </template>
                                </v-list-item>
                            </template>
                        </v-autocomplete>

                        <v-textarea
                            v-model="deactivationNote"
                            label="Catatan (opsional)"
                            rows="2"
                            maxlength="500"
                            counter
                            :disabled="!deactivationOptions.can_deactivate"
                        />
                    </template>
                </v-card-text>
                <v-divider></v-divider>
                <v-card-actions class="pa-5 justify-end">
                    <v-btn
                        variant="text"
                        :disabled="lifecycleSubmitting"
                        @click="deactivationDialog = false"
                    >
                        Batal
                    </v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        :loading="lifecycleSubmitting"
                        :disabled="!canSubmitDeactivation"
                        @click="submitDeactivation"
                    >
                        Nonaktifkan Mitra
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="resetPasswordDialog" max-width="500" persistent scrollable>
            <v-card class="rounded-xl">
                <v-card-title class="pa-5 border-b d-flex align-center ga-3">
                    <v-icon color="secondary">mdi-lock-reset</v-icon>
                    <span class="text-h6 font-weight-bold">Reset Password</span>
                </v-card-title>
                <v-card-text class="pa-5" style="max-height: 75vh">
                    Password <strong>{{ selectedMember?.name }}</strong> akan
                    direset menjadi tanggal lahir dengan format
                    <strong>DDMMYYYY</strong>. Informasi login akan dikirim ke
                    email mitra.
                </v-card-text>
                <v-divider></v-divider>
                <v-card-actions class="pa-5 justify-end">
                    <v-btn
                        variant="text"
                        :disabled="lifecycleSubmitting"
                        @click="resetPasswordDialog = false"
                    >
                        Batal
                    </v-btn>
                    <v-btn
                        color="secondary"
                        variant="flat"
                        :loading="lifecycleSubmitting"
                        @click="submitResetPassword"
                    >
                        Reset & Kirim Email
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import MemberDetail from "@/admin/components/partnership/MemberDetail.vue";
import GenealogyDialog from "@/admin/components/partnership/GenealogyDialog.vue";
import MemberEditDialog from "@/admin/components/partnership/MemberEditDialog.vue";
import memberService from "@/admin/services/member.service";
import type {
    Member,
    MemberDeactivationOptions,
} from "@/admin/types/member";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const { formatDateTime } = useFormatter();
const snackbar = useSnackbarStore();

const search = ref("");
const memberTable = ref<InstanceType<typeof BaseDataTable> | null>(null);
const memberDetailRef = ref<InstanceType<typeof MemberDetail> | null>(null);
const genealogyDialogRef = ref<InstanceType<typeof GenealogyDialog> | null>(null);
const memberEditDialogRef = ref<InstanceType<typeof MemberEditDialog> | null>(null);
const selectedMember = ref<Member | null>(null);
const deactivationDialog = ref(false);
const resetPasswordDialog = ref(false);
const deactivationLoading = ref(false);
const lifecycleSubmitting = ref(false);
const deactivationOptions = ref<MemberDeactivationOptions | null>(null);
const replacementSponsorId = ref<number | null>(null);
const deactivationNote = ref("");
const cancelActiveTransactions = ref(false);

const canSubmitDeactivation = computed(() => {
    if (!deactivationOptions.value?.can_deactivate) return false;
    if (
        deactivationOptions.value.requires_transaction_cancellation &&
        !cancelActiveTransactions.value
    ) {
        return false;
    }
    if (deactivationOptions.value.requires_replacement_sponsor) {
        return replacementSponsorId.value !== null;
    }
    return true;
});

const deactivationBlockers = computed(() => {
    if (!deactivationOptions.value) return "proses yang masih berjalan";
    const blockers: string[] = [];
    const summary = deactivationOptions.value.summary;
    if (summary.blocking_transaction_count > 0) {
        blockers.push(
            `${summary.blocking_transaction_count} transaksi dalam pengiriman atau penerimaan`,
        );
    }
    if (summary.active_return_count > 0) {
        blockers.push(`${summary.active_return_count} retur berjalan`);
    }
    if (summary.has_pending_network_change) {
        blockers.push("perubahan jaringan terjadwal");
    }
    return blockers.join(", ") || "proses yang masih berjalan";
});

const headers = [
    {
        title: "Mitra",
        key: "name",
        align: "start",
        type: "text",
        sortable: false,
        filter: true,
        filterLabel: "Nama Mitra",
        placeholder: "Masukkan Nama Mitra",
    },
    {
        title: "Kode Mitra",
        key: "code",
        align: "start",
        type: "text",
        visible: false,
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Mitra",
    },
    {
        title: "Level",
        key: "level",
        align: "center",
        type: "select",
        sortable: false,
        filter: true,
        options: [
            { value: "distributor", label: "Distributor" },
            { value: "agent", label: "Agent" },
            { value: "reseller", label: "Reseller" },
        ],
        placeholder: "Pilih Level",
    },
    {
        title: "Nomor Telepon",
        key: "mobile_phone",
        align: "start",
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
            { value: 1, label: "Aktif" },
            { value: 0, label: "Tidak Aktif" },
        ],
        placeholder: "Pilih Status",
    },
    {
        title: "Tanggal Bergabung",
        key: "joined_at",
        align: "left",
        type: "date",
        sortable: false,
    },
    {
        title: "Aksi",
        key: "actions",
        align: "center",
        sortable: false,
        width: 236,
    },
];

const openDetailDialog = (id: number) => {
    memberDetailRef.value?.open(id);
};

const openGenealogyDialog = (id: number) => {
    genealogyDialogRef.value?.open(id);
};

const openEditDialog = (id: number) => {
    memberEditDialogRef.value?.open(id);
};

const openDeactivationDialog = async (member: Member) => {
    selectedMember.value = member;
    deactivationDialog.value = true;
    deactivationLoading.value = true;
    deactivationOptions.value = null;
    replacementSponsorId.value = null;
    deactivationNote.value = "";
    cancelActiveTransactions.value = false;

    try {
        deactivationOptions.value =
            await memberService.getDeactivationOptions(member.id);
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message ||
                "Gagal memuat opsi penonaktifan mitra.",
            "error",
        );
        deactivationDialog.value = false;
    } finally {
        deactivationLoading.value = false;
    }
};

const submitDeactivation = async () => {
    if (!selectedMember.value || !canSubmitDeactivation.value) return;
    lifecycleSubmitting.value = true;
    try {
        const response = await memberService.deactivateMember(
            selectedMember.value.id,
            {
                replacement_sponsor_id: replacementSponsorId.value,
                cancel_active_transactions: cancelActiveTransactions.value,
                note: deactivationNote.value || undefined,
            },
        );
        snackbar.showMessage(response.message, "success");
        deactivationDialog.value = false;
        refreshTable();
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Gagal menonaktifkan mitra.",
            "error",
        );
    } finally {
        lifecycleSubmitting.value = false;
    }
};

const openResetPasswordDialog = (member: Member) => {
    selectedMember.value = member;
    resetPasswordDialog.value = true;
};

const submitResetPassword = async () => {
    if (!selectedMember.value) return;
    lifecycleSubmitting.value = true;
    try {
        const response = await memberService.resetPassword(
            selectedMember.value.id,
        );
        snackbar.showMessage(response.message, "success");
        resetPasswordDialog.value = false;
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Gagal mereset password mitra.",
            "error",
        );
    } finally {
        lifecycleSubmitting.value = false;
    }
};

const refreshTable = () => {
    memberTable.value?.refresh();
};
</script>

<style scoped>
.member-actions {
    display: grid;
    grid-template-columns: repeat(5, 36px);
    justify-content: center;
    gap: 8px;
    min-width: 212px;
}

@media (max-width: 767.98px) {
    .member-actions {
        grid-template-columns: repeat(3, 40px);
        min-width: 136px;
    }

    .member-actions :deep(.v-btn) {
        width: 40px;
        height: 40px;
    }

    .member-table :deep(.v-data-table__td) {
        min-height: 92px;
        padding-top: 10px !important;
        padding-bottom: 10px !important;
        vertical-align: middle;
    }

    .member-table :deep(.v-data-table__td:last-child),
    .member-table :deep(.v-data-table__th:last-child) {
        min-width: 164px;
    }
}
</style>
