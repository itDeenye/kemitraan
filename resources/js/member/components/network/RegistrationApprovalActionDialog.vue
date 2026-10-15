<template>
    <v-dialog v-model="dialog" max-width="500" persistent>
        <v-card class="rounded-xl">
            <v-card-title class="approval-action__header">
                <div class="d-flex align-center ga-3 min-w-0">
                    <div
                        class="approval-action__icon"
                        :class="`approval-action__icon--${actionType}`"
                    >
                        <v-icon
                            :icon="
                                actionType === 'approve'
                                    ? 'mdi-check'
                                    : 'mdi-close'
                            "
                            size="22"
                        />
                    </div>
                    <div class="min-w-0">
                        <div class="text-subtitle-1 font-weight-bold">
                            {{
                                actionType === "approve"
                                    ? "Setujui Pendaftaran"
                                    : "Tolak Pendaftaran"
                            }}
                        </div>
                        <div
                            class="text-caption text-medium-emphasis text-truncate"
                        >
                            {{ applicantName }}
                        </div>
                    </div>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    density="comfortable"
                    :disabled="loading"
                    aria-label="Tutup"
                    @click="close"
                />
            </v-card-title>

            <v-divider />

            <v-card-text class="pa-5 pa-sm-6">
                <v-alert
                    :color="actionType === 'approve' ? 'success' : 'error'"
                    variant="tonal"
                    density="compact"
                    class="mb-5"
                >
                    {{
                        actionType === "approve"
                            ? "Pendaftaran akan diteruskan ke admin untuk proses persetujuan akhir."
                            : "Pendaftaran akan langsung ditolak dan tidak diteruskan ke admin."
                    }}
                </v-alert>

                <v-textarea
                    v-model="note"
                    :label="
                        actionType === 'reject'
                            ? 'Alasan Penolakan *'
                            : 'Catatan Persetujuan (Opsional)'
                    "
                    :placeholder="
                        actionType === 'reject'
                            ? 'Contoh: Bukti transfer belum sesuai'
                            : 'Contoh: Dokumen sudah diperiksa'
                    "
                    variant="outlined"
                    density="comfortable"
                    rows="3"
                    auto-grow
                    maxlength="1000"
                    counter="1000"
                    :error-messages="noteError"
                    @update:model-value="noteError = ''"
                />
            </v-card-text>

            <v-divider />

            <v-card-actions class="pa-4 pa-sm-5">
                <v-spacer />
                <v-btn
                    variant="outlined"
                    color="medium-emphasis"
                    class="text-none"
                    :disabled="loading"
                    @click="close"
                >
                    Batal
                </v-btn>
                <v-btn
                    :color="actionType === 'approve' ? 'primary' : 'error'"
                    variant="flat"
                    class="text-none px-5"
                    :loading="loading"
                    @click="submit"
                >
                    {{
                        actionType === "approve"
                            ? "Ya, Setujui"
                            : "Ya, Tolak"
                    }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import networkService from "@/member/services/network.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const emit = defineEmits<{
    processed: [];
}>();

const snackbar = useSnackbarStore();
const dialog = ref(false);
const loading = ref(false);
const registrationId = ref<number | null>(null);
const applicantName = ref("");
const actionType = ref<"approve" | "reject">("approve");
const note = ref("");
const noteError = ref("");

const open = (
    id: number,
    name: string,
    action: "approve" | "reject",
) => {
    registrationId.value = id;
    applicantName.value = name || "Calon Mitra";
    actionType.value = action;
    note.value = "";
    noteError.value = "";
    dialog.value = true;
};

const close = () => {
    if (loading.value) {
        return;
    }

    dialog.value = false;
};

const submit = async () => {
    if (registrationId.value === null) {
        return;
    }

    const normalizedNote = note.value.trim();
    if (actionType.value === "reject" && !normalizedNote) {
        noteError.value = "Alasan penolakan wajib diisi.";
        return;
    }

    loading.value = true;
    noteError.value = "";

    try {
        const payload = { note: normalizedNote || null };
        const response =
            actionType.value === "approve"
                ? await networkService.approveRegistration(
                      registrationId.value,
                      payload,
                  )
                : await networkService.rejectRegistration(
                      registrationId.value,
                      payload,
                  );

        snackbar.showMessage(response.message, "success");
        dialog.value = false;
        emit("processed");
    } catch (error: any) {
        const validationMessage = error.response?.data?.errors?.note?.[0];
        if (validationMessage) {
            noteError.value = validationMessage;
        }

        snackbar.showMessage(
            error.response?.data?.message ||
                "Gagal memproses persetujuan pendaftaran.",
            "error",
        );
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, close });
</script>

<style scoped>
.approval-action__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
}

.approval-action__icon {
    display: grid;
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    place-items: center;
    border-radius: 50%;
}

.approval-action__icon--approve {
    color: #15803d;
    background: #dcfce7;
}

.approval-action__icon--reject {
    color: #b91c1c;
    background: #fee2e2;
}
</style>
