<template>
    <v-dialog v-model="isOpen" max-width="90%" width="380" persistent>
        <div
            class="bg-white rounded-[24px] flex flex-col items-center text-center shadow-2xl mx-auto w-full"
            style="padding: 24px; box-sizing: border-box"
        >
            <div
                v-if="icon"
                class="mb-5 flex items-center justify-center w-16 h-16 rounded-full"
                :class="iconBgClass"
            >
                <v-icon :icon="icon" :color="resolvedIconColor" size="32" />
            </div>

            <h2 class="text-[18px] font-bold text-[#1f2937] mb-2 leading-tight">
                {{ title }}
            </h2>

            <div
                class="text-[14px] text-[#6b7280] mb-8 max-w-[280px] mx-auto leading-relaxed w-full"
                v-html="message"
            ></div>

            <div class="flex flex-row w-full gap-3">
                <button
                    class="flex-1 py-3 px-4 rounded-full font-bold text-[14px] text-[#4b5563] transition-all duration-200 active:scale-[0.98] focus:outline-none"
                    style="background-color: #f3f4f6"
                    :disabled="isLoading"
                    @click="cancel"
                >
                    Tidak
                </button>
                <button
                    class="flex-1 py-3 px-4 rounded-full font-bold text-[14px] text-white transition-all duration-200 active:scale-[0.98] flex items-center justify-center focus:outline-none"
                    :style="{
                        backgroundColor: confirmColorCss,
                        boxShadow: confirmColorShadow,
                    }"
                    :disabled="isLoading"
                    @click="confirm"
                >
                    <v-progress-circular
                        v-if="isLoading"
                        indeterminate
                        size="20"
                        width="2"
                        class="mr-2"
                    ></v-progress-circular>
                    <span v-else>{{ confirmText }}</span>
                </button>
            </div>
        </div>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";

const isOpen = ref(false);
const isLoading = ref(false);
const title = ref("");
const message = ref("");
const confirmText = ref("Ya");
const confirmColor = ref("primary");
const icon = ref("mdi-help-circle-outline");
const iconColor = ref("primary");

const isError = computed(() => {
    return confirmColor.value === "error" || confirmColor.value === "red";
});

const iconBgClass = computed(() => {
    return isError.value ? "bg-red-50" : "bg-blue-50";
});

const resolvedIconColor = computed(() => {
    if (iconColor.value !== "primary") return iconColor.value;
    return isError.value ? "error" : "primary";
});

const confirmColorCss = computed(() => {
    return isError.value ? "var(--red, #ef4444)" : "var(--primary, #3b82f6)";
});

const confirmColorShadow = computed(() => {
    return isError.value
        ? "rgba(239, 68, 68, 0.25)"
        : "rgba(59, 130, 246, 0.25)";
});

let resolvePromise: ((value: boolean) => void) | null = null;

const open = (options: {
    title?: string;
    message: string;
    confirmText?: string;
    confirmColor?: string;
    icon?: string;
    iconColor?: string;
}) => {
    title.value = options.title || "Konfirmasi";
    message.value = options.message;
    confirmText.value = options.confirmText || "Ya, Lanjutkan";
    confirmColor.value = options.confirmColor || "primary";
    icon.value = options.icon || "mdi-help-circle-outline";
    iconColor.value = options.iconColor || options.confirmColor || "primary";
    isOpen.value = true;

    return new Promise<boolean>((resolve) => {
        resolvePromise = resolve;
    });
};

const confirm = () => {
    if (resolvePromise) resolvePromise(true);
    isOpen.value = false;
};

const cancel = () => {
    if (resolvePromise) resolvePromise(false);
    isOpen.value = false;
};

defineExpose({ open, isLoading });
</script>
