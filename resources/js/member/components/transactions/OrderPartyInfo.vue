<template>
    <section
        class="order-party-info"
        :class="{
            card: !embedded,
            'order-party-info--embedded': embedded,
            'order-party-info--compact': compact,
        }"
    >
        <header class="order-party-info__header">
            <h3 class="order-party-info__title">
                <v-icon v-if="icon" :icon="icon" size="18" />
                {{ title }}
            </h3>
        </header>
        <div class="order-party-info__body">
            <strong class="order-party-info__name">{{
                party?.name || "-"
            }}</strong>
            <p v-if="party?.code" class="order-party-info__meta">
                {{ party.code }}
            </p>
            <p v-if="contactPhone" class="order-party-info__meta">
                {{ contactPhone }}
            </p>
            <p
                v-if="address && address.trim() !== '-'"
                class="order-party-info__meta"
            >
                {{ address }}
            </p>
            <BaseBadge
                v-if="showLevel && (party?.type || party?.level)"
                type="level"
                size="x-small"
                :value="party?.type || party?.level || undefined"
                inline
            />
        </div>
    </section>
</template>

<script setup lang="ts">
import { computed } from "vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import type { OrderParty } from "@/member/types/order-party";

const props = defineProps<{
    title: string;
    icon?: string;
    party?: OrderParty | null;
    phone?: string | null;
    address?: string | null;
    embedded?: boolean;
    compact?: boolean;
    showLevel?: boolean;
}>();

const contactPhone = computed(() => props.party?.phone || props.phone);
</script>

<style scoped>
.order-party-info:not(.order-party-info--embedded) {
    margin: 12px 0 16px;
}
.order-party-info__header {
    padding: 14px 16px;
    border-bottom: 1px solid var(--line);
}
.order-party-info__title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.5;
    color: var(--text);
}
.order-party-info__body {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    padding: 14px 16px;
}
.order-party-info__name {
    font-size: 13px;
    font-weight: 600;
    line-height: 1.5;
    color: var(--text);
}
.order-party-info__meta {
    margin: 0;
    font-size: 12px;
    line-height: 1.5;
    color: var(--muted);
    overflow-wrap: anywhere;
}
.order-party-info--embedded .order-party-info__header {
    padding: 0 0 10px;
    border: 0;
}
.order-party-info--embedded .order-party-info__body {
    padding: 0;
}
.order-party-info--compact .order-party-info__header {
    padding-bottom: 4px;
}
.order-party-info--compact .order-party-info__title {
    font-size: 12px;
    font-weight: 400;
    color: var(--muted);
}
</style>
