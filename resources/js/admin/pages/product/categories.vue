<template>
    <div>
        <div class="d-flex align-center justify-space-between mb-4">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Kategori Produk</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk mengelola data kategori produk DNY Skincare.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="categoryTable"
            url="/admin/product/categories"
            :headers="headers"
            v-model:search="search"
        >
            <template #header-actions>
                <v-btn
                    color="primary"
                    prepend-icon="mdi-shape-plus-outline"
                    @click="openFormDialog()"
                    variant="outlined"
                    class="bg-surface rounded-lg"
                >
                    Tambah Kategori
                </v-btn>
            </template>

            <template #item.is_active="{ item }">
                <BaseBadge type="status" :value="item.is_active" />
            </template>

            <template #item.actions="{ item }">
                <div class="d-flex ga-2">
                    <v-btn
                        v-tooltip:top="'Naikkan Urutan'"
                        icon="mdi-arrow-up"
                        variant="text"
                        size="small"
                        color="success"
                        :loading="isReorderingCategory(item, 'up')"
                        :disabled="
                            reorderingCategory !== null || item.sort_order <= 1
                        "
                        @click="handleMoveOrder(item, 'up')"
                    ></v-btn>
                    <v-btn
                        v-tooltip:top="'Turunkan Urutan'"
                        icon="mdi-arrow-down"
                        variant="text"
                        size="small"
                        color="primary"
                        :loading="isReorderingCategory(item, 'down')"
                        :disabled="
                            reorderingCategory !== null || isLastCategory(item)
                        "
                        @click="handleMoveOrder(item, 'down')"
                    ></v-btn>
                    <v-btn
                        v-tooltip:top="'Edit Kategori'"
                        icon="mdi-pencil-outline"
                        variant="outlined"
                        size="small"
                        color="info"
                        class="rounded"
                        :disabled="reorderingCategory !== null"
                        @click="openFormDialog(item)"
                    ></v-btn>
                    <v-btn
                        v-tooltip:top="'Hapus Kategori'"
                        icon="mdi-trash-can-outline"
                        variant="outlined"
                        size="small"
                        color="error"
                        class="rounded"
                        :disabled="reorderingCategory !== null"
                        @click="confirmDelete(item)"
                    ></v-btn>
                </div>
            </template>
        </BaseDataTable>

        <CategoryForm ref="categoryFormRef" @saved="refreshTable" />

        <BaseConfirm ref="confirmDialogRef" />
    </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseConfirm from "@/shared/components/BaseConfirm.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import CategoryForm from "@/admin/components/product/CategoryForm.vue";
import categoryService from "@/admin/services/category.service";
import type { Category } from "@/admin/types/category";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const snackbar = useSnackbarStore();

const search = ref("");
const categoryTable = ref<InstanceType<typeof BaseDataTable> | null>(null);
const categoryFormRef = ref<InstanceType<typeof CategoryForm> | null>(null);
const confirmDialogRef = ref<InstanceType<typeof BaseConfirm> | null>(null);
const totalCategories = ref(0);
const reorderingCategory = ref<{
    id: number;
    direction: "up" | "down";
} | null>(null);

const headers = [
    {
        title: "Nama Kategori",
        key: "name",
        align: "start",
        sortable: true,
        search: true,
        filter: true,
        type: "text",
        filterLabel: "Nama",
        placeholder: "Masukkan Nama Kategori",
    },
    {
        title: "Deskripsi",
        key: "description",
        align: "start",
        sortable: false,
        search: true,
    },
    {
        title: "Jumlah Produk",
        key: "product_count",
        align: "right",
        sortable: true,
    },
    {
        title: "Status",
        key: "is_active",
        align: "center",
        sortable: false,
        filter: true,
        type: "select",
        filterLabel: "Status",
        placeholder: "Pilih Status",
        options: [
            { label: "Aktif", value: 1 },
            { label: "Nonaktif", value: 0 },
        ],
    },
    {
        title: "Aksi",
        key: "actions",
        align: "center",
        sortable: false,
        width: 220,
    },
];

const openFormDialog = (category?: Category) => {
    categoryFormRef.value?.open(category);
};

const refreshCategoryCount = async () => {
    try {
        const categories = await categoryService.getCategories({
            pagination: false,
        });
        totalCategories.value = categories.length;
    } catch (error) {
        console.error("Gagal memuat jumlah kategori", error);
    }
};

const refreshTable = () => {
    categoryTable.value?.refresh();
    void refreshCategoryCount();
};

const isLastCategory = (category: Category) => {
    return (
        totalCategories.value > 0 &&
        category.sort_order >= totalCategories.value
    );
};

const isReorderingCategory = (category: Category, direction: "up" | "down") => {
    return (
        reorderingCategory.value?.id === category.id &&
        reorderingCategory.value.direction === direction
    );
};

const handleMoveOrder = async (
    category: Category,
    direction: "up" | "down",
) => {
    if (
        reorderingCategory.value !== null ||
        (direction === "up" && category.sort_order <= 1) ||
        (direction === "down" && isLastCategory(category))
    ) {
        return;
    }

    reorderingCategory.value = { id: category.id, direction };

    try {
        if (direction === "up") {
            await categoryService.moveCategoryUp(category.id);
        } else {
            await categoryService.moveCategoryDown(category.id);
        }

        refreshTable();
        snackbar.showMessage("Urutan kategori berhasil diubah", "success");
    } catch (error: any) {
        console.error("Gagal mengubah urutan kategori", error);
        snackbar.showMessage(
            error.response?.data?.message || "Gagal mengubah urutan kategori",
            "error",
        );
    } finally {
        reorderingCategory.value = null;
    }
};

const confirmDelete = async (category: Category) => {
    const isConfirmed = await confirmDialogRef.value?.open({
        title: "Konfirmasi Hapus",
        message: `Apakah Anda yakin ingin menghapus kategori <b>${category.name}</b>?`,
        confirmText: "Ya, Hapus",
        confirmColor: "error",
        icon: "mdi-delete-alert",
    });

    if (isConfirmed) {
        try {
            await categoryService.deleteCategory(category.id);
            refreshTable();
            snackbar.showMessage("Berhasil menghapus kategori", "success");
        } catch (error) {
            console.error("Gagal menghapus kategori", error);
            snackbar.showMessage("Gagal menghapus kategori", "error");
        }
    }
};

onMounted(refreshCategoryCount);
</script>
