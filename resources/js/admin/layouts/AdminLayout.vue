<template>
    <v-app style="height: 100vh; overflow: hidden">
        <AdminSidebar v-model:drawer="drawer" v-model:rail="rail" />
        <AdminTopbar v-model:drawer="drawer" v-model:rail="rail" />

        <!-- Main Content -->
        <v-main class="d-flex flex-column" style="height: 100vh">
            <div class="flex-grow-1 custom-scrollbar" style="overflow-y: auto">
                <v-container fluid class="pa-6">
                    <router-view />
                </v-container>
            </div>
        </v-main>
    </v-app>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from "vue";
import AdminSidebar from "@/admin/components/AdminSidebar.vue";
import AdminTopbar from "@/admin/components/AdminTopbar.vue";
import { useAuthStore } from "@/shared/stores/auth";

const drawer = ref(true);
const rail = ref(false);
const authStore = useAuthStore();

onMounted(() => {
    authStore.startMenuSync();
    authStore.initializeSession();
});

onUnmounted(() => {
    authStore.stopMenuSync();
    authStore.stopTokenRefreshTimer();
});
</script>

<style>
/* Prevent Vuetify from forcing html scrollbar */
html {
    overflow-y: hidden !important;
}

/* Custom slim scrollbar for sidebar and main content */
.custom-scrollbar::-webkit-scrollbar,
.custom-sidebar .v-navigation-drawer__content::-webkit-scrollbar {
    width: 6px !important;
}
.custom-scrollbar::-webkit-scrollbar-track,
.custom-sidebar .v-navigation-drawer__content::-webkit-scrollbar-track {
    background: transparent !important;
}
.custom-scrollbar::-webkit-scrollbar-thumb,
.custom-sidebar .v-navigation-drawer__content::-webkit-scrollbar-thumb {
    background-color: #c1c1c1 !important;
    border-radius: 10px !important;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover,
.custom-sidebar .v-navigation-drawer__content::-webkit-scrollbar-thumb:hover {
    background-color: #a8a8a8 !important;
}
/* For Firefox */
.custom-scrollbar,
.custom-sidebar .v-navigation-drawer__content {
    scrollbar-width: thin !important;
    scrollbar-color: #c1c1c1 transparent !important;
}

/* Custom slim scrollbar for Data Table */
.v-data-table .v-table__wrapper::-webkit-scrollbar {
    width: 4px !important;
    height: 4px !important;
}
.v-data-table .v-table__wrapper::-webkit-scrollbar-track {
    background: transparent !important;
}
.v-data-table .v-table__wrapper::-webkit-scrollbar-thumb {
    background-color: #c1c1c1 !important;
    border-radius: 10px !important;
}
.v-data-table .v-table__wrapper::-webkit-scrollbar-thumb:hover {
    background-color: #a8a8a8 !important;
}
.v-data-table .v-table__wrapper::-webkit-scrollbar-button {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
}
/* For Firefox */
.v-data-table .v-table__wrapper {
    scrollbar-width: thin !important;
    scrollbar-color: #c1c1c1 transparent !important;
}
</style>
