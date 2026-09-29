<template>
    <div>
        <div class="d-flex align-center justify-space-between mb-4">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Menu Admin</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk mengelola struktur menu sidebar admin DNY
                    Skincare.
                </p>
            </div>
            <v-btn
                color="primary"
                variant="outlined"
                prepend-icon="mdi-plus"
                @click="openAddParent"
            >
                Tambah Menu
            </v-btn>
        </div>

        <v-card variant="outlined" class="pa-0">
            <v-table density="comfortable" hover>
                <thead>
                    <tr class="bg-grey-lighten-4">
                        <th width="40"></th>
                        <th>Nama Menu</th>
                        <th>Icon</th>
                        <th>Path (URL)</th>
                        <th width="100">Status</th>
                        <th width="180" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody v-if="isLoading">
                    <tr>
                        <td colspan="6" class="text-center pa-4">
                            <v-progress-circular
                                indeterminate
                                color="primary"
                            ></v-progress-circular>
                        </td>
                    </tr>
                </tbody>
                <tbody v-else-if="menus.length === 0">
                    <tr>
                        <td
                            colspan="6"
                            class="text-center pa-8 text-medium-emphasis"
                        >
                            Belum ada menu. Klik "Tambah Parent" untuk memulai.
                        </td>
                    </tr>
                </tbody>
                <tbody v-else>
                    <template v-for="parent in menus" :key="parent.id">
                        <tr
                            class="bg-grey-lighten-5 cursor-pointer hover-bg-grey-lighten-4 transition-all"
                            @click="toggleExpand(parent.id)"
                        >
                            <td class="text-center">
                                <v-icon
                                    :icon="
                                        expanded.includes(parent.id)
                                            ? 'mdi-chevron-down'
                                            : 'mdi-chevron-right'
                                    "
                                    size="small"
                                    color="grey-darken-1"
                                ></v-icon>
                            </td>
                            <td class="font-weight-bold">
                                {{ parent.title }}
                                <v-chip
                                    v-if="
                                        parent.children &&
                                        parent.children.length
                                    "
                                    size="x-small"
                                    class="ml-2"
                                    color="secondary"
                                    variant="tonal"
                                >
                                    {{ parent.children.length }} sub
                                </v-chip>
                            </td>
                            <td>
                                <v-icon
                                    :icon="parent.icon || 'mdi-folder'"
                                    size="small"
                                    class="mr-2"
                                />
                                <span
                                    class="text-caption text-medium-emphasis"
                                    >{{ parent.icon }}</span
                                >
                            </td>
                            <td
                                class="text-caption font-family-monospace text-medium-emphasis"
                            >
                                {{ parent.link || "-" }}
                            </td>
                            <td>
                                <BaseBadge
                                    type="status"
                                    :value="parent.is_active"
                                    inline
                                />
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-center align-center">
                                    <v-btn
                                        icon="mdi-arrow-up"
                                        variant="text"
                                        size="x-small"
                                        :disabled="menus.indexOf(parent) === 0"
                                        @click.stop="
                                            handleMoveOrder(parent, 'up', menus)
                                        "
                                    ></v-btn>
                                    <v-btn
                                        icon="mdi-arrow-down"
                                        variant="text"
                                        size="x-small"
                                        :disabled="
                                            menus.indexOf(parent) ===
                                            menus.length - 1
                                        "
                                        @click.stop="
                                            handleMoveOrder(
                                                parent,
                                                'down',
                                                menus,
                                            )
                                        "
                                    ></v-btn>
                                    <v-menu>
                                        <template v-slot:activator="{ props }">
                                            <v-btn
                                                icon="mdi-dots-vertical"
                                                variant="text"
                                                size="small"
                                                v-bind="props"
                                                @click.stop
                                            ></v-btn>
                                        </template>
                                        <v-list
                                            density="compact"
                                            min-width="150"
                                        >
                                            <v-list-item
                                                prepend-icon="mdi-plus"
                                                title="Tambah Submenu"
                                                @click.stop="
                                                    openAddChild(parent.id)
                                                "
                                            ></v-list-item>
                                            <v-divider></v-divider>
                                            <v-list-item
                                                prepend-icon="mdi-pencil"
                                                title="Edit Parent"
                                                @click.stop="
                                                    openEdit(parent, false)
                                                "
                                            ></v-list-item>
                                            <v-list-item
                                                prepend-icon="mdi-delete"
                                                title="Hapus"
                                                base-color="error"
                                                @click.stop="
                                                    confirmDelete(parent)
                                                "
                                            ></v-list-item>
                                        </v-list>
                                    </v-menu>
                                </div>
                            </td>
                        </tr>

                        <template v-if="expanded.includes(parent.id)">
                            <tr
                                v-for="child in parent.children"
                                :key="child.id"
                            >
                                <td></td>
                                <td class="pl-8 text-body-2">
                                    <div class="d-flex align-center">
                                        <v-icon
                                            icon="mdi-subdirectory-arrow-right"
                                            size="x-small"
                                            class="mr-2 text-disabled"
                                        />
                                        {{ child.title }}
                                    </div>
                                </td>
                                <td>
                                    <v-icon
                                        v-if="child.icon"
                                        :icon="child.icon"
                                        size="small"
                                        class="mr-2 text-disabled"
                                    />
                                    <span
                                        v-if="child.icon"
                                        class="text-caption text-disabled"
                                        >{{ child.icon }}</span
                                    >
                                </td>
                                <td
                                    class="text-caption font-family-monospace text-medium-emphasis"
                                >
                                    {{ child.link }}
                                </td>
                                <td>
                                    <BaseBadge
                                        type="status"
                                        :value="child.is_active"
                                        inline
                                    />
                                </td>
                                <td class="text-center">
                                    <div
                                        class="d-flex justify-center align-center"
                                    >
                                        <v-btn
                                            icon="mdi-arrow-up"
                                            variant="text"
                                            size="x-small"
                                            :disabled="
                                                parent.children.indexOf(
                                                    child,
                                                ) === 0
                                            "
                                            @click="
                                                handleMoveOrder(
                                                    child,
                                                    'up',
                                                    parent.children,
                                                )
                                            "
                                        ></v-btn>
                                        <v-btn
                                            icon="mdi-arrow-down"
                                            variant="text"
                                            size="x-small"
                                            :disabled="
                                                parent.children.indexOf(
                                                    child,
                                                ) ===
                                                parent.children.length - 1
                                            "
                                            @click="
                                                handleMoveOrder(
                                                    child,
                                                    'down',
                                                    parent.children,
                                                )
                                            "
                                        ></v-btn>
                                        <v-menu>
                                            <template
                                                v-slot:activator="{ props }"
                                            >
                                                <v-btn
                                                    icon="mdi-dots-vertical"
                                                    variant="text"
                                                    size="small"
                                                    v-bind="props"
                                                ></v-btn>
                                            </template>
                                            <v-list
                                                density="compact"
                                                min-width="150"
                                            >
                                                <v-list-item
                                                    prepend-icon="mdi-pencil"
                                                    title="Edit Submenu"
                                                    @click="
                                                        openEdit(child, true)
                                                    "
                                                ></v-list-item>
                                                <v-list-item
                                                    prepend-icon="mdi-delete"
                                                    title="Hapus"
                                                    base-color="error"
                                                    @click="
                                                        confirmDelete(child)
                                                    "
                                                ></v-list-item>
                                            </v-list>
                                        </v-menu>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="parent.children.length === 0">
                                <td></td>
                                <td
                                    colspan="5"
                                    class="pl-8 text-caption text-medium-emphasis py-2"
                                >
                                    Belum ada submenu.
                                    <a
                                        href="#"
                                        @click.prevent="openAddChild(parent.id)"
                                        class="text-primary text-decoration-none ml-2"
                                        >Tambah Submenu</a
                                    >
                                </td>
                            </tr>
                        </template>
                    </template>
                </tbody>
            </v-table>
        </v-card>

        <MenuForm
            v-model="isFormOpen"
            :is-loading="isSubmitting"
            :is-edit-mode="isEditMode"
            :is-submenu="isSubmenu"
            :initial-data="editingMenu"
            :parent-id="activeParentId"
            @save="handleSave"
        />

        <v-dialog v-model="isDeleteDialogOpen" max-width="400">
            <v-card>
                <v-card-title class="text-h6 text-error"
                    >Hapus Menu</v-card-title
                >
                <v-card-text>
                    Apakah Anda yakin ingin menghapus menu
                    <strong>{{ menuToDelete?.title }}</strong
                    >? <br /><br />
                    <small class="text-error" v-if="!menuToDelete?.parent_id"
                        >Menghapus parent menu juga akan menghapus semua submenu
                        di dalamnya!</small
                    >
                </v-card-text>
                <v-card-actions class="pa-4">
                    <v-spacer></v-spacer>
                    <v-btn variant="plain" @click="isDeleteDialogOpen = false"
                        >Batal</v-btn
                    >
                    <v-btn
                        color="error"
                        variant="flat"
                        :loading="isDeleting"
                        @click="handleDelete"
                        >Hapus</v-btn
                    >
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import MenuService from "@/admin/services/menu.service";
import type { AdminMenu, AdminMenuPayload } from "@/admin/types/menu";
import MenuForm from "@/admin/components/system/MenuForm.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useAuthStore } from "@/shared/stores/auth";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const snackbar = useSnackbarStore();
const authStore = useAuthStore();
const menus = ref<AdminMenu[]>([]);
const expanded = ref<number[]>([]);
const isLoading = ref(true);
const isFormOpen = ref(false);
const isSubmitting = ref(false);
const isEditMode = ref(false);
const isSubmenu = ref(false);
const editingMenu = ref<Partial<AdminMenu> | null>(null);
const activeParentId = ref<number | null>(null);
const isDeleteDialogOpen = ref(false);
const isDeleting = ref(false);
const menuToDelete = ref<AdminMenu | null>(null);

