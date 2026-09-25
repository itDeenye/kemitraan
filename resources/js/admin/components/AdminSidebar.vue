<template>
    <v-navigation-drawer
        v-model="drawer"
        :rail="rail"
        mobile-breakpoint="md"
        color="surface"
        width="260"
        rail-width="72"
        elevation="0"
        class="custom-sidebar border-r"
    >
        <template #prepend>
            <v-list-item
                :class="[
                    'transition-all',
                    rail
                        ? 'pa-3 mt-2 mb-2 d-flex justify-center'
                        : 'pa-4 mt-2 mb-2',
                ]"
            >
                <template #prepend>
                    <v-img
                        src="/logo-fix.png"
                        alt="DNY Skincare"
                        :width="rail ? 40 : 65"
                        :height="rail ? 40 : 65"
                        :class="['transition-all', rail ? 'mr-0' : 'mr-1']"
                        style="object-fit: contain"
                    />
                </template>
                <v-list-item-title
                    v-show="!rail"
                    class="text-h6 font-weight-bold"
                    style="line-height: 1.2"
                >
                    DNY Skincare
                </v-list-item-title>
                <v-list-item-subtitle
                    v-show="!rail"
                    class="text-body-2 mt-1 text-medium-emphasis"
                    >Admin Panel</v-list-item-subtitle
                >
            </v-list-item>
            <v-divider />
        </template>

        <v-list nav class="pa-3" v-model:opened="openGroups">
            <template v-for="item in menuItems" :key="item.id">
                <v-list-group
                    v-if="item.children && item.children.length > 0 && !rail"
                    :value="item.title"
                >
                    <template #activator="{ props }">
                        <v-list-item
                            v-bind="props"
                            :prepend-icon="item.icon"
                            rounded="lg"
                            :color="
                                isParentActive(item) ? 'primary' : 'default'
                            "
                            :variant="isParentActive(item) ? 'tonal' : 'text'"
                            style="min-height: 44px"
                            class="pr-2"
                        >
                            <v-list-item-title
                                class="text-body-2 font-weight-medium"
                            >
                                <span class="sidebar-menu-title">
                                    <span class="text-truncate">{{ item.title }}</span>
                                    <span
                                        v-if="item.badge"
                                        class="admin-action-badge"
                                    >
                                        {{ formatBadgeCount(item.badge) }}
                                    </span>
                                </span>
                            </v-list-item-title>
                        </v-list-item>
                    </template>

                    <v-list-item
                        v-for="child in item.children"
                        :key="child.id"
                        :prepend-icon="child.icon"
                        :to="child.to"
                        :href="child.href"
                        :target="child.target"
                        rounded="lg"
                        color="primary"
                        :variant="isChildActive(child) ? 'tonal' : 'text'"
                        style="
                            min-height: 40px;
                            padding-inline-start: 28px !important;
                        "
                        class="pr-3"
                    >
                        <v-list-item-title class="text-body-2">
                            <span class="sidebar-menu-title">
                                <span class="text-truncate">{{ child.title }}</span>
                                <span
                                    v-if="child.badge"
                                    class="admin-action-badge"
                                >
                                    {{ formatBadgeCount(child.badge) }}
                                </span>
                            </span>
                        </v-list-item-title>
                    </v-list-item>
                </v-list-group>

                <v-list-item
                    v-else-if="
                        item.children && item.children.length > 0 && rail
                    "
                    :prepend-icon="undefined"
                    rounded="lg"
                    :color="isParentActive(item) ? 'primary' : 'default'"
                    :variant="isParentActive(item) ? 'tonal' : 'text'"
                    class="mb-1"
                    style="min-height: 44px"
                    @click="rail = false"
                >
                    <template #prepend>
                        <v-badge
                            :model-value="Boolean(item.badge)"
                            :content="formatBadgeCount(item.badge || 0)"
                            color="error"
                            location="top end"
                            offset-x="-2"
                            offset-y="-2"
                        >
                            <v-icon :icon="item.icon" />
                        </v-badge>
                    </template>
                    <v-list-item-title class="text-body-2 font-weight-medium">
                        {{ item.title }}
                    </v-list-item-title>
                </v-list-item>

                <v-list-item
                    v-else
                    :prepend-icon="rail ? undefined : item.icon"
                    :to="item.to"
                    :href="item.href"
                    :target="item.target"
                    :exact="item.to === '/admin'"
                    rounded="lg"
                    color="primary"
                    :variant="
                        (item.to && route.path === item.to) ||
                        (item.to &&
                            item.to !== '/admin' &&
                            route.path.startsWith(item.to! + '/')) ||
                        (item.href && route.path === item.href)
                            ? 'tonal'
                            : 'text'
                    "
                    class="mb-1"
                    style="min-height: 44px"
                >
                    <template v-if="rail" #prepend>
                        <v-badge
                            :model-value="Boolean(item.badge)"
                            :content="formatBadgeCount(item.badge || 0)"
                            color="error"
                            location="top end"
                            offset-x="-2"
                            offset-y="-2"
                        >
                            <v-icon :icon="item.icon" />
                        </v-badge>
                    </template>
                    <v-list-item-title class="text-body-2 font-weight-medium">
                        <span class="sidebar-menu-title">
                            <span class="text-truncate">{{ item.title }}</span>
                            <span
                                v-if="item.badge && !rail"
                                class="admin-action-badge"
                            >
                                {{ formatBadgeCount(item.badge) }}
                            </span>
                        </span>
                    </v-list-item-title>
                </v-list-item>
            </template>
        </v-list>
    </v-navigation-drawer>
