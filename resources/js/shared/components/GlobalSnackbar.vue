<template>
    <v-snackbar
        v-model="snackbar.show"
        :color="snackbar.color"
        :timeout="snackbar.timeout"
        location="top right"
        absolute
        :class="{ 'member-snackbar': isMemberPanel }"
        variant="elevated"
        elevation="10"
        rounded="lg"
    >
        <div class="d-flex align-center font-weight-medium">
            <v-icon
                :icon="snackbar.color === 'error' ? 'mdi-alert-circle' : 'mdi-check-circle'"
                class="mr-3"
            ></v-icon>
            {{ snackbar.text }}
        </div>
        <template v-slot:actions>
            <v-btn
                variant="text"
                @click="snackbar.show = false"
                icon="mdi-close"
                size="small"
                color="white"
            ></v-btn>
        </template>
    </v-snackbar>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useSnackbarStore } from '@/shared/stores/snackbar';

const snackbar = useSnackbarStore();

const isMemberPanel = computed(() => {
    if (typeof window !== 'undefined') {
        return window.location.pathname.startsWith('/member');
    }
    return false;
});
</script>

<style>
@media (min-width: 768px) {
    .v-snackbar.member-snackbar {
        right: calc(50vw - 384px) !important;
    }
}
</style>
