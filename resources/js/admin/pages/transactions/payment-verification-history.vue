<template>
    <div>
        <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-4">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Riwayat Verifikasi Pembayaran</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Riwayat pembayaran mitra yang telah disetujui atau ditolak untuk pemeriksaan finance.
                </p>
            </div>
        </div>

        <BaseDataTable url="/admin/transactions/payment-history" :headers="headers">
            <template #item.transaction.code="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-bold">{{ item.transaction?.code }}</div>
                    <div class="text-caption text-medium-emphasis">{{ formatDateTime(item.transferred_at) }}</div>
                </div>
            </template>
            <template #item.transaction.order_type.code="{ item }">
                <BaseBadge type="transaction_type" :value="item.transaction?.order_type?.code === 'preorder'" inline />
            </template>
            <template #item.buyer.name="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-medium">{{ item.buyer?.name || "-" }}</div>
                    <div class="text-caption text-medium-emphasis">{{ item.buyer?.code || "-" }}</div>
                    <BaseBadge type="level" :value="item.buyer?.type" class="mt-1" size="x-small" inline />
                </div>
            </template>
            <template #item.account="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-medium">{{ item.payment?.bank?.name || "-" }}</div>
                    <div class="text-caption text-medium-emphasis">{{ item.account_name || "-" }}</div>
                    <div class="text-caption text-medium-emphasis">{{ item.account_number || "-" }}</div>
                </div>
            </template>
            <template #item.amount="{ item }">{{ formatPrice(item.amount) }}</template>
            <template #item.status="{ item }">
                <BaseBadge type="payment_status" :value="item.status" />
            </template>
            <template #item.payment.approved_at="{ item }">
                {{ formatDateTime(item.payment?.approved_at) }}
            </template>
            <template #item.detail="{ item }">
                <div class="d-flex ga-2 justify-center">
                    <v-btn
                        v-tooltip:top="'Lihat Detail Pembayaran'"
                        aria-label="Lihat detail pembayaran"
                        icon="mdi-eye-outline"
                        variant="outlined"
                        size="small"
                        class="rounded flex-shrink-0"
                        color="primary"
                        @click="detailDialogRef?.open(item.id)"
                    />
                </div>
            </template>
        </BaseDataTable>
        <PaymentVerificationDialog ref="detailDialogRef" read-only />
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import PaymentVerificationDialog from "@/admin/components/transactions/PaymentVerificationDialog.vue";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime, formatPrice } = useFormatter();
const detailDialogRef = ref<InstanceType<typeof PaymentVerificationDialog> | null>(null);
const headers = [
    { title: "Kode Transaksi", key: "transaction.code", type: "text", align: "start", sortable: false, search: true, placeholder: "Cari Kode Transaksi" },
    { title: "Waktu Transfer", key: "transferred_at", type: "date", align: "start", visible: false, sortable: false, filter: true },
    { title: "Tipe Pesanan", key: "transaction.order_type.code", filterKey: "is_preorder", type: "select", align: "center", sortable: false, filter: true,
        options: [{ label: "Reguler", value: 0 }, { label: "Pre-Order", value: 1 }] },
    { title: "Mitra", key: "buyer.name", type: "text", align: "start", sortable: false, filter: true, placeholder: "Masukkan Nama Mitra" },
    { title: "Kode Mitra", key: "buyer.code", type: "text", align: "start", visible: false, sortable: false, filter: true },
    { title: "Rekening", key: "account", align: "start", sortable: false },
    { title: "Transfer (Rp)", key: "amount", align: "end", sortable: false },
    { title: "Hasil Verifikasi", key: "status", type: "select", align: "center", sortable: false, filter: true,
        options: [{ label: "Disetujui", value: "approved" }, { label: "Ditolak", value: "rejected" }] },
    { title: "Waktu Verifikasi", key: "payment.approved_at", filterKey: "approved_at", type: "date", align: "start", sortable: false, filter: true },
    { title: "Detail", key: "detail", align: "center", sortable: false },
];
</script>
