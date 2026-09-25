<template>
    <div>
        <div
            class="d-flex align-center justify-space-between mb-6 flex-wrap ga-4"
        >
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">
                    {{ isMutationMode ? "Riwayat Mutasi Stok" : "Stok Produk" }}
                </h1>
                <p class="text-body-2 text-medium-emphasis">
                    <template v-if="isMutationMode">
                        Riwayat mutasi stok untuk produk
                        <span class="font-weight-bold">{{
                            activeProductName
                        }}</span>
                    </template>
                    <template v-else>
                        Halaman pemantauan data dan riwayat mutasi stok produk
                        perusahaan.
                    </template>
                </p>
            </div>
            <div v-if="isMutationMode">
                <v-btn
                    color="primary"
                    prepend-icon="mdi-arrow-left"
                    variant="outlined"
                    class="bg-surface rounded-lg"
                    @click="closeMutations"
                >
                    Kembali ke Stok
                </v-btn>
            </div>
        </div>

        <BaseDataTable
            ref="stockTable"
            :key="isMutationMode ? 'mutation' : 'stock'"
            :url="tableUrl"
            :headers="currentHeaders"
            v-model:search="search"
        >
            <template #item.code="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-bold">
                        {{ item.product?.code || "-" }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ item.product?.name || "-" }}
                    </div>
                </div>
            </template>

            <template #item.category_id="{ item }">
                <v-chip size="small" variant="tonal" color="primary">
                    {{ item.product?.category_name || "-" }}
                </v-chip>
            </template>

            <template #item.balance="{ item }">
                <span class="font-weight-bold">
                    {{ formatPrice(item.balance) }}
                </span>
            </template>

            <template #item.actions="{ item }">
                <div class="d-flex ga-2 justify-center">
                    <v-btn
                        v-tooltip:top="'Detail Stok'"
                        icon="mdi-eye-outline"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="primary"
                        @click="openDetail(item.id)"
                    />
                    <v-btn
                        v-tooltip:top="'Riwayat Mutasi'"
                        icon="mdi-history"
                        variant="outlined"
                        size="small"
                        class="rounded"
                        color="info"
                        @click="openMutations(item)"
                    />
                </div>
            </template>

            <template #item.happened_at="{ item }">
                {{ item.happened_at ? formatDateTime(item.happened_at) : "-" }}
            </template>
            <template #item.type="{ item }">
                <v-chip
                    size="small"
                    :color="item.type === 'in' ? 'success' : 'error'"
                    variant="tonal"
                >
                    {{ item.type === "in" ? "Masuk" : "Keluar" }}
                </v-chip>
            </template>
            <template #item.quantity="{ item }">
                <span class="font-weight-bold"
                    >{{ formatPrice(item.quantity || 0) }}
                </span>
            </template>
            <template #item.mutation_balance="{ item }">
                <span class="font-weight-bold">
                    {{ formatPrice(item.balance || 0) }}
                </span>
            </template>
        </BaseDataTable>

        <StockDetail ref="stockDetailRef" />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import StockDetail from "@/admin/components/inventory/StockDetail.vue";
import categoryService from "@/admin/services/category.service";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime, formatPrice } = useFormatter();

const search = ref("");
const stockTable = ref();
const stockDetailRef = ref();

const isMutationMode = ref(false);
const activeWarehouseId = ref<number | null>(null);
const activeProductId = ref<number | null>(null);
const activeProductName = ref("");

const tableUrl = computed(() => {
    if (isMutationMode.value) {
        return `/admin/inventory/mutations?filter[warehouse_id]=${activeWarehouseId.value}&filter[product_id]=${activeProductId.value}`;
    }
    return "/admin/inventory/stocks";
});

const stockHeaders = ref<any[]>([
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
        title: "Sisa Stok",
        key: "balance",
        align: "right",
        sortable: false,
    },
    {
        title: "Aksi",
        key: "actions",
        align: "center",
        sortable: false,
        width: "120px",
    },
]);

const mutationHeaders = ref<any[]>([
    {
        title: "Tanggal",
        key: "happened_at",
        align: "left",
        sortable: false,
        filter: true,
        type: "date",
    },
    {
        title: "Tipe Mutasi",
        key: "type",
        type: "select",
        align: "center",
        sortable: false,
        filter: true,
        options: [
            { label: "Masuk", value: "in" },
            { label: "Keluar", value: "out" },
        ],
        placeholder: "Pilih Tipe",
    },
    { title: "Qty", key: "quantity", align: "right", sortable: false },
    {
        title: "Sisa Stok",
        key: "mutation_balance",
        align: "right",
        sortable: false,
    },
    { title: "Catatan", key: "note", align: "left", sortable: false },
]);

const currentHeaders = computed(() => {
    return isMutationMode.value ? mutationHeaders.value : stockHeaders.value;
});

onMounted(async () => {
    try {
        const categories = await categoryService.getCategories({ limit: 100 });
        const categoryCol = stockHeaders.value.find(
            (h) => h.key === "category_id",
        );
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

const openMutations = (item: any) => {
    activeWarehouseId.value = item.warehouse?.id;
    activeProductId.value = item.product?.id;
    activeProductName.value = item.product?.name || "";
    search.value = "";
    isMutationMode.value = true;
};

const closeMutations = () => {
    isMutationMode.value = false;
    activeWarehouseId.value = null;
    activeProductId.value = null;
    activeProductName.value = "";
    search.value = "";
};

const openDetail = (id: number) => {
    if (stockDetailRef.value) {
        stockDetailRef.value.open(id);
    }
};
</script>
