<template>
    <div>
        <div class="mb-6">
            <h1 class="text-h4 font-weight-bold mb-1">User Administrator</h1>
            <p class="text-body-2 text-medium-emphasis">
                Halaman untuk mengelola data akun administrator sistem DNY
                Skincare.
            </p>
        </div>

        <BaseDataTable
            ref="dataTableRef"
            title="Daftar Administrator"
            icon="mdi-account-tie"
            url="/admin/system/administrators"
            :headers="headers"
            v-model:search="search"
            search-placeholder="Cari nama, username, atau email..."
        >
            <template #header-actions>
                <v-btn
                    color="primary"
                    prepend-icon="mdi-account-plus"
                    @click="openFormDialog()"
                    variant="outlined"
                    class="bg-surface rounded-lg text-uppercase"
                >
                    Tambah Administrator
                </v-btn>
            </template>

            <template #item.image_url="{ item }">
                <div>
                    <v-avatar
                        size="40"
                        color="primary"
                        variant="tonal"
                        class="my-2"
                    >
                        <v-img
                            v-if="item.image_url"
                            :src="item.image_url"
                            cover
                        >
                            <template v-slot:placeholder>
                                <div
                                    class="d-flex align-center justify-center h-100 w-100"
                                >
                                    <v-progress-circular
                                        indeterminate
                                        size="20"
                                        color="primary"
                                        width="2"
                                    ></v-progress-circular>
                                </div>
                            </template>
                            <template v-slot:error>
                                <div
                                    class="d-flex align-center justify-center h-100 w-100"
                                >
                                    <span
                                        class="text-primary font-weight-bold text-body-2"
                                    >
                                        {{ getInitials(item.name) }}
                                    </span>
                                </div>
                            </template>
                        </v-img>
                        <span
                            v-else
                            class="text-primary font-weight-bold text-body-2"
                        >
                            {{ getInitials(item.name) }}
                        </span>
                    </v-avatar>
                </div>
            </template>

            <template #item.name="{ item }">
                <div class="d-flex flex-column py-2">
                    <span class="font-weight-medium">{{ item.name }}</span>
                    <span class="text-caption text-medium-emphasis">{{
                        item.email
                    }}</span>
                </div>
            </template>

            <template #item.role="{ item }">
                <BaseBadge type="category" :value="item.role.title" />
            </template>

            <template #item.is_active="{ item }">
                <BaseBadge type="status" :value="item.is_active" />
            </template>

            <template #item.last_login="{ item }">
                <span v-if="item.last_login" class="text-body-2">
                    {{ formatDateTime(item.last_login) }}
                </span>
                <span v-else class="text-medium-emphasis text-caption">
                    Belum pernah
                </span>
            </template>

            <template #item.actions="{ item }">
                <div class="d-flex ga-2">
                    <v-btn
                        v-tooltip:top="'Ubah Password'"
                        icon="mdi-lock-reset"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="warning"
                        @click="openPasswordDialog(item)"
                    ></v-btn>
                    <v-btn
                        v-tooltip:top="'Edit Administrator'"
                        icon="mdi-pencil-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="info"
                        @click="openFormDialog(item)"
                    ></v-btn>
                    <v-btn
                        v-tooltip:top="'Hapus Administrator'"
                        icon="mdi-trash-can-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="error"
                        @click="deleteAdministrator(item)"
                    ></v-btn>
                </div>
            </template>
        </BaseDataTable>

        <AdministratorForm ref="formDialogRef" @saved="refreshTable" />
        <AdministratorPasswordForm
            ref="passwordDialogRef"
            @saved="refreshTable"
        />
        <BaseConfirm ref="confirmDialogRef" />
    </div>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import type { Administrator } from "@/admin/types/administrator";
import administratorService from "@/admin/services/administrator.service";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import AdministratorForm from "@/admin/components/system/AdministratorForm.vue";
import AdministratorPasswordForm from "@/admin/components/system/AdministratorPasswordForm.vue";
import BaseConfirm from "@/shared/components/BaseConfirm.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime } = useFormatter();
const snackbar = useSnackbarStore();

const search = ref("");
const dataTableRef = ref<InstanceType<typeof BaseDataTable> | null>(null);
const formDialogRef = ref<InstanceType<typeof AdministratorForm> | null>(null);
const passwordDialogRef = ref<InstanceType<
    typeof AdministratorPasswordForm
> | null>(null);
const confirmDialogRef = ref<InstanceType<typeof BaseConfirm> | null>(null);

const getInitials = (name: string): string => {
    if (!name) return "?";
    return name
        .split(" ")
        .map((w) => w[0])
        .slice(0, 2)
        .join("")
        .toUpperCase();
};

const headers = [
    {
        title: "Profil",
        key: "image_url",
        align: "center",
        sortable: false,
        width: "70px",
    },
    {
        title: "Username",
        key: "username",
        type: "text",
        align: "left",
        sortable: false,
        search: true,
        filter: true,
        placeholder: "Masukkan Username Administrator",
    },
    {
        title: "Nama & Email",
        key: "name",
        type: "text",
        align: "left",
        sortable: false,
        search: true,
        filter: true,
        filterLabel: "Nama",
        placeholder: "Masukkan Nama Administrator",
    },
    {
        title: "Email",
        key: "email",
        type: "text",
        align: "left",
        visible: false,
        sortable: false,
        search: true,
        filter: true,
        placeholder: "Masukkan Email Administrator",
    },
    { title: "Role", key: "role", align: "center", sortable: false },
    {
        title: "Status",
        key: "is_active",
        type: "select",
        align: "center",
        sortable: false,
        filter: true,
        options: [
            { value: "1", label: "Aktif" },
            { value: "0", label: "Nonaktif" },
        ],
        placeholder: "Pilih Status",
    },
    {
        title: "Terakhir Login",
        key: "last_login",
        type: "date",
        align: "left",
        sortable: false,
        filter: true,
    },
    { title: "Aksi", key: "actions", align: "center", sortable: false },
];

const refreshTable = () => {
    dataTableRef.value?.refresh();
};

const openFormDialog = (item?: Administrator) => {
    formDialogRef.value?.open(item);
};

const openPasswordDialog = (item: Administrator) => {
    passwordDialogRef.value?.open(item);
};

const deleteAdministrator = async (item: Administrator) => {
    if (!confirmDialogRef.value) return;

    const isConfirmed = await confirmDialogRef.value.open({
        title: "Hapus Administrator",
        message: `Apakah Anda yakin ingin menghapus administrator "${item.name}" (${item.username})? Tindakan ini tidak dapat dibatalkan.`,
        confirmText: "Ya, Hapus",
        confirmColor: "error",
        icon: "mdi-account-remove",
    });

    if (isConfirmed) {
        try {
            await administratorService.deleteAdministrator(item.id);
            snackbar.showMessage("Administrator berhasil dihapus", "success");
            refreshTable();
        } catch (error: any) {
            const message =
                error.response?.data?.message ||
                "Gagal menghapus administrator";
            snackbar.showMessage(message, "error");
        }
    }
};
</script>
