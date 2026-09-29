<template>
    <div :class="[alignClass, { 'w-100': !inline }]">
        <v-chip
            :color="badgeColor"
            :size="size"
            :variant="variant"
            :class="chipClass"
        >
            <slot>{{ badgeLabel }}</slot>
        </v-chip>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useBadge } from '@/shared/composables/useBadge';

const props = defineProps({
    text: { type: String, default: '' },
    color: { type: String, default: undefined },
    size: { type: String, default: 'small' },
    variant: { type: String as () => 'flat' | 'text' | 'elevated' | 'tonal' | 'outlined' | 'plain', default: 'tonal' },
    chipClass: { type: String, default: 'font-weight-medium' },
    align: { type: String, default: 'start' }, // 'start', 'center', 'end', 'left', 'right'
    
    // Dynamic / Smart Props
    type: { type: String, default: undefined }, // 'status', 'category', 'level', 'promo', dsb.
    value: { type: [String, Number, Boolean], default: undefined },
    inline: { type: Boolean, default: false },
});

const { getBadgeData } = useBadge();

const smartBadge = computed(() => {
    if (props.type && props.value !== undefined) {
        return getBadgeData(props.type, props.value);
    }
    return null;
});

const badgeColor = computed(() => smartBadge.value?.color || props.color || 'primary');
const badgeLabel = computed(() => smartBadge.value?.label || props.text);

const alignClass = computed(() => {
    let standardized = props.align;
    if (standardized === 'left') standardized = 'start';
    if (standardized === 'right') standardized = 'end';
    
    return `d-flex justify-${standardized}`;
});
</script>
