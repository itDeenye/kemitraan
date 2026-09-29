<template>
    <v-card variant="outlined" class="rounded-lg h-100 d-flex flex-column">
        <!-- Header -->
        <v-card-title
            class="pa-5 pb-3 d-flex align-center justify-space-between flex-wrap ga-3 border-b"
        >
            <div class="d-flex align-center ga-3">
                <v-icon color="primary" size="24">mdi-shield-key</v-icon>
                <div>
                    <span class="text-subtitle-1 font-weight-bold"
                        >Hak Akses</span
                    >
                    <span
                        v-if="role"
                        class="text-body-2 text-medium-emphasis ml-2"
                        >— {{ role.title }}</span
                    >
                </div>
            </div>
            <v-btn
                v-if="role"
                color="success"
                variant="flat"
                size="small"
                prepend-icon="mdi-content-save"
                class="text-none"
                :loading="saving"
                :disabled="isSuperUser"
                @click="savePrivileges"
            >
                Simpan Hak Akses
            </v-btn>
        </v-card-title>

        <v-card-text
            class="pa-0 flex-grow-1"
            style="overflow-y: auto; max-height: calc(100vh - 200px)"
        >
            <!-- Empty state -->
            <div
                v-if="!role"
                class="pa-12 text-center h-100 d-flex flex-column justify-center align-center"
            >
                <v-icon size="64" color="grey-lighten-1" class="mb-4"
                    >mdi-shield-off-outline</v-icon
                >
                <p class="text-body-1 text-medium-emphasis">
                    Pilih role dari daftar di kiri untuk melihat dan mengatur
                    hak akses.
                </p>
            </div>

            <!-- Loading state -->
            <div
                v-else-if="loadingMenus || loadingPrivileges"
                class="pa-12 text-center h-100 d-flex flex-column justify-center align-center"
            >
                <v-progress-circular
                    indeterminate
                    color="primary"
                    size="40"
                    class="mb-4"
                ></v-progress-circular>
                <p class="text-body-2 text-medium-emphasis">
                    Memuat data hak akses...
                </p>
            </div>

            <!-- Privileges tree -->
            <div v-else class="pa-5">
                <!-- Superuser Alert -->
                <v-alert
                    v-if="isSuperUser"
                    type="info"
                    variant="tonal"
                    class="mb-4"
                    border="start"
                >
                    <template #title> Akses Super User </template>
                    Role dengan tipe <strong>Super User</strong> secara otomatis
                    memiliki akses penuh ke seluruh menu dan fitur dalam sistem,
                    terlepas dari konfigurasi centang di bawah ini.
                </v-alert>

                <!-- Select/Deselect All -->
                <v-card variant="tonal" color="primary" class="mb-4 rounded-lg">
                    <div class="d-flex align-center justify-space-between pa-3">
                        <v-checkbox
                            :model-value="isAllSelected"
                            :indeterminate="isPartialSelected"
                            label="Pilih Semua Menu"
                            color="primary"
                            density="compact"
                            hide-details
                            :disabled="isSuperUser"
                            @update:model-value="toggleAll"
                            class="font-weight-medium"
                        ></v-checkbox>
                        <v-chip
                            size="small"
                            color="primary"
                            variant="flat"
                            class="font-weight-bold"
                        >
                            {{ selectedMenuIds.size }} / {{ totalMenuCount }}
                        </v-chip>
                    </div>
                </v-card>

                <!-- Menu tree -->
                <v-expansion-panels variant="accordion" multiple>
                    <v-expansion-panel
                        v-for="menu in menus"
                        :key="menu.id"
                        class="mb-2 border rounded-lg overflow-hidden"
                        elevation="0"
                    >
                        <!-- Parent with children -->
                        <template
                            v-if="menu.children && menu.children.length > 0"
                        >
                            <v-expansion-panel-title
                                :class="
                                    isMenuChecked(menu.id)
                                        ? 'bg-primary-lighten-5'
                                        : 'bg-grey-lighten-4'
                                "
                                class="pa-3 px-4"
                                expand-icon="mdi-chevron-down"
                                collapse-icon="mdi-chevron-up"
                            >
                                <div
                                    class="d-flex align-center w-100"
                                    @click.stop
                                >
                                    <v-checkbox
                                        :model-value="isParentChecked(menu)"
                                        :indeterminate="
                                            isParentIndeterminate(menu)
                                        "
                                        color="primary"
                                        density="compact"
                                        hide-details
                                        :disabled="isSuperUser"
                                        @update:model-value="
                                            toggleParent(menu, $event)
                                        "
                                        class="mr-2 flex-grow-0"
                                    ></v-checkbox>
                                    <v-icon
                                        :icon="menu.icon || 'mdi-folder'"
                                        size="20"
                                        class="mr-3"
                                        :color="
                                            isMenuChecked(menu.id)
                                                ? 'primary'
                                                : 'grey-darken-1'
                                        "
                                    ></v-icon>
                                    <span
                                        class="text-body-2 font-weight-bold"
                                        :class="
                                            isMenuChecked(menu.id)
                                                ? 'text-primary'
                                                : ''
                                        "
                                        >{{ menu.title }}</span
                                    >
                                    <v-spacer></v-spacer>
                                    <v-chip
                                        size="x-small"
                                        :variant="
                                            isMenuChecked(menu.id)
                                                ? 'flat'
                                                : 'tonal'
                                        "
                                        :color="
                                            isMenuChecked(menu.id)
                                                ? 'primary'
                                                : 'grey'
                                        "
                                        class="mr-4"
                                    >
                                        {{ getCheckedChildCount(menu) }}/{{
                                            menu.children.length + 1
                                        }}
                                    </v-chip>
                                </div>
                            </v-expansion-panel-title>
                            <v-expansion-panel-text>
                                <div class="pt-2">
                                    <div
                                        v-for="(child, index) in menu.children"
                                        :key="child.id"
                                        class="d-flex align-center py-2 px-2 menu-child-item"
                                        :class="{
                                            'border-b':
                                                index <
                                                menu.children.length - 1,
                                        }"
                                    >
                                        <v-checkbox
                                            :model-value="
                                                isMenuChecked(child.id)
                                            "
                                            color="primary"
                                            density="compact"
                                            hide-details
                                            :disabled="isSuperUser"
                                            @update:model-value="
                                                toggleMenu(child.id, $event)
                                            "
                                            class="mr-2"
                                        ></v-checkbox>
                                        <v-icon
                                            :icon="
                                                child.icon || 'mdi-circle-small'
                                            "
                                            size="18"
                                            class="mr-3"
                                            :color="
                                                isMenuChecked(child.id)
                                                    ? 'primary'
                                                    : 'grey-lighten-1'
                                            "
                                        ></v-icon>
                                        <span
                                            class="text-body-2"
                                            :class="
                                                isMenuChecked(child.id)
                                                    ? 'font-weight-medium text-primary'
                                                    : 'text-medium-emphasis'
                                            "
                                        >
                                            {{ child.title }}
                                        </span>
                                    </div>
                                </div>
                            </v-expansion-panel-text>
                        </template>

                        <!-- Single menu (no children) -->
                        <template v-else>
                            <v-expansion-panel-title
                                :class="
                                    isMenuChecked(menu.id)
                                        ? 'bg-primary-lighten-5'
                                        : 'bg-grey-lighten-4'
                                "
                                class="pa-3 px-4"
                                hide-actions
                            >
                                <div
                                    class="d-flex align-center w-100"
                                    @click.stop
                                >
                                    <v-checkbox
                                        :model-value="isMenuChecked(menu.id)"
                                        color="primary"
                                        density="compact"
                                        hide-details
                                        :disabled="isSuperUser"
                                        @update:model-value="
                                            toggleMenu(menu.id, $event)
                                        "
                                        class="mr-2 flex-grow-0"
                                    ></v-checkbox>
                                    <v-icon
                                        :icon="menu.icon || 'mdi-circle-small'"
                                        size="20"
                                        class="mr-3"
                                        :color="
                                            isMenuChecked(menu.id)
                                                ? 'primary'
                                                : 'grey-darken-1'
                                        "
                                    ></v-icon>
                                    <span
                                        class="text-body-2 font-weight-bold"
                                        :class="
                                            isMenuChecked(menu.id)
                                                ? 'text-primary'
                                                : ''
                                        "
                                        >{{ menu.title }}</span
                                    >
                                </div>
                            </v-expansion-panel-title>
                        </template>
                    </v-expansion-panel>
                </v-expansion-panels>
            </div>
        </v-card-text>
    </v-card>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import type { Role } from "@/admin/types/role";
