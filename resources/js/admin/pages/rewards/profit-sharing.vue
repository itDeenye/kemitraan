<template>
    <div class="mb-6 d-flex flex-column">
        <h1 class="text-h4 font-weight-bold mb-1">Sharing Profit</h1>
        <p class="text-body-2 text-medium-emphasis">
            Halaman untuk memantau dan mentransfer pembagian sharing profit
            kepada mitra.
        </p>
    </div>

    <BaseDataTable
        ref="mainTable"
        url="/admin/rewards/sharing-profits"
        :headers="headers"
        :multiple-select="true"
        v-model:selected="selectedMitras"
        :return-object="true"
    >
        <template #header-actions>
            <div class="d-flex ga-2">
                <v-btn
                    color="success"
                    variant="outlined"
                    prepend-icon="mdi-check-circle-outline"
                    class="bg-surface rounded-lg text-none"
                    :disabled="!approvableMitras.length"
                    @click="openBulkApprove"
                >
                    Approve
                </v-btn>
                <v-btn
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-bank-transfer"
                    class="bg-surface rounded-lg text-none"
                    :disabled="!transferableMitras.length"
                    @click="openBulkTransfer"
                >
                    Transfer
                </v-btn>
            </div>
        </template>

        <template #item.upline.name="{ item }">
            <div class="d-flex flex-column py-2">
                <div class="font-weight-medium text-body-2">
                    {{ item.upline?.name || "-" }}
                </div>
                <div class="text-caption text-medium-emphasis">
                    {{ item.upline?.code || "-" }}
                </div>
            </div>
        </template>

        <template #item.total_trx_price="{ item }">
            {{ formatPrice(item.total_trx_price) }}
        </template>

        <template #item.total_amount="{ item }">
            <span class="font-weight-bold">{{
                formatPrice(item.total_amount)
            }}</span>
        </template>

        <template #item.status="{ item }">
            <BaseBadge
                type="sharing_reward_status"
                :value="item.status"
                inline
            />
        </template>

        <template #item.actions="{ item }">
            <div class="d-flex ga-2 justify-center">
                <v-btn
                    v-if="item.can_approve"
                    v-tooltip:top="'Approve Bukti'"
                    icon="mdi-check-circle-outline"
                    variant="outlined"
                    size="small"
                    class="rounded"
                    color="success"
                    @click="openRowApprove(item)"
                />
                <v-btn
                    v-if="item.can_transfer"
                    v-tooltip:top="'Transfer Sharing Profit'"
                    icon="mdi-bank-transfer"
                    variant="outlined"
                    size="small"
                    class="rounded"
                    color="primary"
                    @click="openRowTransfer(item)"
                />
                <v-btn
                    v-tooltip:top="'Lihat Bukti dan Detail'"
                    icon="mdi-eye-outline"
                    variant="outlined"
                    size="small"
                    class="rounded"
                    color="primary"
                    @click="viewDetail(item)"
                />
            </div>
        </template>
    </BaseDataTable>

    <BaseConfirm ref="confirmDialog" />

    <SharingProfitMitraDialog ref="detailDialog" @refresh="refreshTable" />

    <v-dialog v-model="isTransferPromptOpen" max-width="400">
        <v-card class="rounded-xl">
            <v-card-title class="pa-4 pb-2 font-weight-bold">
                {{ transferDialogTitle }}
            </v-card-title>
            <v-card-text class="pa-4">
                <p class="text-body-2 text-medium-emphasis mb-4">
                    Masukkan catatan transfer untuk
                    <strong>{{ transferTargets.length }}</strong> mitra yang
                    dipilih.
                </p>
                <v-textarea
                    v-model="transferNote"
                    label="Catatan Transfer"
                    variant="outlined"
                    rows="3"
                    hide-details
                ></v-textarea>
            </v-card-text>
            <v-card-actions class="pa-4 pt-0 justify-end">
                <v-btn
                    variant="text"
                    color="medium-emphasis"
                    @click="isTransferPromptOpen = false"
                    >Batal</v-btn
                >
                <v-btn
                    color="primary"
                    variant="flat"
                    @click="confirmBulkTransfer"
                    :loading="isSubmitting"
                    :disabled="!transferNote"
                    >Proses Transfer</v-btn
                >
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import type { SharingProfitMitra } from "@/admin/types/sharing-profit";
import sharingProfitService from "@/admin/services/sharing-profit.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import BaseConfirm from "@/shared/components/BaseConfirm.vue";
import SharingProfitMitraDialog from "@/admin/components/rewards/SharingProfitMitraDialog.vue";

