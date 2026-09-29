<template>
    <div
        v-if="preorder"
        class="card border border-[#f2d4aa] bg-[#fffaf4]"
        style="margin-top: 20px; margin-bottom: 20px; padding: 20px"
    >
        <div class="flex items-start gap-3 rounded-xl bg-white p-4">
            <v-icon
                icon="mdi-source-branch"
                color="#ef8a00"
                size="22"
                class="mt-0.5"
            />
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="m-0 text-sm font-bold text-[#b86600]">
                        Alur Pesanan PO
                    </h3>
                    <span
                        class="rounded-full bg-[#fff0d8] px-2 py-1 text-[10px] font-semibold text-[#a85c00]"
                    >
                        {{ preorder.chain.length }} tahap
                    </span>
                </div>
                <p class="m-0 mt-2 text-[12px] text-[var(--muted)]">
                    Pemesan awal
                </p>
                <p class="m-0 mt-0.5 text-[13px] text-[var(--ink)]">
                    <strong>
                        {{ preorder.origin.buyer.name || "-" }}
                        <template v-if="preorder.origin.buyer.code">
                            ({{ preorder.origin.buyer.code }})
                        </template>
                    </strong>
                </p>
                <p class="m-0 mt-1 break-all text-[11px] text-[var(--muted)]">
                    {{ preorder.origin.transaction.code }}
                </p>
            </div>
        </div>

        <div class="flex flex-col" style="gap: 16px; margin-top: 20px">
            <div class="h-px bg-[#ead7bd]" aria-hidden="true"></div>
            <template
                v-for="(step, stepIndex) in preorder.chain"
                :key="
                    step.transaction.id ??
                    `${step.sequence}-${step.buyer.id}-${step.seller.id}`
                "
            >
                <div
                    v-if="stepIndex > 0"
                    class="h-px bg-[#ead7bd]"
                    aria-hidden="true"
                ></div>
                <div
                    class="rounded-xl bg-white p-4 shadow-[0_4px_16px_rgba(122,73,25,0.06)]"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span
                                class="flex h-6 w-6 items-center justify-center rounded-full bg-[#fff0d8] text-[11px] font-bold text-[#b86600]"
                            >
                                {{ step.sequence }}
                            </span>
                            <span class="text-[11px] font-bold text-[#8c5a24]">
                                Tahap {{ step.sequence }}
                            </span>
                        </div>
                        <span
                            v-if="
                                step.transaction.id ===
                                preorder.current_member_transaction_id
                            "
                            class="rounded-full bg-[var(--primary)] px-2 py-1 text-[10px] font-semibold text-white"
                        >
                            Pesanan untuk Anda
                        </span>
                    </div>
                    <div
                        class="grid grid-cols-[1fr_auto_1fr] items-center gap-3"
                        style="margin-top: 16px"
                    >
                        <div class="min-w-0">
                            <span class="block text-[10px] text-[var(--muted)]">
                                Pembeli
                            </span>
                            <strong
                                class="block truncate text-[13px] text-[var(--ink)]"
                                >{{ step.buyer.name || "-" }}</strong
                            >
                            <div
                                class="flex items-center gap-1 mt-1"
                                :class="{ 'flex-wrap': step.buyer.code }"
                            >
                                <span
                                    v-if="step.buyer.code"
                                    class="text-[11px] text-[var(--muted)]"
                                >
                                    {{ step.buyer.code }}
                                </span>
                                <BaseBadge
                                    type="level"
                                    :value="step.buyer.type"
                                    inline
                                />
                            </div>
                        </div>
                        <v-icon
                            icon="mdi-arrow-right-thin"
                            color="#ef8a00"
                            size="22"
                        />
                        <div class="min-w-0 text-right">
                            <span class="block text-[10px] text-[var(--muted)]">
                                Penjual
                            </span>
                            <strong
                                class="block truncate text-[13px] text-[var(--ink)]"
                                >{{ step.seller.name || "-" }}</strong
                            >
                            <div
                                class="flex items-center gap-1 mt-1 justify-end"
                                :class="{ 'flex-wrap': step.seller.code }"
                            >
                                <span
                                    v-if="step.seller.code"
                                    class="text-[11px] text-[var(--muted)]"
                                >
                                    {{ step.seller.code }}
                                </span>
                                <BaseBadge
                                    type="level"
                                    :value="step.seller.type"
                                    inline
                                />
                            </div>
                        </div>
                    </div>
                    <p
                        v-if="step.transaction.code"
                        class="m-0 break-all text-[11px] text-[var(--muted)]"
                        style="margin-top: 16px"
                    >
                        {{ step.transaction.code }}
                    </p>
                    <div
                        class="rounded-lg px-3 py-3"
                        :class="stepStatusClass(step.transaction.status)"
                        style="margin-top: 16px"
                    >
                        <span class="block text-[10px] opacity-75">
                            Status
                        </span>
                        <strong class="text-[11px]">
                            {{ step.transaction.status_label }}
                        </strong>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { SalePreorder } from "@/member/types/sale";
import BaseBadge from "@/shared/components/BaseBadge.vue";

defineProps<{
    preorder: SalePreorder | null | undefined;
}>();

const stepStatusClass = (status: string) => {
    if (["completed", "received"].includes(status)) {
        return "bg-[#e5f7ee] text-[#147a4f]";
    }

    if (["cancelled", "rejected"].includes(status)) {
        return "bg-[#fde8e8] text-[#b42318]";
    }

    if (["processing", "shipped", "ready_to_pickup"].includes(status)) {
        return "bg-[#eef3ff] text-[#3159a6]";
    }

    return "bg-[#fff7e9] text-[#b96500]";
};
</script>
