<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-6 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Role Permission</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk mengelola role dan hak akses administrator DNY
                    Skincare.
                </p>
            </div>
            <v-btn
                color="primary"
                prepend-icon="mdi-shield-plus"
                class="text-none"
                variant="outlined"
                @click="openFormDialog()"
            >
                Tambah Role
            </v-btn>
        </div>

        <v-row>
            <v-col cols="12" md="4">
                <v-card variant="outlined" class="rounded-lg h-100">
                    <v-card-title class="pa-5 pb-3 d-flex align-center ga-3">
                        <v-icon color="primary" size="24"
                            >mdi-shield-account-outline</v-icon
                        >
                        <span class="text-subtitle-1 font-weight-bold"
                            >Daftar Role</span
                        >
                    </v-card-title>

                    <v-divider></v-divider>

                    <v-card-text class="pa-0">
                        <div v-if="loadingRoles" class="pa-8 text-center">
                            <v-progress-circular
                                indeterminate
                                color="primary"
                                size="36"
                            ></v-progress-circular>
                        </div>

                        <div
                            v-else-if="roles.length === 0"
                            class="pa-8 text-center"
                        >
                            <v-icon
                                size="48"
                                color="grey-lighten-1"
                                class="mb-3"
                                >mdi-shield-off-outline</v-icon
                            >
                            <p class="text-body-2 text-medium-emphasis">
                                Belum ada role.
                            </p>
                        </div>

                        <!-- Role list -->
                        <v-list
                            v-else
                            density="compact"
                            bg-color="transparent"
                            nav
                            class="pa-3"
                        >
                            <v-list-item
                                v-for="role in roles"
                                :key="role.id"
                                :value="role.id"
                                color="primary"
                                :variant="
                                    selectedRoleId === role.id
                                        ? 'tonal'
                                        : 'text'
                                "
                                @click="selectRole(role)"
                                class="mb-1 rounded-lg"
                            >
                                <template #prepend>
                                    <v-icon
                                        :icon="
                                            role.type === 'superuser'
                                                ? 'mdi-shield-crown'
                                                : 'mdi-shield-account-outline'
                                        "
                                        :color="
                                            selectedRoleId === role.id
                                                ? 'primary'
                                                : 'grey'
                                        "
                                    />
                                </template>
                                <v-list-item-title class="font-weight-medium">{{
                                    role.title
                                }}</v-list-item-title>
                                <v-list-item-subtitle
                                    class="d-flex align-center ga-2 mt-1"
                                >
                                    <v-chip
                                        size="x-small"
                                        variant="tonal"
                                        :color="
                                            role.type === 'superuser'
                                                ? 'warning'
                                                : 'info'
                                        "
                                        label
                                    >
                                        {{
                                            role.type === "superuser"
                                                ? "Super User"
                                                : "Administrator"
                                        }}
                                    </v-chip>
                                    <span class="text-caption"
                                        >{{
                                            role.administrator_count
                                        }}
                                        user</span
                                    >
                                </v-list-item-subtitle>

                                <template #append>
                                    <div class="d-flex ga-1">
                                        <v-btn
                                            icon="mdi-pencil-outline"
                                            variant="text"
                                            size="x-small"
                                            color="info"
                                            @click.stop="openFormDialog(role)"
                                        ></v-btn>
                                        <v-btn
                                            icon="mdi-trash-can-outline"
                                            variant="text"
                                            size="x-small"
                                            color="error"
                                            @click.stop="deleteRole(role)"
                                        ></v-btn>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="8">
                <RolePrivileges :role="selectedRole" />
            </v-col>
        </v-row>

        <RoleForm ref="roleFormRef" @saved="onRoleSaved" />
        <BaseConfirm ref="confirmDialogRef" />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import type { Role } from "@/admin/types/role";
import roleService from "@/admin/services/role.service";
import RoleForm from "@/admin/components/system/RoleForm.vue";
import RolePrivileges from "@/admin/components/system/RolePrivileges.vue";
import BaseConfirm from "@/shared/components/BaseConfirm.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const snackbar = useSnackbarStore();

const roles = ref<Role[]>([]);
const loadingRoles = ref(false);
const selectedRoleId = ref<number | null>(null);
const selectedRole = ref<Role | null>(null);

const roleFormRef = ref<InstanceType<typeof RoleForm> | null>(null);
const confirmDialogRef = ref<InstanceType<typeof BaseConfirm> | null>(null);

const fetchRoles = async () => {
    loadingRoles.value = true;
    try {
        roles.value = await roleService.getRoles();
    } catch (error) {
        snackbar.showMessage("Gagal memuat daftar role", "error");
    } finally {
        loadingRoles.value = false;
    }
};

const selectRole = (role: Role) => {
    selectedRoleId.value = role.id;
    selectedRole.value = role;
};

const openFormDialog = (role?: Role) => {
    roleFormRef.value?.open(role);
};

const onRoleSaved = async () => {
    const previousSelectedId = selectedRoleId.value;
    await fetchRoles();

    if (previousSelectedId) {
        const updated = roles.value.find((r) => r.id === previousSelectedId);
        if (updated) {
            selectRole(updated);
        }
    }
};

const deleteRole = async (role: Role) => {
    if (!confirmDialogRef.value) return;

    const isConfirmed = await confirmDialogRef.value.open({
        title: "Hapus Role",
        message: `Apakah Anda yakin ingin menghapus role "${role.title}"? Tindakan ini tidak dapat dibatalkan.`,
        confirmText: "Ya, Hapus",
        confirmColor: "error",
        icon: "mdi-delete-alert",
    });

    if (isConfirmed) {
        try {
            await roleService.deleteRole(role.id);
            snackbar.showMessage("Role berhasil dihapus", "success");

            if (selectedRoleId.value === role.id) {
                selectedRoleId.value = null;
                selectedRole.value = null;
            }
            await fetchRoles();
        } catch (error: any) {
            const message =
                error.response?.data?.message || "Gagal menghapus role";
            snackbar.showMessage(message, "error");
        }
    }
};

onMounted(() => {
    fetchRoles();
});
</script>