const { formatPrice } = useFormatter();
const snackbar = useSnackbarStore();

const mainTable = ref<InstanceType<typeof BaseDataTable> | null>(null);
const confirmDialog = ref<InstanceType<typeof BaseConfirm> | null>(null);
const detailDialog = ref<InstanceType<typeof SharingProfitMitraDialog> | null>(
    null,
);

const selectedMitras = ref<SharingProfitMitra[]>([]);
const transferTargets = ref<SharingProfitMitra[]>([]);
const isTransferPromptOpen = ref(false);
const transferNote = ref("");
const isSubmitting = ref(false);

const approvableMitras = computed(() =>
    selectedMitras.value.filter((mitra) => mitra.can_approve),
);
const transferableMitras = computed(() =>
    selectedMitras.value.filter((mitra) => mitra.can_transfer),
);
const transferDialogTitle = computed(() =>
    transferTargets.value.length === 1
        ? "Transfer Sharing Profit"
        : "Transfer Sharing Profit Terpilih",
);

const headers = [
    {
        title: "Mitra",
        key: "upline.name",
        type: "text",
        align: "start",
        sortable: false,
        filter: true,
        filterLabel: "Nama Mitra",
        placeholder: "Masukkan Nama Mitra",
    },
    {
        title: "Kode Mitra",
        key: "upline.code",
        type: "text",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Mitra",
    },
    {
        title: "Total Nilai Transaksi (Rp)",
        key: "total_trx_price",
        align: "right",
        sortable: false,
    },
    {
        title: "Total Komisi (Rp)",
        key: "total_amount",
        align: "right",
        sortable: false,
    },
    {
        title: "Status",
        key: "status",
        align: "center",
        sortable: false,
    },
    {
        title: "Aksi",
        key: "actions",
        align: "center",
        sortable: false,
    },
];

const refreshTable = () => {
    mainTable.value?.refresh();
    selectedMitras.value = [];
    transferTargets.value = [];
};

const viewDetail = (item: SharingProfitMitra) => {
    detailDialog.value?.open(item);
};

const openBulkApprove = async () => {
    await openApprove(approvableMitras.value);
};

const openRowApprove = async (item: SharingProfitMitra) => {
    await openApprove([item]);
};

const openApprove = async (mitras: SharingProfitMitra[]) => {
    if (!mitras.length || !confirmDialog.value) return;

    const count = mitras.length;
    const confirmed = await confirmDialog.value.open({
        title: count === 1 ? "Setujui Bukti" : "Setujui Bukti Terpilih",
        message: `Setujui bukti spread payment untuk ${count} mitra? Setelah disetujui, tombol Transfer akan tersedia.`,
        confirmColor: "success",
        confirmText: "Approve",
    });

    if (confirmed) {
        await processApprove(mitras);
    }
};

const processApprove = async (mitras: SharingProfitMitra[]) => {
    try {
        const uplineIds = mitras.map((mitra) => mitra.upline.id);
        const processed = await sharingProfitService.approve(uplineIds);
        snackbar.showMessage(
            `${processed} bukti spread payment berhasil di-approve.`,
            "success",
        );
        refreshTable();
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Terjadi kesalahan saat approve",
            "error",
        );
    }
};

const openBulkTransfer = () => {
    openTransfer(transferableMitras.value);
};

const openRowTransfer = (item: SharingProfitMitra) => {
    openTransfer([item]);
};

const openTransfer = (mitras: SharingProfitMitra[]) => {
    if (!mitras.length) return;
    transferTargets.value = mitras;
    transferNote.value = "";
    isTransferPromptOpen.value = true;
};

const confirmBulkTransfer = async () => {
    if (!transferTargets.value.length || !transferNote.value) return;
    isSubmitting.value = true;
    try {
        const uplineIds = transferTargets.value.map((mitra) => mitra.upline.id);
        await sharingProfitService.transfer(uplineIds, transferNote.value);
        snackbar.showMessage(
            `Transfer sharing profit ${transferTargets.value.length} mitra berhasil dicatat.`,
            "success",
        );
        isTransferPromptOpen.value = false;
        refreshTable();
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Terjadi kesalahan saat transfer",
            "error",
        );
    } finally {
        isSubmitting.value = false;
    }
};
</script>
