<template>
    <OrderPartyInfo
        title="Informasi Penjual"
        icon="mdi-storefront-outline"
        :party="seller"
        :phone="phone"
        :address="address"
        :embedded="embedded"
        show-level
    />
</template>

<script setup lang="ts">
import OrderPartyInfo from "@/member/components/transactions/OrderPartyInfo.vue";
import type { OrderParty } from "@/member/types/order-party";

defineProps<{
    seller?: OrderParty | null;
    phone?: string | null;
    address?: string | null;
    embedded?: boolean;
}>();
</script>
