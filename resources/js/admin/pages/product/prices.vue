<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-6 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Harga Produk</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk mengelola data harga produk DNY Skincare.
                </p>
            </div>
        </div>

        <BaseDataTable
            ref="priceTable"
            url="/admin/product/prices"
            :headers="headers"
            v-model:search="search"
            :multiple-select="true"
            :return-object="true"
            v-model:selected="selectedProducts"
        >
            <template #header-actions>
                <v-btn
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-cash-multiple"
                    :disabled="selectedProducts.length === 0"
                    @click="openEditDialog(selectedProducts)"
                >
                    Ubah Harga Massal
                    <v-badge
                        v-if="selectedProducts.length > 0"
                        :content="selectedProducts.length"
                        color="error"
                        inline
                        class="ml-2"
                    ></v-badge>
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

            <template #item.category_id="{ item }">
                <BaseBadge type="category" :value="item.category?.name" />
            </template>

            <template #item.customer_price="{ item }">
                <span class="font-weight-bold">
                    {{ formatPrice(item.customer_price || 0) }}
                </span>
            </template>

            <template #item.member_prices="{ item }">
                <div v-if="item.member_prices && item.member_prices.length > 0">
                    <div
                        v-for="mp in item.member_prices"
                        :key="mp.member_level_id"
                        class="text-caption"
                    >
                        <span class="text-medium-emphasis">{{ mp.name }}:</span>
                        <span class="font-weight-medium ml-1"
                            >Rp{{ formatPrice(mp.price) }}</span
                        >
                    </div>
                </div>
                <span v-else class="text-caption text-medium-emphasis">-</span>
            </template>

            <template #item.is_active="{ item }">
                <BaseBadge type="status" :value="item.is_active" />
            </template>

            <template #item.actions="{ item }">
                <div class="d-flex ga-2 justify-center">
                    <v-btn
                        v-tooltip:top="'Edit Harga'"
                        icon="mdi-pencil-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="info"
                        @click="openEditDialog(item)"
                    />
                </div>
            </template>
        </BaseDataTable>

        <!-- Modals -->
        <ProductPriceForm ref="priceFormRef" @saved="refreshTable" />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import ProductPriceForm from "@/admin/components/product/ProductPriceForm.vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import categoryService from "@/admin/services/category.service";

const { formatPrice } = useFormatter();

const search = ref("");
const priceTable = ref();
const priceFormRef = ref();
const selectedProducts = ref<any[]>([]);

const openEditDialog = (itemOrItems: any | any[]) => {
    priceFormRef.value?.open(itemOrItems);
};

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
        key: "category_id",
        sortable: false,
        filter: true,
        type: "select",
        filterLabel: "Kategori",
        placeholder: "Pilih Kategori",
        options: [],
    },
    {
        title: "Harga Pelanggan (Rp)",
        align: "right",
        key: "customer_price",
        sortable: false,
    },
    {
        title: "Harga Mitra",
        align: "left",
        key: "member_prices",
        sortable: false,
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
    },
]);

onMounted(async () => {
    try {
        const categories = await categoryService.getCategories({ limit: 100 });
        const categoryCol = headers.value.find((h) => h.key === "category_id");
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

const refreshTable = () => {
    selectedProducts.value = [];
    if (priceTable.value) {
        priceTable.value.refresh();
    }
};
</script>
