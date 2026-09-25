<template>
    <div class="mobile-app-wrapper">
        <div class="mobile-app-container">
            <MemberTopbar />

            <!-- Main Content -->
            <router-view />

            <MemberBottomNav />
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted } from "vue";
import MemberTopbar from "@/member/components/MemberTopbar.vue";
import MemberBottomNav from "@/member/components/MemberBottomNav.vue";
import { useViewportHeight } from "@/member/composables/useViewportHeight";
import { useAuthStore } from "@/shared/stores/auth";

const authStore = useAuthStore();

// dynamic viewport height tracking (dvh with JS fallback)
useViewportHeight();

onMounted(() => {
    if (authStore.token) {
        authStore.fetchUser();
    }
    authStore.initializeSession();
});

onUnmounted(() => {
    authStore.stopTokenRefreshTimer();
});
</script>
