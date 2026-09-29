<template>
    <div class="screen-body">
        <div class="welcome-row">
            <h2>{{ addressId ? "Edit Alamat" : "Tambah Alamat" }}</h2>
        </div>

        <AddressForm :address-id="addressId" />
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";
import AddressForm from "@/member/components/profile/AddressForm.vue";
import { decodeRouteId } from "@/shared/utils/route-id";

const route = useRoute();
const addressId = computed(() => {
    const id = Number(decodeRouteId(route.query.id));
    return Number.isInteger(id) && id > 0 ? id : undefined;
});
</script>
