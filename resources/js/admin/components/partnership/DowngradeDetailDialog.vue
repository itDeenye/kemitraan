<template>
    <v-dialog v-model="dialog" max-width="800" scrollable>
        <v-card class="rounded-xl elevation-10">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28"
                        >mdi-file-document-outline</v-icon
                    >
                    <span class="text-h6 font-weight-bold text-primary">
                        Detail Downgrade Mitra
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

            <v-divider></v-divider>

            <v-card-text class="pa-6 pt-4" style="max-height: 75vh">
                <div v-if="loading" class="d-flex justify-center py-12">
                    <v-progress-circular
                        indeterminate
                        color="primary"
                        size="48"
                    ></v-progress-circular>
                </div>

                <div v-else-if="detail">
                    <div
                        class="d-flex align-center justify-space-between mb-6 flex-wrap ga-3"
                    >
                        <div class="d-flex align-center ga-2">
                            <span class="text-caption text-medium-emphasis mr-1"
                                >Downgrade Level:</span
                            >
                            <BaseBadge
                                type="level"
                                :value="detail.from_level?.name"
                                inline
                            />
                            <v-icon size="20" color="medium-emphasis"
                                >mdi-arrow-right</v-icon
                            >
                            <BaseBadge
                                type="level"
                                :value="detail.to_level?.name"
                                inline
                            />
                            <div class="mx-2 border-r h-50"></div>
                            <span class="text-caption text-medium-emphasis mr-1"
                                >Status:</span
                            >
                            <BaseBadge
                                type="upgrade_status"
                                :value="detail.status?.code"
                                inline
                            />
                        </div>
                        <div
                            class="text-caption text-medium-emphasis text-right"
                        >
                            <div v-if="detail.created_at">
                                <v-icon size="14" class="mr-1"
                                    >mdi-calendar-clock</v-icon
                                >
                                Dibuat:
                                {{ formatDateTime(detail.created_at) }}
                            </div>
                        </div>
                    </div>

                    <v-row>
                        <v-col cols="12">
                            <h3
                                class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3"
                            >
                                <v-icon size="22" color="primary"
                                    >mdi-account-circle-outline</v-icon
                                >
                                Data Mitra
                            </h3>
                            <v-card
                                variant="flat"
                                class="rounded-lg border border-opacity-25"
                            >
                                <v-list
                                    density="compact"
                                    class="bg-transparent pa-2"
                                >
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Kode Mitra</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-medium text-body-2"
                                                >{{
                                                    detail.member?.code || "-"
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                    <v-divider
                                        class="my-1 border-opacity-50"
                                    ></v-divider>
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Nama Mitra</v-list-item-title
                                        >
                                        <template #append
                                            ><span
                                                class="font-weight-bold text-body-2"
                                                >{{
                                                    detail.member?.name || "-"
                                                }}</span
                                            ></template
                                        >
                                    </v-list-item>
                                </v-list>
                            </v-card>
                        </v-col>

                        <v-col
                            cols="12"
                            v-if="detail.from_parent || detail.to_parent"
                        >
                            <h3
                                class="text-subtitle-1 font-weight-bold mb-3 mt-2 d-flex align-center ga-2 text-grey-darken-3"
                            >
                                <v-icon size="22" color="primary"
                                    >mdi-account-switch</v-icon
                                >
                                Perubahan Upline
                            </h3>
                            <v-card
                                variant="flat"
                                class="rounded-lg border border-opacity-25"
                            >
                                <v-list
                                    density="compact"
                                    class="bg-transparent pa-2"
                                >
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Upline
                                            Sebelumnya</v-list-item-title
                                        >
                                        <template #append>
                                            <div
                                                v-if="detail.from_parent"
                                                class="d-flex flex-column align-end"
                                            >
                                                <span
                                                    class="font-weight-medium text-body-2"
                                                    >{{
                                                        detail.from_parent.name
                                                    }}</span
                                                >
                                                <div
                                                    class="d-flex align-center justify-end mt-1"
                                                >
                                                    <span
                                                        class="text-caption text-medium-emphasis mr-2"
                                                        >{{
                                                            detail.from_parent
                                                                .code
                                                        }}</span
                                                    >
                                                    <BaseBadge
                                                        type="level"
                                                        :value="
                                                            detail.from_parent
                                                                .level_code
                                                        "
                                                        inline
                                                    />
                                                </div>
                                            </div>
                                            <span
                                                v-else
                                                class="text-medium-emphasis"
                                                >-</span
                                            >
                                        </template>
                                    </v-list-item>
                                    <v-divider
                                        class="my-1 border-opacity-50"
                                    ></v-divider>
                                    <v-list-item>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                            >Upline Baru</v-list-item-title
                                        >
                                        <template #append>
                                            <div
                                                v-if="detail.to_parent"
                                                class="d-flex flex-column align-end"
                                            >
                                                <span
                                                    class="font-weight-medium text-body-2"
                                                    >{{
                                                        detail.to_parent.name
                                                    }}</span
                                                >
                                                <div
                                                    class="d-flex align-center justify-end mt-1"
                                                >
                                                    <span
                                                        class="text-caption text-medium-emphasis mr-2"
                                                        >{{
                                                            detail.to_parent
                                                                .code
                                                        }}</span
                                                    >
                                                    <BaseBadge
                                                        type="level"
                                                        :value="
                                                            detail.to_parent
                                                                .level_code
                                                        "
                                                        inline
                                                    />
                                                </div>
                                            </div>
                                            <span
                                                v-else
                                                class="text-medium-emphasis"
                                                >-</span
                                            >
                                        </template>
                                    </v-list-item>
                                </v-list>
                            </v-card>
                        </v-col>

                        <v-col cols="12" v-if="detail.effective_date">
                            <h3
                                class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3"
                            >
                                <v-icon size="22" color="primary"
                                    >mdi-calendar-start</v-icon
                                >
                                Tanggal Berlaku
                            </h3>
                            <v-card
                                variant="flat"
                                class="rounded-lg border border-opacity-25"
                            >
                                <v-list
                                    density="compact"
                                    class="bg-transparent pa-2"
                                >
                                    <v-list-item>
                                        <template #prepend>
                                            <v-icon
                                                color="grey-darken-1"
                                                size="20"
                                                class="mr-3"
                                                >mdi-calendar-check</v-icon
                                            >
                                        </template>
                                        <v-list-item-title
                                            class="text-body-2 text-medium-emphasis"
                                        >
                                            Mulai Berlaku
                                        </v-list-item-title>
                                        <template #append>
                                            <span
                                                class="font-weight-bold text-body-2"
                                            >
                                                {{
                                                    formatDateTime(
                                                        detail.effective_date,
                                                    )
                                                }}
                                            </span>
                                        </template>
                                    </v-list-item>
                                </v-list>
                            </v-card>
                        </v-col>

                        <!-- Catatan -->
                        <v-col cols="12" v-if="detail.note">
                            <v-alert
                                color="warning"
                                variant="tonal"
                                border="start"
                                density="comfortable"
                                class="mt-4"
                            >
                                <strong>Catatan:</strong><br />
                                {{ detail.note }}
                            </v-alert>
                        </v-col>
                    </v-row>
                </div>
            </v-card-text>
            <v-divider></v-divider>

            <v-card-actions class="pa-6 pt-4 border-t">
                <v-spacer />
                <v-btn
                    variant="flat"
                    color="primary"
                    class="text-none px-8"
                    @click="close"
                    >Tutup</v-btn
                >
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import api from "@/shared/services/api";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const { formatDateTime } = useFormatter();
const snackbar = useSnackbarStore();

const dialog = ref(false);
const loading = ref(false);
const detail = ref<any>(null);

const open = async (id: number) => {
    dialog.value = true;
    loading.value = true;
    detail.value = null;

    try {
        const { data } = await api.get(`/admin/partnership/downgrades/${id}`);
        if (data?.success) {
            detail.value = data.data;
        }
    } catch (error: any) {
        console.error("Gagal memuat detail downgrade:", error);
        snackbar.showMessage(
            error.response?.data?.message ||
                "Gagal memuat detail downgrade mitra",
            "error",
        );
    } finally {
        loading.value = false;
    }
};

const close = () => {
    dialog.value = false;
};

defineExpose({ open, close });
</script>
