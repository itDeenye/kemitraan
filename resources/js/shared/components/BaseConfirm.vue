<template>
    <v-dialog v-model="isOpen" max-width="400" persistent>
        <v-card class="rounded-xl">
            <v-card-title
                class="text-h6 font-weight-bold pt-5 px-6 d-flex align-center gap-2"
            >
                <v-icon :color="iconColor" size="small">{{ icon }}</v-icon>
                {{ title }}
            </v-card-title>
            <v-card-text class="px-6 py-4 text-body-1" v-html="message">
            </v-card-text>
            <v-card-actions class="px-6 py-4">
                <v-spacer></v-spacer>
                <v-btn variant="outlined" class="text-none" @click="cancel"
                    >Batal</v-btn
                >
                <v-btn
                    :color="confirmColor"
                    variant="flat"
                    class="text-none"
                    :loading="isLoading"
                    @click="confirm"
                >
                    {{ confirmText }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";

const isOpen = ref(false);
const isLoading = ref(false);
const title = ref("");
const message = ref("");
const confirmText = ref("Ya");
const confirmColor = ref("primary");
const icon = ref("mdi-help-circle-outline");
const iconColor = ref("primary");

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