</template>

<script setup lang="ts">
import { computed, ref, watch, onMounted, onUnmounted } from "vue";
import { useRoute } from "vue-router";
import { useAuthStore } from "@/shared/stores/auth";
import { AdminActionSummaryService } from "@/admin/services/action-summary.service";
import type { SidebarMenuItem } from "@/admin/types/menu";

const drawer = defineModel<boolean>("drawer");
const rail = defineModel<boolean>("rail");
const route = useRoute();
const authStore = useAuthStore();

const actionCountByRoute = ref<Record<string, number>>({});
let actionRefreshInterval: ReturnType<typeof setInterval> | null = null;

const menuWithActionBadge = (item: SidebarMenuItem): SidebarMenuItem => {
    const children = item.children?.map(menuWithActionBadge);
    const ownCount = item.to ? actionCountByRoute.value[item.to] || 0 : 0;
    const childCount = children?.reduce(
        (total, child) => total + (child.badge || 0),
        0,
    ) || 0;
    const badge = ownCount + childCount;

    return {
        ...item,
        ...(children ? { children } : {}),
        ...(badge > 0 ? { badge } : {}),
    };
};

/** Dynamic sidebar menus from auth store, enriched with actionable counts. */
const menuItems = computed(() =>
    authStore.sidebarMenus.map(menuWithActionBadge),
);

const formatBadgeCount = (count: number) => (count > 99 ? "99+" : count);

const loadActionSummary = async () => {
    try {
        const response = await AdminActionSummaryService.get();
        actionCountByRoute.value = Object.fromEntries(
            response.data.menus.map((menu) => [menu.route, menu.count]),
        );
    } catch (error) {
        console.error("Failed to load admin action summary:", error);
    }
};

const openGroups = ref<string[]>([]);

const isChildActive = (child: any) => {
    if (!child || (!child.to && !child.href)) return false;
    if (child.href) return route.path === child.href;
    return (
        route.path === child.to ||
        (child.to !== "/admin" && route.path.startsWith(child.to + "/"))
    );
};

const isParentActive = (item: any) => {
    if (!item.children || item.children.length === 0) return false;
    return item.children.some((child: any) => isChildActive(child));
};

const updateOpenGroups = () => {
    const currentPath = route.path;
    let activeParent = "";

    for (const item of menuItems.value) {
        if (item.children) {
            const hasActiveChild = item.children.some(
                (child) =>
                    (child.to && currentPath === child.to) ||
                    (child.to &&
                        child.to !== "/admin" &&
                        currentPath.startsWith(child.to + "/")) ||
                    (child.href && currentPath === child.href),
            );
            if (hasActiveChild) {
                activeParent = item.title;
                break;
            }
        }
    }

    if (activeParent && !openGroups.value.includes(activeParent)) {
        openGroups.value.push(activeParent);
    }
};

watch(() => route.path, () => {
    updateOpenGroups();
    loadActionSummary();
});
watch(menuItems, updateOpenGroups);
onMounted(() => {
    updateOpenGroups();
    loadActionSummary();
    actionRefreshInterval = setInterval(loadActionSummary, 60_000);
});
onUnmounted(() => {
    if (actionRefreshInterval) {
        clearInterval(actionRefreshInterval);
    }
});

// Ensure accordion behavior but keep active parent open
watch(openGroups, (newVal, oldVal) => {
    // If a new group was opened (length increased)
    if (newVal.length > oldVal.length && newVal.length > 1) {
        const currentPath = route.path;
        let activeParent = "";

        for (const item of menuItems.value) {
            if (item.children) {
                const hasActiveChild = item.children.some(
                    (child) =>
                        (child.to && currentPath === child.to) ||
                        (child.to &&
                            child.to !== "/admin" &&
                            currentPath.startsWith(child.to + "/")) ||
                        (child.href && currentPath === child.href),
                );
                if (hasActiveChild) {
                    activeParent = item.title;
                    break;
                }
            }
        }

        // The newly opened group is the last one in the array
        const newlyOpened = newVal[newVal.length - 1];

        // We only want the newly opened one, AND the active parent (if any)
        openGroups.value = [newlyOpened];
        if (activeParent && activeParent !== newlyOpened) {
            openGroups.value.push(activeParent);
        }
    }
});
</script>

<style scoped>
/* Kurangi jarak (gap) antara icon dengan teks nama menu */
:deep(.v-list-item__spacer) {
    width: 14px !important;
}

/* Sedikit menggeser chevron/arrow ke kanan agar tidak terlalu sempit */
:deep(.v-list-item__append) {
    margin-inline-start: 4px;
}

/* Mencegah huruf bagian bawah (seperti g, p, y) terpotong (clipped) akibat overflow:hidden & line-height yang terlalu sempit */
:deep(.v-list-item-title) {
    line-height: 1.5 !important;
}

.sidebar-menu-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-width: 0;
}

.admin-action-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: rgb(var(--v-theme-error));
    color: rgb(var(--v-theme-on-error));
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
}
</style>