const fetchMenus = async () => {
    isLoading.value = true;
    try {
        menus.value = await MenuService.getMenuTree();
    } catch (error) {
        console.error("Failed to load menus:", error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(fetchMenus);

const toggleExpand = (id: number) => {
    const index = expanded.value.indexOf(id);
    if (index === -1) {
        expanded.value.push(id);
    } else {
        expanded.value.splice(index, 1);
    }
};

const openAddParent = () => {
    isEditMode.value = false;
    isSubmenu.value = false;
    editingMenu.value = null;
    activeParentId.value = null;
    isFormOpen.value = true;
};

const openAddChild = (parentId: number) => {
    isEditMode.value = false;
    isSubmenu.value = true;
    editingMenu.value = null;
    activeParentId.value = parentId;
    isFormOpen.value = true;
    if (!expanded.value.includes(parentId)) {
        expanded.value.push(parentId);
    }
};

const openEdit = (menu: AdminMenu, asSubmenu: boolean) => {
    isEditMode.value = true;
    isSubmenu.value = asSubmenu;
    editingMenu.value = menu;
    activeParentId.value = menu.parent_id;
    isFormOpen.value = true;
};

const handleSave = async (payload: AdminMenuPayload) => {
    isSubmitting.value = true;
    try {
        if (isEditMode.value && editingMenu.value?.id) {
            await MenuService.updateMenu(editingMenu.value.id, payload);
        } else {
            // Set default order to be at the end of the list if new
            if (payload.parent_id) {
                const parent = menus.value.find(
                    (m) => m.id === payload.parent_id,
                );
                payload.order =
                    parent && parent.children ? parent.children.length + 1 : 1;
            } else {
                payload.order = menus.value.length + 1;
            }
            await MenuService.createMenu(payload);
        }
        isFormOpen.value = false;

        // Refresh local table and global store
        await fetchMenus();
        await authStore.refreshMenus();
        snackbar.showMessage("Menu berhasil disimpan!");
    } catch (error: any) {
        console.error("Save failed:", error);
        const msg =
            error.response?.data?.message ||
            "Gagal menyimpan menu. Periksa koneksi atau input Anda.";
        snackbar.showMessage(msg, "error");
    } finally {
        isSubmitting.value = false;
    }
};

const handleMoveOrder = async (
    menu: AdminMenu,
    direction: "up" | "down",
    list: AdminMenu[],
) => {
    const index = list.findIndex((m) => m.id === menu.id);
    if (index === -1) return;

    if (direction === "up" && index === 0) return;
    if (direction === "down" && index === list.length - 1) return;

    const swapIndex = direction === "up" ? index - 1 : index + 1;
    const sibling = list[swapIndex];
    const menuOrder = menu.order || index + 1;
    const siblingOrder = sibling.order || swapIndex + 1;

    const payloadMenu: AdminMenuPayload = {
        parent_id: menu.parent_id,
        title: menu.title,
        description: menu.description,
        link: menu.link,
        icon: menu.icon,
        css_class: menu.class,
        order: siblingOrder,
        is_active: menu.is_active,
    };

    const payloadSibling: AdminMenuPayload = {
        parent_id: sibling.parent_id,
        title: sibling.title,
        description: sibling.description,
        link: sibling.link,
        icon: sibling.icon,
        css_class: sibling.class,
        order: menuOrder,
        is_active: sibling.is_active,
    };

    isLoading.value = true;
    try {
        await MenuService.updateMenu(menu.id, payloadMenu);
        await MenuService.updateMenu(sibling.id, payloadSibling);

        await fetchMenus();
        await authStore.refreshMenus();
        snackbar.showMessage("Urutan menu berhasil diubah!");
    } catch (error: any) {
        console.error("Failed to change order", error);
        const msg =
            error.response?.data?.message || "Gagal mengubah urutan menu.";
        snackbar.showMessage(msg, "error");
    } finally {
        isLoading.value = false;
    }
};

const confirmDelete = (menu: AdminMenu) => {
    menuToDelete.value = menu;
    isDeleteDialogOpen.value = true;
};

const handleDelete = async () => {
    if (!menuToDelete.value) return;

    isDeleting.value = true;
    try {
        await MenuService.deleteMenu(menuToDelete.value.id);
        isDeleteDialogOpen.value = false;

        // Refresh local table and global store
        await fetchMenus();
        await authStore.refreshMenus();
        snackbar.showMessage("Menu berhasil dihapus!");
    } catch (error: any) {
        console.error("Delete failed:", error);
        const msg = error.response?.data?.message || "Gagal menghapus menu.";
        snackbar.showMessage(msg, "error");
    } finally {
        isDeleting.value = false;
        menuToDelete.value = null;
    }
};
</script>

<style scoped>
.hover-bg-grey-lighten-4:hover {
    background-color: #f5f5f5 !important;
}
.transition-all {
    transition: all 0.2s ease;
}
</style>
