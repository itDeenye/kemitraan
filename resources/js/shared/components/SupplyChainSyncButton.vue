<template>
    <v-list-item
        v-if="visible && props.presentation === 'menu'"
        title="Sinkronisasi Internal"
        aria-label="Sinkronisasi Internal"
        role="menuitem"
        prepend-icon="mdi-cloud-sync-outline"
        color="info"
        :disabled="processing"
        @click.stop="synchronize"
    >
        <template #append>
            <v-progress-circular v-if="processing" indeterminate size="18" width="2" color="info" />
        </template>
    </v-list-item>
    <v-btn
        v-else-if="visible"
        v-tooltip:top="'Sinkronisasi Internal'"
        aria-label="Sinkronisasi Internal"
        icon="mdi-cloud-sync-outline"
        variant="outlined"
        size="small"
        class="rounded"
        color="info"
        :loading="processing"
        :disabled="processing"
        @click="synchronize"
    />
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import api from "@/shared/services/api";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const props = defineProps<{ endpoint: string; canSync?: boolean; presentation?: 'button' | 'menu' }>();
const emit = defineEmits(["processed"]);
const snackbar = useSnackbarStore();
const processing = ref(false);
const completedEndpoint = ref<string | null>(null);
const visible = computed(() => Boolean(props.canSync) && completedEndpoint.value !== props.endpoint);

const responseMessage = (data: any, fallback: string) =>
    typeof data?.message === "string" && data.message.trim() ? data.message : fallback;

const synchronize = async () => {
    if (processing.value || !visible.value) return;
    const endpoint = props.endpoint;
    processing.value = true;
    try {
        const { data } = await api.post(endpoint);
        if (data?.success !== true) {
            snackbar.showMessage(responseMessage(data, "Sinkronisasi belum berhasil."), "error");
            return;
        }
        completedEndpoint.value = endpoint;
        snackbar.showMessage(responseMessage(data, "Sinkronisasi berhasil."), "success");
    } catch (error: any) {
        snackbar.showMessage(responseMessage(error.response?.data, "Gagal menghubungi layanan sinkronisasi."), "error");
    } finally {
        processing.value = false;
        emit("processed");
    }
};
</script>
