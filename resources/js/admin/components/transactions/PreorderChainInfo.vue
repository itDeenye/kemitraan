<template>
    <v-card
        v-if="preorder"
        variant="tonal"
        color="warning"
        class="rounded-lg pa-4"
    >
        <div class="d-flex align-start ga-3 mb-4">
            <v-icon size="22">mdi-source-branch</v-icon>
            <div>
                <div class="text-subtitle-2 font-weight-bold">
                    Asal Pesanan PO
                </div>
                <div class="text-body-2">
                    Pemesan asal:
                    <strong>{{ preorder.origin.buyer.name || "-" }}</strong>
                    <template v-if="preorder.origin.buyer.code">
                        ({{ preorder.origin.buyer.code }})
                    </template>
                </div>
                <div class="text-caption text-medium-emphasis font-weight-bold">
                    {{ preorder.origin.transaction.code }}
                </div>
            </div>
        </div>

        <div class="d-flex flex-column ga-2">
            <div
                v-for="step in preorder.chain"
                :key="step.transaction.id"
                class="preorder-step rounded-lg pa-3"
            >
                <div
                    class="d-flex align-center justify-space-between ga-2 mb-2"
                >
                    <span class="text-caption font-weight-bold">
                        Tahap {{ step.sequence }}
                    </span>
                    <v-chip
                        v-if="
                            step.transaction.id ===
                            preorder.current_transaction_id
                        "
                        color="primary"
                        size="x-small"
                        variant="flat"
                    >
                        Transaksi ini
                    </v-chip>
                </div>
                <div
                    class="d-flex align-center flex-wrap ga-2 text-body-2 mb-1"
                >
                    <div class="d-flex align-center ga-2">
                        <strong>{{ step.buyer.name || "-" }}</strong>
                        <BaseBadge
                            type="level"
                            :value="step.buyer.type"
                            size="x-small"
                            inline
                        />
                    </div>
                    <v-icon size="18">mdi-arrow-right</v-icon>
                    <div class="d-flex align-center ga-2">
                        <strong>{{ step.seller.name || "-" }}</strong>
                        <BaseBadge
                            type="level"
                            :value="step.seller.type"
                            size="x-small"
                            inline
                        />
                    </div>
                </div>

                <div
                    class="text-caption text-medium-emphasis font-weight-bold mb-2"
                >
                    {{ step.transaction.code }}
                </div>

                <div>
                    <BaseBadge
                        type="transaction_status"
                        :value="step.transaction.status"
                        size="x-small"
                        inline
                    >
                        {{ stepStatusLabel(step.transaction) }}
                    </BaseBadge>
                </div>
            </div>
        </div>
    </v-card>
</template>

<script setup lang="ts">
import type {
    TransactionPreorder,
    TransactionPreorderTransaction,
} from "@/admin/types/transaction-order";
import BaseBadge from "@/shared/components/BaseBadge.vue";

defineProps<{
    preorder?: TransactionPreorder | null;
}>();

const stepStatusLabel = (transaction: TransactionPreorderTransaction): string => {
    if (
        transaction.status === "waiting_payment" &&
        transaction.payment_status === "rejected"
    ) {
        return "Menunggu Bukti Pembayaran Baru";
    }

    return transaction.status_label;
};
</script>

<style scoped>
.preorder-step {
    background: rgb(var(--v-theme-surface));
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