import type { AdminMenu } from "@/admin/types/menu";
import roleService from "@/admin/services/role.service";
import { useAuthStore } from "@/shared/stores/auth";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const props = defineProps<{
    role: Role | null;
}>();

const snackbar = useSnackbarStore();
const authStore = useAuthStore();

const menus = ref<AdminMenu[]>([]);
const selectedMenuIds = ref<Set<number>>(new Set());
const loadingMenus = ref(false);
const loadingPrivileges = ref(false);
const saving = ref(false);

const isSuperUser = computed(() => props.role?.type === "superuser");

/** Collect all menu IDs (parents + children) from the menu tree */
const allMenuIds = computed<number[]>(() => {
    const ids: number[] = [];
    for (const menu of menus.value) {
        ids.push(menu.id);
        if (menu.children) {
            for (const child of menu.children) {
                ids.push(child.id);
            }
        }
    }
    return ids;
});

const totalMenuCount = computed(() => allMenuIds.value.length);

const isAllSelected = computed(() => {
    if (totalMenuCount.value === 0) return false;
    return allMenuIds.value.every((id) => selectedMenuIds.value.has(id));
});

const isPartialSelected = computed(() => {
    if (isAllSelected.value) return false;
    return allMenuIds.value.some((id) => selectedMenuIds.value.has(id));
});

