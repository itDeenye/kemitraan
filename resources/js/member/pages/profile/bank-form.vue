<template>
    <div class="screen-body">
        <div class="welcome-row">
            <h2>{{ bankId ? "Edit Rekening" : "Tambah Rekening" }}</h2>
        </div>

        <BankForm :bank-id="bankId" />
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";
import BankForm from "@/member/components/profile/BankForm.vue";
import { decodeRouteId } from "@/shared/utils/route-id";

const route = useRoute();
const bankId = computed(() => {
    const id = Number(decodeRouteId(route.query.id));
    return Number.isInteger(id) && id > 0 ? id : undefined;
});
</script>
