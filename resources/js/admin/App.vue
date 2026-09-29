<template>
    <v-app>
        <router-view />
        <GlobalSnackbar />
    </v-app>
</template>

<script setup lang="ts">
import GlobalSnackbar from '@/shared/components/GlobalSnackbar.vue';
</script>
