<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-6 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">
                    Manajemen Rekening
                </h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk mengelola data rekening bank yang digunakan
                    oleh perusahaan.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="dataTableRef"
            title="Daftar Bank"
            url="/admin/company/banks"
            :headers="headers"
        >
            <template #header-actions>
                <v-btn
                    color="primary"
                    prepend-icon="mdi-bank-plus"
                    variant="outlined"
                    class="bg-surface rounded-lg"
                    @click="openForm()"
                    >Tambah Bank</v-btn
                >
            </template>

            <template v-slot:item.bank_id="{ item }">
                <span class="font-weight-medium">{{ item.bank?.name }}</span>
            </template>

            <template v-slot:item.type="{ item }">
                <BaseBadge type="company_bank_type" :value="item.type" />
            </template>

            <template v-slot:item.account_number="{ item }">
                <span class="font-family-monospace text-body-2">{{
                    item.account_number
                }}</span>
            </template>

            <template v-slot:item.is_active="{ item }">
                <BaseBadge type="status" :value="item.is_active" />
            </template>

            <template v-slot:item.actions="{ item }">
                <div class="d-flex ga-2">
                    <v-btn
                        v-tooltip:top="'Edit Bank'"
                        icon="mdi-pencil-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="info"
                        @click="openForm(item)"
                    ></v-btn>
                    <v-btn
                        v-tooltip:top="'Hapus Bank'"
                        icon="mdi-trash-can-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="error"
                        @click="deleteBank(item)"
                    ></v-btn>
                </div>
            </template>
        </BaseDataTable>

        <BankForm ref="bankFormRef" @saved="refreshTable" />
        <BaseConfirm ref="confirmDialogRef" />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import BankForm from "@/admin/components/company/BankForm.vue";
import BaseConfirm from "@/shared/components/BaseConfirm.vue";
import bankService from "@/admin/services/bank.service";
import referenceService from "@/shared/services/reference.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import type { CompanyBank } from "@/admin/types/bank";

const snackbar = useSnackbarStore();

const dataTableRef = ref<InstanceType<typeof BaseDataTable> | null>(null);
const bankFormRef = ref<InstanceType<typeof BankForm> | null>(null);
const confirmDialogRef = ref<InstanceType<typeof BaseConfirm> | null>(null);

const headers = ref<any[]>([
    {
        title: "Nama Bank",
        key: "bank_id",
        type: "select",
        align: "start",
        sortable: false,
        filter: true,
        filterLabel: "Nama Bank",
        placeholder: "Pilih Bank",
        options: [],
    },
    {
        title: "Tipe Rekening",
        key: "type",
        type: "select",
        align: "center",
        sortable: false,
        filter: true,
        options: [
            { label: "Perusahaan", value: "company" },
            { label: "Kemitraan", value: "spread_payment" },
        ],
        placeholder: "Pilih Tipe",
    },
    {
        title: "Nomor Rekening",
        key: "account_number",
        type: "text",
        align: "left",
        search: true,
        sortable: false,
        filter: true,
        placeholder: "Masukkan Nomor Rekening",
    },
    {
        title: "Nama Pemilik",
        key: "account_name",
        type: "text",
        align: "left",
        search: true,
        sortable: false,
        filter: true,
        placeholder: "Masukkan Nama Pemilik Rekening",
    },
    {
        title: "Status",
        key: "is_active",
        type: "select",
        align: "center",
        sortable: false,
        filter: true,
        placeholder: "Pilih Status",
        options: [
            { label: "Aktif", value: 1 },
            { label: "Nonaktif", value: 0 },
        ],
    },
    {
        title: "Aksi",
        key: "actions",
        type: "actions",
        align: "center",
        sortable: false,
    },
]);

onMounted(async () => {
    try {
        const banks = await referenceService.getBanks();
        const bankCol = headers.value.find((h) => h.key === "bank_id");
        if (bankCol) {
            bankCol.options = banks.map((b: any) => ({
                label: b.name,
                value: b.id,
            }));
        }
    } catch (error) {
        console.error("Gagal memuat filter bank", error);
    }
});

const openForm = (bank?: CompanyBank) => {
    bankFormRef.value?.open(bank);
};

const refreshTable = () => {
    dataTableRef.value?.refresh();
};

const deleteBank = async (bank: CompanyBank) => {
    if (!confirmDialogRef.value) return;

    const isConfirmed = await confirmDialogRef.value.open({
        title: "Hapus Bank",
        message: `Apakah Anda yakin ingin menghapus rekening bank "${bank.bank?.name} - ${bank.account_number}"? Tindakan ini tidak dapat dibatalkan.`,
        confirmText: "Ya, Hapus",
        confirmColor: "error",
        icon: "mdi-delete-alert",
    });

    if (isConfirmed) {
        try {
            await bankService.deleteBank(bank.id);
            snackbar.showMessage("Data bank berhasil dihapus", "success");
            refreshTable();
        } catch (error: any) {
            const message =
                error.response?.data?.message || "Gagal menghapus data bank";
            snackbar.showMessage(message, "error");
        }
    }
};
</script>
