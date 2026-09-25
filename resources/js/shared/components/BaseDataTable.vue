<template>
    <div
        class="base-data-table"
        :class="{ 'is-light-mode': !$vuetify.theme.current.dark }"
    >
        <!-- Header (Toolbar) -->
        <div
            class="d-flex align-center justify-space-between mb-4 flex-wrap ga-4"
            v-if="showSearch || $slots['header-actions'] || showViewColumns"
        >
            <!-- Left Actions (e.g. Tambah) -->
            <div class="d-flex align-center gap-2 flex-wrap">
                <slot name="header-actions"></slot>
            </div>

            <!-- Right Actions (Search, Filter, View) -->
            <div class="d-flex align-center gap-3 flex-wrap">
                <!-- Search Box -->
                <v-text-field
                    v-if="showSearch"
                    v-model="internalSearch"
                    prepend-inner-icon="mdi-magnify"
                    placeholder="Cari data..."
                    variant="outlined"
                    density="compact"
                    hide-details
                    class="search-input bg-surface rounded-lg"
                    clearable
                    @update:model-value="onSearchInput"
                ></v-text-field>

                <!-- Filter Button -->
                <v-btn
                    v-if="hasFilterableColumns"
                    variant="outlined"
                    color="medium-emphasis"
                    prepend-icon="mdi-filter-variant"
                    class="text-none bg-surface rounded-lg"
                    @click="filterDialogOpen = true"
                >
                    Filter
                    <v-badge
                        v-if="activeFilterCount > 0"
                        :content="activeFilterCount"
                        color="primary"
                        inline
                        class="ml-1"
                    />
                </v-btn>

                <!-- View Columns (Hide/Show) -->
                <v-menu v-if="showViewColumns" :close-on-content-click="false">
                    <template v-slot:activator="{ props: menuProps }">
                        <v-btn
                            v-bind="menuProps"
                            variant="outlined"
                            color="medium-emphasis"
                            prepend-icon="mdi-eye-outline"
                            class="text-none bg-surface rounded-lg"
                        >
                            View
                        </v-btn>
                    </template>
                    <v-list density="compact" class="pa-2" min-width="200">
                        <v-list-item-title
                            class="text-caption font-weight-bold mb-2 px-2 text-medium-emphasis"
                            >TAMPILKAN KOLOM</v-list-item-title
                        >
                        <v-list-item
                            v-for="col in viewableHeaders"
                            :key="col.key"
                        >
                            <template v-slot:prepend>
                                <v-checkbox-btn
                                    v-model="visibleColumns"
                                    :value="col.key"
                                    color="primary"
                                    density="compact"
                                ></v-checkbox-btn>
                            </template>
                            <v-list-item-title class="text-body-2">{{
                                col.title
                            }}</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>
            </div>
        </div>

        <!-- Active Filter Badges -->
        <div
            v-if="activeFilterCount > 0"
            class="pb-3 d-flex flex-wrap"
            style="gap: 8px"
        >
            <v-chip
                v-for="(value, key) in currentFilters"
                :key="key"
                size="small"
                closable
                variant="tonal"
                color="primary"
                @click:close="removeFilter(String(key))"
            >
                {{ getFilterLabel(String(key)) }}:
                {{ getFilterDisplayValue(String(key), value) }}
            </v-chip>
            <v-chip
                v-if="activeFilterCount > 1"
                size="small"
                variant="outlined"
                color="error"
                @click="clearAllFilters"
                prepend-icon="mdi-close-circle"
            >
                Hapus Semua
            </v-chip>
        </div>

        <!-- Filter Dialog -->
        <FilterDialog
            v-model="filterDialogOpen"
            :columns="headers"
            :current-filters="currentFilters"
            @apply="handleApplyFilters"
        />

        <!-- Table Container -->
        <v-card
            variant="flat"
            class="border bg-surface rounded-lg overflow-hidden position-relative"
        >
            <!-- Refresh Button -->
            <v-btn
                v-if="showRefresh"
                icon="mdi-refresh"
                variant="text"
                size="small"
                color="primary"
                class="table-refresh-btn"
                title="Refresh Data"
                @click="refresh"
                :loading="loading || internalLoading"
            ></v-btn>
            <!-- Server-side DataTable -->
            <v-data-table-server
                v-if="url"
                v-model="internalSelected"
                :headers="activeHeaders"
                :items="serverItems"
                :items-length="totalItems"
                :loading="loading || internalLoading"
                :search="debouncedSearch"
                :items-per-page="itemsPerPage"
                :height="tableHeight"
                :fixed-header="fixedHeader"
                :show-select="multipleSelect"
                :return-object="returnObject"
                loading-text="Memuat data..."
                items-per-page-text="Data per halaman:"
                hover
                class="custom-table"
                @update:options="loadServerItems"
            >
                <template v-for="(_, name) in $slots" v-slot:[name]="slotData">
                    <div
                        v-if="isItemSlot(String(name))"
                        class="cell-align-wrapper d-flex align-center"
                        :class="getCellAlignClass(String(name))"
                    >
                        <slot :name="name" v-bind="slotData || {}"></slot>
                    </div>
                    <slot v-else :name="name" v-bind="slotData || {}"></slot>
                </template>
                <template v-slot:no-data>
                    <div class="pa-8 text-center">
                        <v-icon size="64" color="grey-lighten-2" class="mb-4"
                            >mdi-database-remove-outline</v-icon
                        >
                        <div
                            class="text-body-1 font-weight-medium text-medium-emphasis"
                        >
                            Data tidak ditemukan
                        </div>
                    </div>
                </template>
            </v-data-table-server>

            <!-- Client-side DataTable -->
            <v-data-table
                v-else
                v-model="internalSelected"
                :headers="activeHeaders"
                :items="items"
                :search="debouncedSearch"
                :loading="loading"
                :items-per-page="itemsPerPage"
                :height="tableHeight"
                :fixed-header="fixedHeader"
                :show-select="multipleSelect"
                :return-object="returnObject"
                loading-text="Memuat data..."
                items-per-page-text="Data per halaman:"
                hover
                class="custom-table"
            >
                <template v-for="(_, name) in $slots" v-slot:[name]="slotData">
                    <div
                        v-if="isItemSlot(String(name))"
                        class="cell-align-wrapper d-flex align-center"
                        :class="getCellAlignClass(String(name))"
                    >
                        <slot :name="name" v-bind="slotData || {}"></slot>
                    </div>
                    <slot v-else :name="name" v-bind="slotData || {}"></slot>
                </template>
                <template v-slot:no-data>
                    <div class="pa-8 text-center">
                        <v-icon size="64" color="grey-lighten-2" class="mb-4"
                            >mdi-database-remove-outline</v-icon
                        >
                        <div
                            class="text-body-1 font-weight-medium text-medium-emphasis"
                        >
                            Data tidak ditemukan
                        </div>
                    </div>
                </template>
            </v-data-table>
        </v-card>
    </div>
