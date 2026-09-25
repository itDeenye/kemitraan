<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-6 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Data Produk</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk mengelola data produk DNY Skincare.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="productTable"
            url="/admin/products"
            :headers="headers"
            v-model:search="search"
        >
            <template #header-actions>
                <v-btn
                    color="primary"
                    prepend-icon="mdi-package-variant-closed-plus"
                    @click="openFormDialog()"
                    variant="outlined"
                    class="bg-surface rounded-lg"
                >
                    Tambah Produk
                </v-btn>
            </template>

            <template #item.code="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-bold">
                        {{ item.code || "-" }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ item.name || "-" }}
                    </div>
                </div>
            </template>

            <template #item.category="{ item }">
                <BaseBadge
                    type="category"
                    :value="item.category?.name"
                    inline
                />
            </template>

            <template #item.is_active="{ item }">
                <BaseBadge type="status" :value="item.is_active" />
            </template>

            <template #item.actions="{ item }">
                <div class="d-flex ga-2">
                    <v-btn
                        v-tooltip:top="'Detail Produk'"
                        icon="mdi-eye-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="primary"
                        @click="openDetailDialog(item)"
                    />
                    <v-btn
                        v-tooltip:top="'Edit Produk'"
                        icon="mdi-pencil-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="info"
                        @click="openFormDialog(item)"
                    />
                    <v-btn
                        v-tooltip:top="'Hapus Produk'"
                        icon="mdi-trash-can-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="error"
                        @click="deleteProduct(item)"
                    />
                </div>
            </template>
        </BaseDataTable>

        <!-- Modals -->
        <ProductForm ref="productFormRef" @saved="refreshTable" />
        <ProductDetail ref="productDetailRef" />
        <BaseConfirm ref="confirmDialogRef" />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseConfirm from "@/shared/components/BaseConfirm.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import ProductForm from "@/admin/components/product/ProductForm.vue";
import ProductDetail from "@/admin/components/product/ProductDetail.vue";
import productService from "@/admin/services/product.service";
import categoryService from "@/admin/services/category.service";
import type { Product } from "@/admin/types/product";
import { useBadge } from "@/shared/composables/useBadge";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const snackbar = useSnackbarStore();

const search = ref("");
const productTable = ref<InstanceType<typeof BaseDataTable> | null>(null);
const productFormRef = ref<InstanceType<typeof ProductForm> | null>(null);
const productDetailRef = ref<InstanceType<typeof ProductDetail> | null>(null);
const confirmDialogRef = ref<InstanceType<typeof BaseConfirm> | null>(null);

const headers = ref<any[]>([
    {
        title: "Produk",
        key: "code",
        type: "text",
        align: "left",
        sortable: false,
        search: true,
        filter: true,
        filterLabel: "Kode Produk",
        placeholder: "Masukkan Kode Produk",
    },
    {
        title: "Nama Produk",
        key: "name",
        type: "text",
        align: "left",
        visible: false,
        sortable: false,
        search: true,
        filter: true,
        filterLabel: "Nama Produk",
        placeholder: "Masukkan Nama Produk",
    },
    {
        title: "Kategori",
        align: "center",
        key: "category",
        filterKey: "category_id",
        sortable: false,
        filter: true,
        type: "select",
        filterLabel: "Kategori",
        placeholder: "Pilih Kategori",
        options: [],
    },
    {
        title: "Status",
        key: "is_active",
        align: "center",
        sortable: false,
        filter: true,
        type: "select",
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
    },
]);

onMounted(async () => {
    try {
        const categories = await categoryService.getCategories({ limit: 100 });
        const categoryCol = headers.value.find((h) => h.key === "category");
        if (categoryCol) {
            categoryCol.options = categories.map((c: any) => ({
                label: c.name,
                value: c.id,
            }));
        }
    } catch (error) {
        console.error("Gagal memuat filter kategori", error);
    }
});

const openFormDialog = (product?: Product) => {
    productFormRef.value?.open(product);
};

const openDetailDialog = (product: Product) => {
    productDetailRef.value?.open(product.id);
};

const deleteProduct = async (product: any) => {
    if (!confirmDialogRef.value) return;

    const isConfirmed = await confirmDialogRef.value.open({
        title: "Hapus Produk",
        message: `Apakah Anda yakin ingin menghapus produk <b>${product.name}</b> ini? Tindakan ini tidak dapat dibatalkan.`,
        confirmText: "Ya, Hapus",
        confirmColor: "error",
        icon: "mdi-delete-alert",
    });

    if (isConfirmed) {
        try {
            await productService.deleteProduct(product.id);
            refreshTable();
            snackbar.showMessage("Berhasil menghapus produk", "success");
        } catch (error) {
            console.error("Gagal menghapus produk", error);
            snackbar.showMessage("Gagal menghapus produk", "error");
        }
    }
};

const refreshTable = () => {
    if (productTable.value) {
        productTable.value.refresh();
    }
};
</script>
