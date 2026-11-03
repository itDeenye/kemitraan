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
            <v-col cols="12" sm="8" md="6" lg="5">
                <v-autocomplete
                    v-model="selectedMember"
                    :items="members"
                    item-title="name"
                    item-value="id"
                    label="Pilih Mitra"
                    variant="outlined"
                    density="comfortable"
                    :loading="loadingMembers"
                    @update:search="(val) => searchMembers(val, selectedMember)"
                    @click:clear="onClearMember"
                    return-object
                    clearable
                    placeholder="Ketik nama atau kode mitra..."
                    persistent-placeholder
                    hide-details
                    bg-color="surface"
                    :custom-filter="customFilter"
                >
                    <template v-slot:item="{ props, item }">
                        <v-list-item v-bind="props" :title="item.raw.name">
                            <template #subtitle>
                                <div class="d-flex align-center ga-2 mt-1">
                                    <span
                                        class="font-weight-medium text-caption"
                                        >{{ item.raw.code }}</span
                                    >
                                    <span
                                        v-if="item.raw.level?.name"
                                        class="text-caption text-medium-emphasis"
                                    >
                                        &bull; {{ item.raw.level.name }}
                                    </span>
                                </div>
                            </template>
                        </v-list-item>
                    </template>

                    <template v-slot:selection="{ item }">
                        <span
                            class="text-body-2 font-weight-medium text-truncate"
                        >
                            {{ item.raw.name }}
                            <span
                                class="text-caption text-medium-emphasis ml-1 font-weight-regular"
                            >
                                ({{ item.raw.code }})
                            </span>
                        </span>
                    </template>

                    <template #no-data>
                        <div
                            v-if="loadingMembers"
                            class="d-flex align-center justify-center py-4 ga-2 text-primary"
                        >
                            <v-progress-circular
                                indeterminate
                                size="20"
                                width="2"
                                color="primary"
                            ></v-progress-circular>
                            <span class="text-body-2 font-weight-medium">
                                Mencari data mitra...
                            </span>
                        </div>
                        <div
                            v-else-if="
                                !searchQuery || searchQuery.trim().length < 3
                            "
                            class="d-flex align-center justify-center py-4 px-2 ga-2 text-medium-emphasis text-center"
                        >
                            <v-icon size="18" color="medium-emphasis"
                                >mdi-magnify</v-icon
                            >
                            <span class="text-caption">
                                Ketik minimal 3 karakter (nama atau kode) untuk
                                mencari mitra
                            </span>
                        </div>
                        <div
                            v-else
                            class="d-flex align-center justify-center py-4 px-2 ga-2 text-medium-emphasis text-center"
                        >
                            <v-icon size="18" color="medium-emphasis"
                                >mdi-account-search-outline</v-icon
                            >
                            <span class="text-caption">
                                Data mitra tidak ditemukan
                            </span>
                        </div>
                    </template>
                </v-autocomplete>
            </v-col>
        </v-row>

        <BaseDataTable
            v-if="selectedMember"
            :key="selectedMember.id"
            ref="tableRef"
            url="/admin/partnership/member-stocks"
            :query-params="{ 'filter[member_id]': selectedMember.id }"
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
import { useFormatter } from "@/shared/composables/useFormatter";
import { useMemberSearch } from "@/admin/composables/useMemberSearch";
import categoryService from "@/admin/services/category.service";

const { formatDateTime, formatPrice } = useFormatter();

const search = ref("");
const tableRef = ref();
const selectedMember = ref<any>(null);

const {
    members,
    loadingMembers,
    searchQuery,
    searchMembers,
    clearMembers,
    customFilter,
} = useMemberSearch({ limit: 20, minQueryLength: 3, onDemand: true });

const onClearMember = () => {
    selectedMember.value = null;
    clearMembers();
};

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

onMounted(async () => {
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
