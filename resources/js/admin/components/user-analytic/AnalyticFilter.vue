<template>
    <v-card variant="outlined" class="pa-4 mb-6 rounded-lg">
        <v-row dense align="center">
            <v-col cols="12" md="4">
                <v-text-field
                    v-model="filters.date_from"
                    label="Tanggal Mulai"
                    type="date"
                    density="compact"
                    variant="outlined"
                    hide-details
                ></v-text-field>
            </v-col>
            <v-col cols="12" md="4">
                <v-text-field
                    v-model="filters.date_to"
                    label="Tanggal Akhir"
                    type="date"
                    density="compact"
                    variant="outlined"
                    hide-details
                ></v-text-field>
            </v-col>
            <v-col cols="12" md="4" class="d-flex justify-end">
                <v-btn
                    color="primary"
                    @click="applyFilters"
                    prepend-icon="mdi-filter"
                    class="mr-2 text-none"
                >
                    Terapkan Filter
                </v-btn>
                <v-btn
                    variant="outlined"
                    class="text-none"
                    @click="resetFilters"
                >
                    Reset
                </v-btn>
            </v-col>
        </v-row>
    </v-card>
</template>

<script setup lang="ts">
import { ref } from "vue";

const filters = ref({
    date_from: "",
    date_to: "",
});

const emit = defineEmits(["filter"]);

const applyFilters = () => {
    // Only pass non-empty values
    const payload: { date_from?: string; date_to?: string } = {};
    if (filters.value.date_from) payload.date_from = filters.value.date_from;
    if (filters.value.date_to) payload.date_to = filters.value.date_to;
    emit("filter", payload);
};

const resetFilters = () => {
    filters.value = {
        date_from: "",
        date_to: "",
    };
    emit("filter", {});
};
</script>
