<template>
    <v-app-bar elevation="0" color="surface" border="b">
        <!-- Mobile Toggle -->
        <v-app-bar-nav-icon class="d-md-none" @click="drawer = !drawer" />
        <!-- Desktop Toggle -->
        <v-app-bar-nav-icon class="d-none d-md-flex" @click="rail = !rail">
            <v-icon>{{ rail ? "mdi-menu" : "mdi-menu-open" }}</v-icon>
        </v-app-bar-nav-icon>

        <v-divider
            vertical
            class="mx-3 my-auto d-none d-md-block"
            style="height: 24px; max-height: 24px; opacity: 0.15"
        ></v-divider>

        <v-app-bar-title class="text-body-1 font-weight-medium py-2">
            <v-breadcrumbs :items="breadcrumbs" class="pa-0">
                <template #divider>
                    <v-icon
                        icon="mdi-chevron-right"
                        size="small"
                        color="grey-lighten-1"
                    ></v-icon>
                </template>
                <template #item="{ item, index }">
                    <span
                        :class="[
                            'text-body-2',
                            index === breadcrumbs.length - 1
                                ? 'font-weight-bold text-primary'
                                : 'text-medium-emphasis',
                        ]"
                    >
                        {{ item.title }}
                    </span>
                </template>
            </v-breadcrumbs>
        </v-app-bar-title>
        <template #append>
            <v-menu
                width="200"
                rounded="lg"
                elevation="2"
                :close-on-content-click="true"
            >
                <template #activator="{ props }">
                    <div
                        v-bind="props"
                        class="d-flex align-center cursor-pointer px-3 py-1 rounded-pill transition-colors mr-1 profile-btn"
                        style="gap: 10px"
                    >
                        <!-- Avatar -->
                        <v-avatar
                            size="34"
                            style="
                                background-color: #ffffff;
                                border: 1px solid #a01526;
                                color: #a01526;
                                font-weight: 600;
                                font-size: 13px;
                            "
                        >
                            <img
                                v-if="authStore.user?.image && !imageLoadError"
                                :src="authStore.user.image"
                                :alt="authStore.user?.name || 'Admin'"
                                style="
                                    width: 100%;
                                    height: 100%;
                                    object-fit: cover;
                                "
                                @error="imageLoadError = true"
                            />
                            <template v-else>{{ initials }}</template>
                        </v-avatar>

                        <!-- Name & Email (desktop only) -->
                        <div class="d-none d-sm-flex flex-column text-left">
                            <span
                                class="text-body-2 font-weight-bold text-high-emphasis leading-tight mb-0"
                            >
                                {{ authStore.user?.name || "Admin" }}
                            </span>
                            <span
                                class="text-caption text-medium-emphasis leading-tight"
                                style="font-size: 11px"
                            >
                                {{ authStore.user?.email || "admin@gmail.com" }}
                            </span>
                        </div>

                        <v-icon
                            size="small"
                            color="medium-emphasis"
                            class="d-none d-sm-flex"
                            >mdi-chevron-down</v-icon
                        >
                    </div>
                </template>

                <v-list class="pa-1" elevation="0" density="compact">
                    <v-list-item
                        prepend-icon="mdi-account-outline"
                        title="Profile"
                        to="/admin/profile"
                        rounded="md"
                        min-height="36"
                    />
                    <v-list-item
                        prepend-icon="mdi-theme-light-dark"
                        title="Ubah Tema"
                        @click="toggleTheme"
                        rounded="md"
                        min-height="36"
                    />
                    <v-divider class="my-1" />
                    <v-list-item
                        prepend-icon="mdi-logout"
                        title="Log out"
                        base-color="error"
                        @click="handleLogout"
                        rounded="md"
                        min-height="36"
                    />
                </v-list>
            </v-menu>
        </template>
    </v-app-bar>
    <BaseConfirm ref="confirmDialogRef" />
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useNavigation } from "@/admin/composables/useNavigation";
import { useThemeSettings } from "@/admin/composables/useThemeSettings";
import { useAuthStore } from "@/shared/stores/auth";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useRouter } from "vue-router";
import BaseConfirm from "@/shared/components/BaseConfirm.vue";

const drawer = defineModel<boolean>("drawer");
const rail = defineModel<boolean>("rail");

const { breadcrumbs } = useNavigation();
const { toggleTheme } = useThemeSettings();
const authStore = useAuthStore();
const snackbar = useSnackbarStore();
const router = useRouter();
const imageLoadError = ref(false);

const userImage = computed(() => authStore.user?.image || null);
watch(userImage, () => {
    imageLoadError.value = false;
});

const initials = computed(() => {
    const name = authStore.user?.name || "Admin";
    const parts = name.trim().split(" ");
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
});

const confirmDialogRef = ref<InstanceType<typeof BaseConfirm> | null>(null);

const handleLogout = async () => {
    const confirmed = await confirmDialogRef.value?.open({
        title: "Konfirmasi Logout",
        message: "Apakah Anda yakin ingin keluar dari sistem admin?",
        confirmText: "Ya, Keluar",
        confirmColor: "error",
        icon: "mdi-logout",
    });

    if (confirmed) {
        await authStore.logout(false); // false = no full page reload
        snackbar.showMessage("Anda berhasil keluar dari sistem.");
        router.push("/admin/login");
    }
};
</script>

<style scoped>
.profile-btn {
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}
.profile-btn:hover {
    background-color: rgba(var(--v-theme-on-surface), 0.04);
}
</style>
