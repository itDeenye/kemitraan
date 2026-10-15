<template>
    <div class="category-slider">
        <button
            type="button"
            class="category-slider__control"
            :disabled="!canScrollLeft"
            :aria-label="`Geser ${itemLabel} ke kiri`"
            @click="scrollCategories(-1)"
        >
            <v-icon icon="mdi-chevron-left" size="20" />
        </button>

        <div
            ref="categoryTrack"
            class="category-slider__track"
            role="tablist"
            :aria-label="ariaLabel"
            @scroll.passive="updateScrollState"
        >
            <button
                v-if="showAll"
                type="button"
                class="filter-chip category-slider__chip"
                :class="{ active: modelValue === null }"
                role="tab"
                :aria-selected="modelValue === null"
                @click="selectCategory(null, $event)"
            >
                {{ allLabel }}
            </button>
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="filter-chip category-slider__chip"
                :class="{ active: modelValue === category.id }"
                role="tab"
                :aria-selected="modelValue === category.id"
                @click="selectCategory(category.id, $event)"
            >
                {{ category.name }}
            </button>
        </div>

        <button
            type="button"
            class="category-slider__control"
            :disabled="!canScrollRight"
            :aria-label="`Geser ${itemLabel} ke kanan`"
            @click="scrollCategories(1)"
        >
            <v-icon icon="mdi-chevron-right" size="20" />
        </button>
    </div>
</template>

<script setup lang="ts" generic="T extends number | string">
import {
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";

interface CategorySliderItem {
    id: T;
    name: string;
}

const props = withDefaults(
    defineProps<{
        categories: CategorySliderItem[];
        modelValue: T | null;
        allLabel?: string;
        ariaLabel?: string;
        itemLabel?: string;
        showAll?: boolean;
    }>(),
    {
        allLabel: "Semua",
        ariaLabel: "Kategori produk",
        itemLabel: "kategori",
        showAll: true,
    },
);

const emit = defineEmits<{
    "update:modelValue": [value: T | null];
}>();

const categoryTrack = ref<HTMLElement | null>(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);
let resizeObserver: ResizeObserver | null = null;

const updateScrollState = () => {
    const track = categoryTrack.value;
    if (!track) {
        return;
    }

    const maximumScrollLeft = Math.max(
        0,
        track.scrollWidth - track.clientWidth,
    );
    const edgeTolerance = 2;

    canScrollLeft.value = track.scrollLeft > edgeTolerance;
    canScrollRight.value =
        track.scrollLeft < maximumScrollLeft - edgeTolerance;
};

const scrollCategories = (direction: -1 | 1) => {
    const track = categoryTrack.value;
    if (!track) {
        return;
    }

    track.scrollBy({
        left: direction * Math.max(track.clientWidth * 0.7, 160),
        behavior: "smooth",
    });
};

const selectCategory = (value: T | null, event: MouseEvent) => {
    emit("update:modelValue", value);

    (event.currentTarget as HTMLElement | null)?.scrollIntoView({
        behavior: "smooth",
        block: "nearest",
        inline: "center",
    });
};

const refreshScrollState = async () => {
    await nextTick();
    updateScrollState();
};

watch(() => props.categories, refreshScrollState, { deep: true });

onMounted(() => {
    refreshScrollState();
    window.addEventListener("resize", updateScrollState);

    if (typeof ResizeObserver !== "undefined" && categoryTrack.value) {
        resizeObserver = new ResizeObserver(updateScrollState);
        resizeObserver.observe(categoryTrack.value);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener("resize", updateScrollState);
    resizeObserver?.disconnect();
});
</script>

<style scoped>
.category-slider {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr) 34px;
    align-items: center;
    gap: 6px;
    margin: 8px 0 10px;
}

.category-slider__track {
    display: flex;
    min-width: 0;
    gap: 8px;
    padding: 2px 0;
    overflow-x: auto;
    overscroll-behavior-x: contain;
    scrollbar-width: none;
    scroll-behavior: smooth;
    scroll-snap-type: x proximity;
    touch-action: pan-x;
}

.category-slider__track::-webkit-scrollbar {
    display: none;
}

.category-slider__chip {
    scroll-snap-align: center;
    font-family: inherit;
    line-height: 1.4;
    white-space: nowrap;
}

.category-slider__control {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    padding: 0;
    border: 1px solid var(--line);
    border-radius: 50%;
    color: var(--wine);
    background: #fff;
    box-shadow: 0 2px 8px rgba(93, 26, 39, 0.08);
    cursor: pointer;
    transition:
        color 0.2s ease,
        border-color 0.2s ease,
        background 0.2s ease,
        opacity 0.2s ease,
        transform 0.2s ease;
}

.category-slider__control:hover:not(:disabled) {
    color: #fff;
    border-color: var(--wine);
    background: var(--wine);
    transform: translateY(-1px);
}

.category-slider__control:focus-visible {
    outline: 2px solid rgba(174, 15, 42, 0.3);
    outline-offset: 2px;
}

.category-slider__control:disabled {
    color: var(--muted);
    background: #f7f4f5;
    box-shadow: none;
    cursor: default;
    opacity: 0.42;
}

@media (max-width: 640px) {
    .category-slider {
        grid-template-columns: 30px minmax(0, 1fr) 30px;
        gap: 4px;
    }

    .category-slider__control {
        width: 30px;
        height: 30px;
    }
}
</style>