</template>

<script setup lang="ts">
import { ref, watch, computed } from "vue";
import api from "@/shared/services/api";
import FilterDialog from "./FilterDialog.vue";

const props = defineProps({
    title: { type: String, default: "" },
    headers: { type: Array as () => any[], required: true },
    items: { type: Array as () => any[], default: () => [] },
    url: { type: String, default: null },
    queryParams: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    showSearch: { type: Boolean, default: true },
    showViewColumns: { type: Boolean, default: true },
    showRefresh: { type: Boolean, default: true },
    search: { type: String, default: "" },
    itemsPerPage: { type: Number, default: 10 },
    tableHeight: { type: [String, Number], default: "calc(100vh - 300px)" },
    fixedHeader: { type: Boolean, default: true },
    multipleSelect: { type: Boolean, default: false },
    returnObject: { type: Boolean, default: false },
    selected: { type: Array, default: () => [] },
});

const emit = defineEmits([
    "update:search",
    "update:selected",
    "data-loaded",
    "update:filters",
]);

const internalSearch = ref(props.search);
const debouncedSearch = ref(props.search);
const internalSelected = ref([...props.selected]);
const filterDialogOpen = ref(false);
const currentFilters = ref<Record<string, string>>({});

const supportedFilterOperators = new Set([
    "eq",
    "ne",
    "gt",
    "gte",
    "lt",
    "lte",
    "like",
    "in",
    "not_in",
]);

// Server-side state
const serverItems = ref([]);
const totalItems = ref(0);
const internalLoading = ref(false);
const lastOptions = ref<any>(null);

let searchTimeout: any = null;

// --- Computed ---

/** Kolom yang punya filter: true */
const hasFilterableColumns = computed(() =>
    props.headers.some((h: any) => h.filter === true),
);

/** Jumlah filter yang sedang aktif */
const activeFilterCount = computed(
    () => Object.keys(currentFilters.value).length,
);

/** Kolom yang punya search: true, dipakai sebagai field_search param */
const searchableFields = computed(() =>
    props.headers
        .filter((h: any) => h.search === true)
        .map((h: any) => h.key)
        .join(","),
);

