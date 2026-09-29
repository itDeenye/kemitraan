<template>
    <v-dialog v-model="dialog" max-width="500" persistent>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon
                        :color="actionType === 'approve' ? 'success' : 'error'"
                        size="28"
                    >
                        {{
                            actionType === "approve"
                                ? "mdi-check-circle-outline"
                                : "mdi-close-circle-outline"
                        }}
                    </v-icon>
                    <span
                        :class="[
                            'text-h6 font-weight-bold',
                            actionType === 'approve'
                                ? 'text-success'
                                : 'text-error',
                        ]"
                    >
                        {{
                            actionType === "approve"
                                ? "Setujui " + contextTitle
                                : "Tolak " + contextTitle
                        }}
                    </span>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    color="medium-emphasis"
                    @click="close"
                    density="comfortable"
                ></v-btn>
            </v-card-title>

            <v-card-text class="pa-6">
                <p class="text-body-2 mb-4">
                    Anda akan
                    {{ actionType === "approve" ? "menyetujui" : "menolak" }}
                    {{ contextName }} dari <strong>{{ memberName }}</strong
                    >. Silakan tambahkan catatan (opsional).
                </p>

                <v-form ref="formRef" @submit.prevent="submit">
                    <v-textarea
                        v-model="note"
                        label="Catatan (opsional)"
                        variant="outlined"
                        density="comfortable"
                        rows="3"
                        auto-grow
                        hide-details="auto"
                        :placeholder="
                            actionType === 'approve'
                                ? 'Dokumen telah diverifikasi...'
                                : 'Dokumen tidak lengkap...'
                        "
                    ></v-textarea>
                </v-form>
            </v-card-text>

            <v-card-actions class="pa-6 pt-0">
                <v-spacer></v-spacer>
                <v-btn
                    variant="outlined"
                    color="medium-emphasis"
                    class="text-none px-6"
                    @click="close"
                    :disabled="loading"
                >
                    Batal
                </v-btn>
                <v-btn
                    :color="actionType === 'approve' ? 'success' : 'error'"
                    variant="flat"
                    class="text-none px-6 ml-3"
                    @click="submit"
                    :loading="loading"
                >
                    {{ actionType === "approve" ? "Ya, Setujui" : "Ya, Tolak" }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import api from "@/shared/services/api";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const emit = defineEmits(["processed"]);
const snackbar = useSnackbarStore();

const dialog = ref(false);
const loading = ref(false);
const formRef = ref();

const registrationId = ref<number | null>(null);
const memberName = ref("");
const actionType = ref<"approve" | "reject">("approve");
const note = ref("");
const resourceEndpoint = ref("registrations");
const contextName = ref("registrasi");
const contextTitle = ref("Registrasi");

const open = (
    id: number,
    name: string,
    type: "approve" | "reject",
    resource = "registrations",
    context = "registrasi",
) => {
    registrationId.value = id;
    memberName.value = name || "Mitra";
    actionType.value = type;
    resourceEndpoint.value = resource;
    contextName.value = context.toLowerCase();
    contextTitle.value = context.charAt(0).toUpperCase() + context.slice(1);
    note.value = "";
    dialog.value = true;
};

const close = () => {
    dialog.value = false;
    formRef.value?.reset();
};

const submit = async () => {
    if (!registrationId.value) return;

    loading.value = true;
    try {
        const payload = { note: note.value };
        const endpoint = `/admin/partnership/${resourceEndpoint.value}/${registrationId.value}/${actionType.value}`;

        await api.post(endpoint, payload);

        snackbar.showMessage(
            `${contextTitle.value} berhasil di${actionType.value === "approve" ? "setujui" : "tolak"}.`,
            "success",
        );
        emit("processed");
        close();
    } catch (e: any) {
        snackbar.showMessage(
            e.response?.data?.message ||
                "Terjadi kesalahan saat memproses data.",
            "error",
        );
    } finally {
        loading.value = false;
    }
};

defineExpose({
    open,
    close,
});
</script>
