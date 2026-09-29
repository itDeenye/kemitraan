import { computed } from "vue";
import { useRoute } from "vue-router";
import { useAuthStore } from "@/shared/stores/auth";

export function useNavigation() {
    const route = useRoute();
    const authStore = useAuthStore();

    const pageTitle = computed(() => (route.meta.title as string) || "Admin Panel");

    const breadcrumbs = computed(() => {
        const path = route.path;
        const crumbs: { title: string }[] = [];
        const menuItems = authStore.sidebarMenus;

        /**
         * Check if `path` belongs to the menu item `to`.
         * Matches exactly, or as a prefix followed by '/'.
         * This prevents '/stock' from matching '/stock-adjustment'.
         */
        const isMatch = (to: string | undefined, currentPath: string): boolean => {
            if (!to || to === "/admin") return false;
            if (to === currentPath) return true;
            return currentPath.startsWith(to + "/");
        };

        for (const item of menuItems) {
            if (item.to === path || isMatch(item.to, path)) {
                crumbs.push({ title: item.title });
                break;
            }
            if (item.children) {
                // Sort children by path length descending so more specific routes match first
                const sortedChildren = [...item.children].sort(
                    (a, b) => (b.to?.length || 0) - (a.to?.length || 0),
                );
                const child = sortedChildren.find(
                    (c) => c.to === path || isMatch(c.to, path),
                );
                if (child) {
                    crumbs.push({ title: item.title });
                    crumbs.push({ title: child.title });
                    break;
                }
            }
        }

        if (crumbs.length === 0) {
            crumbs.push({ title: pageTitle.value });
        }
        return crumbs;
    });

    return {
        pageTitle,
        breadcrumbs,
    };
}
