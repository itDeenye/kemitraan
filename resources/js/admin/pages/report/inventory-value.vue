<template>
    <div class="mb-6 d-flex flex-column">
        <h1 class="text-h4 font-weight-bold mb-1">Laporan Stok</h1>
        <p class="text-body-2 text-medium-emphasis">
            Halaman untuk melihat laporan stok barang yang tersedia di gudang.
        </p>
    </div>

    <BaseDataTable
        ref="inventoryValueTable"
        url="/admin/reports/stocks"
        :headers="headers"
        :query-params="extraParams"
    >
        <template #header-actions>
            <div class="d-flex align-center ga-3">
                <div
                    class="d-flex align-center ga-2 bg-surface pa-1 rounded-lg border"
                >
                    <span class="text-caption font-weight-medium px-2 pt-2"
                        >Dari:</span
                    >
                    <v-text-field
                        v-model="dateFrom"
                        type="date"
                        variant="plain"
                        density="compact"
                        hide-details
                        style="width: 130px"
                    ></v-text-field>
                </div>

                <div
                    class="d-flex align-center ga-2 bg-surface pa-1 rounded-lg border"
                >
                    <span class="text-caption font-weight-medium px-2 pt-2"
                        >Sampai:</span
                    >
                    <v-text-field
                        v-model="dateTo"
                        type="date"
                        variant="plain"
                        density="compact"
                        hide-details
                        style="width: 130px"
                    ></v-text-field>
                </div>
            </div>
        </template>

        <template #item.product.name="{ item }">
            <div class="d-flex flex-column py-2">
                <div class="text-body-2 font-weight-bold">
                    {{ item.product?.name || "-" }}
                </div>
                <div class="text-caption text-medium-emphasis">
                    {{ item.product?.code || "-" }}
                </div>
            </div>
        </template>

        <template #item.category_id="{ item }">
            <BaseBadge
                type="category"
                :value="item.product?.category?.name"
                inline
            />
        </template>

        <template #item.starting_balance="{ item }">
            <span class="font-weight-medium">{{
                formatPrice(item.starting_balance)
            }}</span>
        </template>

        <template #item.stock_in="{ item }">
            <span class="font-weight-medium">{{
                formatPrice(item.stock_in)
            }}</span>
        </template>

        <template #item.stock_out="{ item }">
            <span class="font-weight-medium">{{
                formatPrice(item.stock_out)
            }}</span>
        </template>

        <template #item.ending_balance="{ item }">
            <span class="font-weight-bold">{{
                formatPrice(item.ending_balance)
            }}</span>
        </template>
    </BaseDataTable>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useDateRange } from "@/shared/composables/useDateRange";
import categoryService from "@/admin/services/category.service";

const { formatPrice } = useFormatter();

const { dateFrom, dateTo } = useDateRange();

const inventoryValueTable = ref<InstanceType<typeof BaseDataTable> | null>(
    null,
);

const extraParams = computed(() => ({
    date_from: dateFrom.value,
    date_to: dateTo.value,
}));

const headers = ref<any[]>([
    {
        title: "Produk",
        key: "product.name",
        type: "text",
        align: "start",
        sortable: false,
        filter: true,
        filterLabel: "Nama Produk",
        placeholder: "Masukkan Nama Produk",
    },
    {
        title: "Kode Produk",
        key: "product.code",
        type: "text",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Produk",
    },
    {
        title: "Kategori",
        key: "category_id",
        align: "center",
        sortable: false,
        filter: true,
        type: "select",
        filterLabel: "Kategori",
        placeholder: "Pilih Kategori",
        options: [],
    },
    {
        title: "Stok Awal",
        key: "starting_balance",
        align: "right",
        sortable: false,
    },
    {
        title: "Stok Masuk",
        key: "stock_in",
        align: "right",
        sortable: false,
    },
    {
        title: "Stok Keluar",
        key: "stock_out",
        align: "right",
        sortable: false,
    },
    {
        title: "Stok Akhir",
        key: "ending_balance",
        align: "right",
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
</script>