// --- Filter Methods ---

const handleApplyFilters = (filters: Record<string, string>) => {
    currentFilters.value = filters;
    emit("update:filters", filters);
    if (props.url && lastOptions.value) {
        loadServerItems({ ...lastOptions.value, page: 1 });
    }
};

const removeFilter = (filterKey: string) => {
    delete currentFilters.value[filterKey];
    currentFilters.value = { ...currentFilters.value };
    emit("update:filters", currentFilters.value);
    if (props.url && lastOptions.value) {
        loadServerItems({ ...lastOptions.value, page: 1 });
    }
};

const clearAllFilters = () => {
    currentFilters.value = {};
    emit("update:filters", currentFilters.value);
    if (props.url && lastOptions.value) {
        loadServerItems({ ...lastOptions.value, page: 1 });
    }
};

const getFilterLabel = (filterKey: string): string => {
    const match = filterKey.match(/^(.+)\[(\w+)\]$/);
    const colKey = match ? match[1] : filterKey;
    const col = props.headers.find((h: any) => h.key === colKey);
    return col ? col.filterLabel || col.title : colKey;
};

const getFilterDisplayValue = (filterKey: string, value: string): string => {
    const match = filterKey.match(/^(.+)\[(\w+)\]$/);
    const colKey = match ? match[1] : filterKey;
    const col = props.headers.find((h: any) => h.key === colKey);

    if (!col) return value;

    // Select type: show label instead of value
    if (col.type === "select" && col.options) {
        const opt = col.options.find((o: any) => o.value === value);
        return opt ? opt.label : value;
    }

    // Date range format
    if (typeof value === "string" && value.includes("::")) {
        const [start, end] = value.split("::");
        const fmt = (d: string) => {
            try {
                return new Date(d).toLocaleDateString("id-ID", {
                    day: "numeric",
                    month: "short",
                    year: "numeric",
                });
            } catch {
                return d;
            }
        };
        return `${fmt(start)} - ${fmt(end)}`;
    }

    return value;
};

const getNextDate = (value: string): string => {
    const parts = value.split("-").map(Number);
    if (parts.length !== 3 || parts.some((part) => Number.isNaN(part))) {
        return value;
    }

    const [year, month, day] = parts;
    const nextDate = new Date(Date.UTC(year, month - 1, day + 1));

    return nextDate.toISOString().slice(0, 10);
};

// --- Search ---

const onSearchInput = (val: string) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        debouncedSearch.value = val;
        emit("update:search", val);
    }, 500);
};

// --- Server Data Loading ---

const loadServerItems = async (options: any) => {
    if (!props.url) return;
    lastOptions.value = options;
    const { page, itemsPerPage, sortBy } = options;

    internalLoading.value = true;
    try {
        let sortQuery = "";
        if (sortBy && sortBy.length > 0) {
            sortQuery = sortBy
                .map((s: any) => (s.order === "desc" ? "-" : "") + s.key)
                .join(",");
        }

        const params: Record<string, any> = {
            page,
            limit: itemsPerPage,
            search: debouncedSearch.value || undefined,
            sort: sortQuery || undefined,
            ...props.queryParams,
        };

        if (itemsPerPage === -1) {
            params.pagination = false;
            delete params.limit;
            delete params.page;
        }

        // Tambahkan field_search jika ada kolom search: true
        if (searchableFields.value) {
            params.field_search = searchableFields.value;
        }

        // Gunakan kontrak filter DataTable backend:
        // filter[field][operator]=value.
        for (const [key, value] of Object.entries(currentFilters.value)) {
            const match = key.match(/^(.+)\[(\w+)\]$/);
            if (!match) continue;

            const colKey = match[1];
            const col = props.headers.find((h: any) => h.key === colKey);
            const apiFilterKey = col?.filterKey || colKey;
            const requestedOperator = match[2] === "lse" ? "like" : match[2];
            const operator = supportedFilterOperators.has(requestedOperator)
                ? requestedOperator
                : "eq";

            if (col?.type === "date") {
                const rawValue = String(value);
                const [rawStart, rawEnd] = rawValue.includes("::")
                    ? rawValue.split("::")
                    : [rawValue, rawValue];
                const dates = [rawStart, rawEnd].filter(Boolean).sort();
                const start = dates[0];
                const end = dates[dates.length - 1];

                if (start) params[`filter[${apiFilterKey}][gte]`] = start;
                if (end)
                    params[`filter[${apiFilterKey}][lt]`] = getNextDate(end);
                continue;
            }

            const filterValue =
                operator === "like" && !String(value).includes("%")
                    ? `%${value}%`
                    : value;
            if (operator === "eq") {
                params[`filter[${apiFilterKey}]`] = filterValue;
            } else {
                params[`filter[${apiFilterKey}][${operator}]`] = filterValue;
            }
        }

        const { data } = await api.get(props.url, { params });

        if (data?.data) {
            serverItems.value = data.data.results || data.data || [];
            totalItems.value =
                data.data.pagination?.total_data || serverItems.value.length;
        } else {
            serverItems.value = data || [];
            totalItems.value = data?.length || 0;
        }

        emit("data-loaded", data);
    } catch (e) {
        console.error("DataTable API Error", e);
        serverItems.value = [];
        totalItems.value = 0;
    } finally {
        internalLoading.value = false;
    }
};