const isMenuChecked = (menuId: number): boolean => {
    return selectedMenuIds.value.has(menuId);
};

/** Check if parent and ALL its children are checked */
const isParentChecked = (menu: AdminMenu): boolean => {
    const parentChecked = selectedMenuIds.value.has(menu.id);
    if (!menu.children || menu.children.length === 0) return parentChecked;
    const allChildrenChecked = menu.children.every((c) =>
        selectedMenuIds.value.has(c.id),
    );
    return parentChecked && allChildrenChecked;
};

/** Check if parent has SOME but not all children checked */
const isParentIndeterminate = (menu: AdminMenu): boolean => {
    if (!menu.children || menu.children.length === 0) return false;
    const allIds = [menu.id, ...menu.children.map((c) => c.id)];
    const checkedCount = allIds.filter((id) =>
        selectedMenuIds.value.has(id),
    ).length;
    return checkedCount > 0 && checkedCount < allIds.length;
};

const getCheckedChildCount = (menu: AdminMenu): number => {
    const allIds = [menu.id, ...(menu.children?.map((c) => c.id) || [])];
    return allIds.filter((id) => selectedMenuIds.value.has(id)).length;
};

const toggleMenu = (menuId: number, checked: any) => {
    const newSet = new Set(selectedMenuIds.value);
    if (checked) {
        newSet.add(menuId);
    } else {
        newSet.delete(menuId);
    }
    selectedMenuIds.value = newSet;
};

const toggleParent = (menu: AdminMenu, checked: any) => {
    const newSet = new Set(selectedMenuIds.value);
    const allIds = [menu.id, ...(menu.children?.map((c) => c.id) || [])];
    if (checked) {
        allIds.forEach((id) => newSet.add(id));
    } else {
        allIds.forEach((id) => newSet.delete(id));
    }
    selectedMenuIds.value = newSet;
};

const toggleAll = (checked: any) => {
    if (checked) {
        selectedMenuIds.value = new Set(allMenuIds.value);
    } else {
        selectedMenuIds.value = new Set();
    }
};

/** Fetch menu tree (called once) */
const fetchMenus = async () => {
    loadingMenus.value = true;
    try {
        menus.value = await roleService.getMenus();
    } catch (error) {
        snackbar.showMessage("Gagal memuat data menu", "error");
    } finally {
        loadingMenus.value = false;
    }
};

/** Fetch privileges for currently selected role */
const fetchPrivileges = async (roleId: number) => {
    loadingPrivileges.value = true;
    try {
        const privileges = await roleService.getPrivileges(roleId);
        selectedMenuIds.value = new Set(privileges.map((p) => p.menu_id));
    } catch (error) {
        snackbar.showMessage("Gagal memuat hak akses", "error");
    } finally {
        loadingPrivileges.value = false;
    }
};

/** Save privileges */
const savePrivileges = async () => {
    if (!props.role) return;

    saving.value = true;
    try {
        const payload = {
            menus: Array.from(selectedMenuIds.value).map((id) => ({
                menu_id: id,
            })),
        };
        await roleService.savePrivileges(props.role.id, payload);
        await authStore.refreshMenus();
        snackbar.showMessage("Hak akses berhasil disimpan", "success");
    } catch (error: any) {
        const message =
            error.response?.data?.message || "Gagal menyimpan hak akses";
        snackbar.showMessage(message, "error");
    } finally {
        saving.value = false;
    }
};

/** Watch for role changes */
watch(
    () => props.role,
    (newRole) => {
        if (newRole) {
            fetchPrivileges(newRole.id);
        } else {
            selectedMenuIds.value = new Set();
        }
    },
    { immediate: true },
);

/** Load menus on mount */
fetchMenus();
</script>

<style scoped>
.menu-child-item:hover {
    background-color: rgba(var(--v-theme-primary), 0.04);
}
</style>
