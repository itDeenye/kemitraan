<template>
    <div>
        <div class="d-flex align-center justify-space-between mb-4">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Stok Produk</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Halaman untuk memantau ketersediaan stok produk mitra.
                </p>
            </div>
        </div>

        <v-row class="mb-4">
            <v-col cols="12" sm="8" md="6" lg="4">
                <v-autocomplete
                    v-model="selectedMember"
                    :items="members"
                    item-title="name"
                    item-value="id"
                    label="Pilih Mitra"
                    variant="outlined"
                    density="comfortable"
                    :loading="loadingMembers"
                    @update:search="searchMembers"
                    return-object
                    placeholder="Ketik nama mitra..."
                    hide-details
                    bg-color="surface"
                >
                    <template v-slot:item="{ props, item }">
                        <v-list-item
                            v-bind="props"
                            :title="item.raw.name"
                            :subtitle="item.raw.code"
                        >
                        </v-list-item>
                    </template>
                </v-autocomplete>
            </v-col>
        </v-row>

        <BaseDataTable
            v-if="selectedMember"
            ref="tableRef"
            :url="`/admin/partnership/member-stocks?filter[member_id]=${selectedMember.id}`"
            :headers="headers"
            v-model:search="search"
        >
            <template #item.product.code="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="text-body-2 font-weight-bold">
                        {{ item.product.code || "-" }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ item.product.name || "-" }}
                    </div>
                </div>
            </template>

            <template #item.product.category_name="{ item }">
                <BaseBadge
                    type="category"
                    :value="item.product?.category_name"
                    inline
                />
            </template>

            <template #item.transfer_in="{ item }">
                <span
                    class="font-weight-bold"
                    :class="
                        Number(item.transfer_in) > 0
                            ? 'text-success'
                            : 'text-medium-emphasis'
                    "
                >
                    {{ formatPrice(item.transfer_in) }}
                </span>
                <span class="text-caption text-medium-emphasis ml-1">
                    {{ item.product?.unit || "pcs" }}
                </span>
            </template>

            <template #item.transfer_out="{ item }">
                <span
                    class="font-weight-bold"
                    :class="
                        Number(item.transfer_out) > 0
                            ? 'text-error'
                            : 'text-medium-emphasis'
                    "
                >
                    {{ formatPrice(item.transfer_out) }}
                </span>
                <span class="text-caption text-medium-emphasis ml-1">
                    {{ item.product?.unit || "pcs" }}
                </span>
            </template>

            <template #item.balance="{ item }">
                <span class="font-weight-bold">{{
                    formatPrice(item.balance)
                }}</span>
                <span class="text-caption text-medium-emphasis ml-1">
                    {{ item.product?.unit || "pcs" }}
                </span>
            </template>
        </BaseDataTable>

        <v-card
            v-else
            variant="outlined"
            class="pa-10 text-center bg-grey-lighten-4 border-dashed"
        >
            <v-icon size="64" color="grey-lighten-1" class="mb-4"
                >mdi-package-variant</v-icon
            >
            <h3 class="text-h6 font-weight-medium text-grey-darken-1">
                Pilih Mitra Terlebih Dahulu
            </h3>
            <p class="text-body-2 text-medium-emphasis mt-2">
                Gunakan pencarian di atas untuk memilih mitra, lalu data stok
                akan otomatis ditampilkan.
            </p>
        </v-card>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import api from "@/shared/services/api";
import { useFormatter } from "@/shared/composables/useFormatter";
import categoryService from "@/admin/services/category.service";

const { formatDateTime, formatPrice } = useFormatter();

const search = ref("");
const tableRef = ref();
const selectedMember = ref<any>(null);

const headers = ref<any[]>([
    {
        title: "Produk",
        key: "product.code",
        type: "text",
        align: "left",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Produk",
    },
    {
        title: "Produk",
        key: "product.name",
        type: "text",
        align: "left",
        visible: false,
        sortable: false,
        filter: true,
        placeholder: "Masukkan Nama Produk",
    },
    {
        title: "Kategori",
        key: "product.category_name",
        filterKey: "category_id",
        align: "center",
        sortable: false,
        filter: true,
        type: "select",
        filterLabel: "Kategori",
        placeholder: "Pilih Kategori",
        options: [],
    },
    {
        title: "Stok Mengambang Masuk",
        key: "transfer_in",
        align: "right",
        sortable: false,
    },
    {
        title: "Stok Mengambang Keluar",
        key: "transfer_out",
        align: "right",
        sortable: false,
    },
    {
        title: "Sisa Stok",
        key: "balance",
        align: "right",
        sortable: false,
    },
]);

// Options for autocomplete
const members = ref<any[]>([]);
const loadingMembers = ref(false);
let searchTimeout: any = null;

const fetchMembers = async (query = "") => {
    loadingMembers.value = true;
    try {
        const { data } = await api.get("/admin/partnership/members", {
            params: { search: query, limit: 10 },
        });
        members.value = data.data?.results || [];
    } catch (e) {
        console.error("Failed to fetch members", e);
    } finally {
        loadingMembers.value = false;
    }
};

const searchMembers = (query: string) => {
    if (query && query.length < 3) return;
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchMembers(query), 500);
};

onMounted(async () => {
    fetchMembers();

    try {
        const categories = await categoryService.getCategories({ limit: 100 });
        const categoryCol = headers.value.find(
            (h) => h.key === "product.category_name",
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
</script>

<style scoped>
.border-dashed {
    border-style: dashed !important;
}
</style>