const refresh = () => {
    if (props.url && lastOptions.value) {
        loadServerItems(lastOptions.value);
    }
};

defineExpose({ refresh });

// --- Cell Alignment Helpers ---

/** Check if a slot name is an item column slot (e.g. 'item.name') */
const isItemSlot = (name: string): boolean => {
    return name.startsWith("item.");
};

/** Get the flex justify class for a column based on its align property */
const getCellAlignClass = (slotName: string): string => {
    const key = slotName.replace("item.", "");
    const col = activeHeaders.value.find((c: any) => c.key === key);
    const align = col?.align || "start";
    if (align === "center") return "justify-center";
    if (align === "end") return "justify-end";
    return "justify-start";
};

// --- Column Visibility ---

const viewableHeaders = computed(() =>
    props.headers.filter((h: any) => h.visible !== false),
);

const visibleColumns = ref(viewableHeaders.value.map((h: any) => h.key));

const activeHeaders = computed(() => {
    return props.headers
        .filter(
            (col: any) =>
                col.visible !== false && visibleColumns.value.includes(col.key),
        )
        .map((col: any) => {
            // Standarisasi align untuk Vuetify 3
            let standardizedAlign = col.align;
            if (col.align === "left") standardizedAlign = "start";
            else if (col.align === "right") standardizedAlign = "end";

            return {
                ...col,
                align: standardizedAlign,
            };
        });
});

watch(
    () => props.headers,
    (newVal) => {
        visibleColumns.value = newVal
            .filter((h: any) => h.visible !== false)
            .map((h: any) => h.key);
    },
    { deep: true },
);

watch(
    () => props.queryParams,
    () => {
        if (props.url && lastOptions.value) {
            loadServerItems({ ...lastOptions.value, page: 1 });
        }
    },
    { deep: true },
);

watch(
    () => props.search,
    (val) => {
        internalSearch.value = val;
    },
);

watch(internalSearch, (val) => {
    emit("update:search", val);
});

watch(
    () => props.selected,
    (val) => {
        internalSelected.value = val;
    },
);

watch(internalSelected, (val) => {
    emit("update:selected", val);
});
</script>

<style scoped>
.search-input {
    width: 250px;
}
.gap-3 {
    gap: 12px;
}

/* Header Styling */
.is-light-mode :deep(th.v-data-table__th) {
    background-color: #f4f4f4 !important;
    color: #334155 !important;
    border-bottom: 2px solid #e2e8f0 !important;
}

/* Standardize Header Text */
:deep(.v-data-table-header__content) {
    font-weight: 700 !important;
}
/* Table Refresh Button */
.table-refresh-btn {
    position: absolute;
    bottom: 10px;
    left: 16px;
    z-index: 10;
    /* border: 1px solid #334155; */
}

/* Make room for absolute refresh button so it doesn't overlap pagination */
:deep(.v-data-table-footer) {
    padding-right: 14px !important;
}

/* Cell alignment wrapper: force children to auto-width so flex justify works */
.cell-align-wrapper {
    width: 100%;
}
.cell-align-wrapper > :deep(*) {
    width: auto !important;
    max-width: 100%;
}

/* Custom minimal scrollbar for the table wrapper */
:deep(.v-table__wrapper::-webkit-scrollbar) {
    width: 4px;
    height: 4px;
}
:deep(.v-table__wrapper::-webkit-scrollbar-track) {
    background: transparent;
}
:deep(.v-table__wrapper::-webkit-scrollbar-thumb) {
    background: rgba(0, 0, 0, 0.15);
    border-radius: 4px;
}
:deep(.v-table__wrapper::-webkit-scrollbar-thumb:hover) {
    background: rgba(0, 0, 0, 0.25);
}
:deep(.v-table__wrapper::-webkit-scrollbar-button) {
    display: none;
}
</style>
