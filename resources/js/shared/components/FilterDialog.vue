<template>
    <v-dialog v-model="dialogOpen" max-width="560" persistent>
        <v-card class="rounded-xl">
            <v-card-title
                class="d-flex align-center justify-space-between pt-5 px-6"
            >
                <span class="text-h6 font-weight-bold">Filter Data</span>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    @click="close"
                ></v-btn>
            </v-card-title>
            <v-card-subtitle class="px-6 pb-2 text-body-2 text-medium-emphasis">
                Atur filter untuk menemukan data yang Anda cari.
            </v-card-subtitle>

            <v-divider />

            <v-card-text
                class="px-6 py-5"
                style="max-height: 60vh; overflow-y: auto"
            >
                <div
                    v-if="filterableColumns.length === 0"
                    class="text-center text-medium-emphasis pa-4"
                >
                    Tidak ada kolom yang bisa difilter.
                </div>

                <div v-else class="d-flex flex-column" style="gap: 20px">
                    <div v-for="col in filterableColumns" :key="col.key">
                        <div
                            class="d-flex align-center justify-space-between mb-1"
                        >
                            <label class="text-body-2 font-weight-medium">
                                {{ col.filterLabel || col.title }}
                            </label>
                            <v-btn
                                v-if="hasValue(col.key)"
                                variant="text"
                                size="x-small"
                                color="error"
                                class="text-none"
                                @click="clearColumn(col.key)"
                            >
                                Reset
                            </v-btn>
                        </div>

                        <!-- Text / Number Input -->
                        <v-text-field
                            v-if="col.type === 'text' || col.type === 'number'"
                            v-model="filters[col.key].value"
                            :type="col.type === 'number' ? 'number' : 'text'"
                            :placeholder="
                                col.placeholder || getPlaceholder(col.type)
                            "
                            variant="outlined"
                            density="compact"
                            hide-details
                        />

                        <!-- Select Input -->
                        <v-autocomplete
                            v-else-if="col.type === 'select'"
                            v-model="filters[col.key].value"
                            :items="col.options || []"
                            item-title="label"
                            item-value="value"
                            :placeholder="col.placeholder || 'Pilih opsi...'"
                            variant="outlined"
                            density="compact"
                            hide-details
                            clearable
                        />

                        <!-- Date Range -->
                        <div
                            v-else-if="
                                col.filterType === 'date-range' ||
                                col.type === 'date'
                            "
                            class="d-flex align-center"
                            style="gap: 12px"
                        >
                            <div class="flex-grow-1">
                                <label
                                    class="text-caption text-medium-emphasis d-block mb-1"
                                    >Dari</label
                                >
                                <v-text-field
                                    type="date"
                                    :model-value="getStartDate(col.key)"
                                    @update:model-value="
                                        (v: string) =>
                                            updateDateRange(col.key, 'start', v)
                                    "
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                />
                            </div>
                            <div class="flex-grow-1">
                                <label
                                    class="text-caption text-medium-emphasis d-block mb-1"
                                    >Sampai</label
                                >
                                <v-text-field
                                    type="date"
                                    :model-value="getEndDate(col.key)"
                                    @update:model-value="
                                        (v: string) =>
                                            updateDateRange(col.key, 'end', v)
                                    "
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </v-card-text>

            <v-divider />

            <v-card-actions class="px-6 py-4">
                <v-btn
                    variant="text"
                    color="error"
                    class="text-none"
                    @click="clearAll"
                    :disabled="!hasAnyFilter"
                >
                    Reset Semua
                </v-btn>
                <v-spacer />
                <v-btn variant="outlined" class="text-none" @click="close"
                    >Batal</v-btn
                >
                <v-btn
                    color="primary"
                    variant="flat"
                    class="text-none"
                    @click="apply"
                    >Terapkan</v-btn
                >
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";

export interface FilterColumn {
    key: string;
    title: string;
    type?: "text" | "number" | "select" | "date";
    filter?: boolean;
    filterLabel?: string;
    filterKey?: string;
    filterOperator?: string;
    filterType?: "date-range";
    placeholder?: string;
    options?: { value: string; label: string }[];
}

interface FilterEntry {
    operator: string;
    value: string;
}

const props = defineProps<{
    modelValue: boolean;
    columns: FilterColumn[];
    currentFilters: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "update:modelValue", val: boolean): void;
    (e: "apply", filters: Record<string, string>): void;
}>();

const dialogOpen = computed({
    get: () => props.modelValue,
    set: (val) => emit("update:modelValue", val),
});

const filters = ref<Record<string, FilterEntry>>({});

const filterableColumns = computed(() =>
    props.columns.filter((col) => col.filter === true),
);

const hasAnyFilter = computed(() =>
    Object.values(filters.value).some(
        (f) => f.value !== "" && f.value !== null && f.value !== undefined,
    ),
);

const initFilters = () => {
    const result: Record<string, FilterEntry> = {};
    for (const col of filterableColumns.value) {
        const defaultOp =
                col.filterOperator || (col.type === "text" ? "like" : "eq");
        result[col.key] = { operator: defaultOp, value: null as any };
    }

    // Populate from current filters
    for (const [filterKey, value] of Object.entries(props.currentFilters)) {
        const match = filterKey.match(/^(.+)\[(\w+)\]$/);
        if (match) {
            const colKey = match[1];
            const operator = match[2];
            if (result[colKey]) {
                result[colKey] = { operator, value };
            }
        }
    }

    filters.value = result;
};

watch(
    () => props.modelValue,
    (open) => {
        if (open) initFilters();
    },
);

const hasValue = (key: string) => {
    return (
        filters.value[key] &&
        filters.value[key].value !== "" &&
        filters.value[key].value !== null
    );
};

const clearColumn = (key: string) => {
    if (filters.value[key]) filters.value[key].value = null as any;
};

const clearAll = () => {
    for (const col of filterableColumns.value) {
        if (filters.value[col.key]) filters.value[col.key].value = null as any;
    }
};

const getPlaceholder = (type?: string) => {
    switch (type) {
        case "text":
            return "Masukkan teks...";
        case "number":
            return "Masukkan angka...";
        default:
            return "Masukkan nilai...";
    }
};

const safeStr = (val: any): string => {
    return val === null || val === undefined || val === "undefined"
        ? ""
        : String(val);
};

const getStartDate = (key: string): string => {
    const val = safeStr(filters.value[key]?.value);
    return val.includes("::") ? safeStr(val.split("::")[0]) : val;
};

const getEndDate = (key: string): string => {
    const val = safeStr(filters.value[key]?.value);
    return val.includes("::") ? safeStr(val.split("::")[1]) : "";
};

const updateDateRange = (key: string, type: "start" | "end", val: string) => {
    let start = getStartDate(key);
    let end = getEndDate(key);

    if (type === "start") {
        start = val;
        if (start && !end) end = start;
    } else {
        end = val;
        if (end && !start) start = end;
    }

    if (!start && !end) {
        filters.value[key].value = "";
    } else {
        filters.value[key].value = `${start || ""}::${end || ""}`;
    }
};

const apply = () => {
    const result: Record<string, string> = {};
    for (const [key, entry] of Object.entries(filters.value)) {
        if (
            entry.value !== "" &&
            entry.value !== null &&
            entry.value !== undefined
        ) {
            result[`${key}[${entry.operator}]`] = entry.value;
        }
    }
    emit("apply", result);
    emit("update:modelValue", false);
};

const close = () => {
    emit("update:modelValue", false);
};
</script>
