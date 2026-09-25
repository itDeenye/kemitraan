<template>
    <div class="media-upload-wrapper position-relative d-inline-block">
        <input
            type="file"
            ref="fileInputRef"
            class="d-none"
            @change="handleFileChange"
            :accept="accept"
        />

        <!-- UI Container -->
        <div
            class="media-upload-container position-relative overflow-hidden cursor-pointer d-inline-block"
            :class="containerClass"
            @click="triggerFileSelect"
            :style="{
                width: typeof size === 'number' ? size + 'px' : size,
                height: typeof size === 'number' ? size + 'px' : size,
            }"
        >
            <slot name="preview" :url="previewUrl || modelValue">
                <!-- Default Avatar Preview -->
                <v-avatar
                    :size="size"
                    :color="color"
                    class="elevation-2 w-100 h-100"
                >
                    <v-img
                        v-if="(previewUrl || modelValue) && !imageError"
                        :src="previewUrl || modelValue"
                        cover
                        @error="imageError = true"
                    />
                    <span
                        v-else-if="fallbackText"
                        class="font-weight-bold"
                        :style="{ fontSize: fallbackFontSize, color: 'white' }"
                    >
                        {{ computedInitials }}
                    </span>
                    <v-icon v-else :size="iconSize" color="white">{{
                        fallbackIcon
                    }}</v-icon>
                </v-avatar>
            </slot>

            <!-- Upload Progress Overlay -->
            <v-overlay
                v-model="isUploading"
                contained
                class="align-center justify-center bg-black"
                opacity="0.6"
            >
                <div
                    class="text-center d-flex flex-column align-center justify-center pa-2"
                >
                    <v-progress-circular
                        :model-value="progress"
                        color="white"
                        :size="40"
                        :width="4"
                    >
                        <span class="text-caption font-weight-bold"
                            >{{ progress }}%</span
                        >
                    </v-progress-circular>
                    <v-btn
                        v-if="progress < 100"
                        size="x-small"
                        variant="text"
                        color="error"
                        class="mt-1"
                        @click.stop="cancelUpload"
                    >
                        Batal
                    </v-btn>
                </div>
            </v-overlay>

            <!-- Hover Edit Icon Overlay (Only when not uploading) -->
            <div
                v-if="!isUploading"
                class="edit-overlay position-absolute top-0 left-0 w-100 h-100 d-flex align-center justify-center bg-black bg-opacity-50"
            >
                <v-icon color="white" size="32">mdi-camera</v-icon>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import { useMediaUpload } from "@/shared/composables/useMediaUpload";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const props = defineProps({
    modelValue: { type: String, default: "" },
    collection: { type: String, default: "default" },
    size: { type: [Number, String], default: 120 },
    iconSize: { type: [Number, String], default: 60 },
    color: { type: String, default: "primary" },
    fallbackIcon: { type: String, default: "mdi-camera" },
    fallbackText: { type: String, default: "" },
    accept: { type: String, default: "image/*" },
    rounded: { type: Boolean, default: true },
});

const emit = defineEmits([
    "update:modelValue",
    "error",
    "success",
    "uploading",
]);
const snackbar = useSnackbarStore();

const { isUploading, progress, upload, cancel } = useMediaUpload();

const fileInputRef = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);
const imageError = ref(false);

/** Compute initials from fallbackText */
const computedInitials = computed(() => {
    if (!props.fallbackText) return "";
    return props.fallbackText
        .split(" ")
        .map((w) => w[0])
        .slice(0, 2)
        .join("")
        .toUpperCase();
});

/** Font size for initials, scaled to avatar size */
const fallbackFontSize = computed(() => {
    const s = typeof props.size === "number" ? props.size : parseInt(props.size) || 120;
    return Math.round(s * 0.3) + "px";
});

const containerClass = computed(() => {
    return [props.rounded ? "rounded-circle" : "rounded-lg", "transition-all"];
});

const triggerFileSelect = () => {
    if (isUploading.value) return;
    fileInputRef.value?.click();
};

const handleFileChange = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    // Reset input so the same file can be selected again
    input.value = "";

    // Create a local object URL for immediate preview
    previewUrl.value = URL.createObjectURL(file);
    emit("uploading", true);

    try {
        const uploadedUrl = await upload(file, props.collection);
        imageError.value = false;
        emit("update:modelValue", uploadedUrl);
        emit("success", uploadedUrl);
    } catch (e: any) {
        // Revert preview on failure
        previewUrl.value = null;
        emit("error", e);
        snackbar.showMessage(e.message || "Gagal mengunggah media", "error");
    } finally {
        emit("uploading", false);
    }
};

const cancelUpload = () => {
    cancel();
    previewUrl.value = null;
    emit("uploading", false);
    snackbar.showMessage("Upload dibatalkan", "info");
};
</script>

<style scoped>
.edit-overlay {
    opacity: 0;
    transition: opacity 0.3s ease;
}
.media-upload-container:hover .edit-overlay {
    opacity: 1;
}
</style>
