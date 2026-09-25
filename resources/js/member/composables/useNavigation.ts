import { computed } from "vue";
import { useRoute } from "vue-router";
import { useAuthStore } from "@/shared/stores/auth";

export function useNavigation() {
    const route = useRoute();
    const authStore = useAuthStore();

    const pageTitle = computed(() => (route.meta.title as string) || "Member Panel");

    const breadcrumbs = computed(() => {
        const path = route.path;
        const crumbs = [];
        const menuItems = authStore.sidebarMenus;

        for (const item of menuItems) {
            if (item.to === path || (item.to !== "/member" && item.to && path.startsWith(item.to))) {
                crumbs.push({ title: item.title });
                break;
            }
            if (item.children) {
                const child = item.children.find(
                    (c) =>
                        c.to === path ||
                        (c.to !== "/member" && c.to && path.startsWith(c.to))
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
