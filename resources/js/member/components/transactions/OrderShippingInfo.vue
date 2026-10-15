<template>
    <section
        class="order-shipping-info"
        :class="{ card: !embedded, 'order-shipping-info--embedded': embedded }"
    >
        <header class="order-shipping-info__header">
            <h3 class="order-shipping-info__title">
                <v-icon icon="mdi-truck-outline" size="18" />
                Informasi Pengiriman
            </h3>
        </header>
        <div class="order-shipping-info__body">
            <div v-if="method" class="order-shipping-info__row">
                <span>Metode Pengiriman</span>
                <strong>{{ methodLabel }}</strong>
            </div>
            <template v-if="method === 'pickup'">
                <OrderPartyInfo
                    v-if="transit && origin?.name"
                    title="Asal Barang"
                    :party="origin"
                    embedded
                    compact
                />
                <OrderPartyInfo
                    title="Lokasi Pengambilan"
                    :party="pickupLocation"
                    :address="formatLocation(pickupLocation)"
                    embedded
                    compact
                />
            </template>
            <template v-else>
                <OrderPartyInfo
                    v-if="origin"
                    title="Asal Barang"
                    :party="origin"
                    :address="formatLocation(origin)"
                    embedded
                    compact
                />
                <OrderPartyInfo
                    v-if="destination"
                    title="Tujuan"
                    :party="destination"
                    :address="formatLocation(destination)"
                    embedded
                    compact
                />
            </template>
            <div
                v-if="method !== 'pickup' && courierLabel"
                class="order-shipping-info__row"
            >
                <span>Kurir</span>
                <strong>{{ courierLabel }}</strong>
            </div>
            <div
                v-if="shipping?.delivery_note_number"
                class="order-shipping-info__row"
            >
                <span>Nomor Surat Jalan</span>
                <div class="order-shipping-info__value">
                    <strong>{{ shipping.delivery_note_number }}</strong>
                    <slot name="delivery-note-action" />
                </div>
            </div>
            <div
                v-if="shipping?.tracking_number"
                class="order-shipping-info__row"
            >
                <span>Nomor Resi</span>
                <div class="order-shipping-info__value">
                    <strong>{{ shipping.tracking_number }}</strong>
                    <slot name="tracking-number-action" />
                </div>
            </div>
            <slot />
        </div>
    </section>
</template>

<script setup lang="ts">
import { computed } from "vue";
import OrderPartyInfo from "@/member/components/transactions/OrderPartyInfo.vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import type { OrderParty } from "@/member/types/order-party";

const props = defineProps<{
    method?: string | null;
    shipping?: {
        courier?: string | null;
        service?: string | null;
        tracking_number?: string | null;
        delivery_note_number?: string | null;
    } | null;
    origin?: OrderParty | null;
    destination?: OrderParty | null;
    pickupLocation?: OrderParty | null;
    transit?: boolean;
    embedded?: boolean;
}>();

const { formatLocation } = useFormatter();
const methodLabels: Record<string, string> = {
    pickup: "Diambil di Tempat",
    courier_manual: "Kurir",
    courier_express: "Kurir Ekspres",
    courier_instant: "Kurir Instan",
};
const methodLabel = computed(
    () => methodLabels[props.method || ""] || props.method,
);
const courierLabel = computed(() =>
    [
        props.shipping?.courier?.toLocaleUpperCase("id-ID"),
        props.shipping?.service,
    ]
        .map((value) => value?.trim())
        .filter((value) => value && value !== "-")
        .join(" - "),
);
</script>

<style scoped>
.order-shipping-info:not(.order-shipping-info--embedded) {
    margin: 12px 0 16px;
}
.order-shipping-info__header {
    padding: 14px 16px;
    border-bottom: 1px solid var(--line);
}
.order-shipping-info__title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.5;
}
.order-shipping-info__body {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px 16px;
}
.order-shipping-info__row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    font-size: 12px;
    line-height: 1.5;
    color: var(--muted);
}
.order-shipping-info__row strong {
    max-width: 65%;
    font-size: 13px;
    font-weight: 600;
    text-align: right;
    overflow-wrap: anywhere;
    color: var(--text);
}
.order-shipping-info__value {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
    max-width: 65%;
}
.order-shipping-info__value strong {
    max-width: 100%;
}
.order-shipping-info--embedded .order-shipping-info__header {
    padding: 0 0 10px;
    border: 0;
}
.order-shipping-info--embedded .order-shipping-info__body {
    padding: 0;
}
</style>
