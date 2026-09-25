<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-4 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">
                    Verifikasi Pembayaran
                </h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk melakukan verifikasi bukti transfer pembayaran
                    dari mitra.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="dataTable"
            url="/admin/transactions/payments"
            :headers="headers"
            :query-params="queryParams"
        >
            <template #item.transaction.code="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-bold">
                        {{ item.transaction?.code }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ formatDateTime(item.transferred_at) }}
                    </div>
                </div>
            </template>
            <template #item.transaction.order_type.code="{ item }">
                <v-chip
                    :color="
                        item.transaction?.order_type?.code === 'preorder'
                            ? 'warning'
                            : 'info'
                    "
                    size="small"
                    variant="tonal"
                    class="font-weight-medium"
                >
                    {{ item.transaction?.order_type?.label || "Reguler" }}
                </v-chip>
            </template>
            <template #item.buyer.name="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="font-weight-medium text-body-2">
                        {{ item.buyer?.name }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ item.buyer?.code || "-" }}
                    </div>
                    <BaseBadge
                        type="level"
                        :value="item.buyer?.type"
                        class="mt-1"
                        size="x-small"
                    />
                </div>
            </template>
            <template #item.account="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-medium">
                        {{ item.account_name || "-" }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ item.account_number || "-" }}
                    </div>
                </div>
            </template>
            <template #item.bill_amount="{ item }">
                <div class="py-2 text-right text-body-2 font-weight-medium">
                    {{ formatPrice(item.bill_amount) }}
                </div>
            </template>
            <template #item.status="{ item, column }">
                <BaseBadge
                    type="transaction_status"
                    :value="item.transaction?.status"
                    :align="column.align"
                />
            </template>
            <template #item.actions="{ item }">
                <div class="d-flex ga-2 justify-center">
                    <v-btn
                        v-if="['approved', 'rejected'].includes(item.status)"
                        v-tooltip:top="'Detail Pembayaran'"
                        icon="mdi-eye-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="primary"
                        @click="openDetail(item.id)"
                    />
                    <v-btn
                        v-else
                        v-tooltip:top="'Verifikasi Pembayaran'"
                        icon="mdi-clipboard-check-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="info"
                        @click="openDetail(item.id)"
                    />
                </div>
            </template>
        </BaseDataTable>

        <PaymentVerificationDialog
            ref="detailDialogRef"
            @updated="refreshData"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import PaymentVerificationDialog from "@/admin/components/transactions/PaymentVerificationDialog.vue";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime, formatPrice } = useFormatter();

const dataTable = ref<InstanceType<typeof BaseDataTable> | null>(null);
const detailDialogRef = ref<InstanceType<
    typeof PaymentVerificationDialog
> | null>(null);

const queryParams = computed(() => ({
    "filter[buyer.type]": "distributor",
    "filter[transaction.status][eq]": "waiting_payment_approval",
}));

const headers = [
    {
        title: "Kode Transaksi",
        key: "transaction.code",
        type: "text",
        align: "start",
        sortable: false,
        search: true,
        placeholder: "Cari Kode Transaksi",
    },
    {
        title: "Waktu Transfer",
        key: "transferred_at",
        type: "date",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
    },
    {
        title: "Tipe Pesanan",
        key: "transaction.order_type.code",
        filterKey: "is_preorder",
        align: "center",
        type: "select",
        sortable: false,
        filter: true,
        options: [
            { label: "Reguler", value: 0 },
            { label: "Pre-Order", value: 1 },
        ],
        placeholder: "Pilih Tipe",
    },
    {
        title: "Mitra",
        key: "buyer.name",
        type: "text",
        align: "start",
        sortable: false,
        filter: true,
        filterLabel: "Nama Mitra",
        placeholder: "Masukkan Nama Mitra",
    },
    {
        title: "Kode Mitra",
        key: "buyer.code",
        type: "text",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Mitra",
    },
    {
        title: "Rekening",
        key: "account",
        align: "start",
        sortable: false,
    },
    {
        title: "Nominal (Rp)",
        key: "bill_amount",
        align: "end",
        sortable: false,
    },
    {
        title: "Status",
        key: "status",
        type: "select",
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

const refreshData = () => {
    dataTable.value?.refresh();
};

const openDetail = (id: string | number) => {
    detailDialogRef.value?.open(id);
};
</script>

<style scoped>
.gap-2 {
    gap: 8px;
}
</style>
