<template>
    <v-card variant="outlined" class="pa-4 rounded-lg">
        <div class="text-caption text-medium-emphasis mb-1">
            {{ title }}
        </div>
        <div 
            class="text-h6 font-weight-bold" 
            :class="`text-${color}`"
        >
            {{ value }}
        </div>
    </v-card>
</template>

<script setup lang="ts">
defineProps<{
    title: string;
    value: string | number;
    color: string;
}>();
</script>
