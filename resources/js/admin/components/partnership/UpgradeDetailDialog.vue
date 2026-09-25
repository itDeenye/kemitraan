<template>
    <v-dialog v-model="dialog" max-width="1000" scrollable>
        <v-card class="rounded-xl elevation-10">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28"
                        >mdi-file-document-outline</v-icon
                    >
                    <span class="text-h6 font-weight-bold text-primary">
                        Detail Pengajuan Upgrade
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
                                >Pengajuan Level:</span
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
                        </div>
                        <div
                            class="text-caption text-medium-emphasis text-right"
                        >
                            <div v-if="detail.created_at">
                                <v-icon size="14" class="mr-1"
                                    >mdi-calendar-clock</v-icon
                                >
                                Diajukan:
                                {{ formatDateTime(detail.created_at) }}
                            </div>
                        </div>

                        <v-row>
                            <v-col cols="12" md="6">
                                <div class="d-flex flex-column ga-4">
                                    <div>
                                        <h3
                                            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3"
                                        >
                                            <v-icon size="22" color="primary"
                                                >mdi-account-circle-outline</v-icon
                                            >
                                            Data Mitra & Pengajuan
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
                                                    <template #prepend
                                                        ><v-icon
                                                            color="grey-darken-1"
                                                            size="20"
                                                            class="mr-3"
                                                            >mdi-barcode</v-icon
                                                        ></template
                                                    >
                                                    <v-list-item-title
                                                        class="text-body-2 text-medium-emphasis"
                                                        >Kode
                                                        Mitra</v-list-item-title
                                                    >
                                                    <template #append
                                                        ><span
                                                            class="font-weight-bold text-body-2"
                                                            >{{
                                                                detail.member
                                                                    ?.code ||
                                                                "-"
                                                            }}</span
                                                        ></template
                                                    >
                                                </v-list-item>
                                                <v-list-item>
                                                    <template #prepend
                                                        ><v-icon
                                                            color="grey-darken-1"
                                                            size="20"
                                                            class="mr-3"
                                                            >mdi-account-outline</v-icon
                                                        ></template
                                                    >
                                                    <v-list-item-title
                                                        class="text-body-2 text-medium-emphasis"
                                                        >Nama
                                                        Mitra</v-list-item-title
                                                    >
                                                    <template #append
                                                        ><span
                                                            class="font-weight-bold text-body-2"
                                                            >{{
                                                                detail.member
                                                                    ?.name ||
                                                                "-"
                                                            }}</span
                                                        ></template
                                                    >
                                                </v-list-item>
                                                <v-list-item>
                                                    <template #prepend
                                                        ><v-icon
                                                            color="grey-darken-1"
                                                            size="20"
                                                            class="mr-3"
                                                            >mdi-calendar-range</v-icon
                                                        ></template
                                                    >
                                                    <v-list-item-title
                                                        class="text-body-2 text-medium-emphasis"
                                                        >Periode
                                                        Kualifikasi</v-list-item-title
                                                    >
                                                    <template #append
                                                        ><span
                                                            class="font-weight-bold text-body-2"
                                                            >{{
                                                                formatYearMonth(
                                                                    detail
                                                                        .period
                                                                        ?.from,
                                                                )
                                                            }}
                                                            -
                                                            {{
                                                                formatYearMonth(
                                                                    detail
                                                                        .period
                                                                        ?.to,
                                                                )
                                                            }}</span
                                                        ></template
                                                    >
                                                </v-list-item>
                                            </v-list>
                                        </v-card>
                                    </div>
                                </div>
                            </v-col>

                            <v-col cols="12" md="6">
                                <div class="d-flex flex-column ga-4">
                                    <div>
                                        <h3
                                            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3"
                                        >
                                            <v-icon size="22" color="primary"
                                                >mdi-information-outline</v-icon
                                            >
                                            Informasi Proses
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
                                                    <template #prepend
                                                        ><v-icon
                                                            color="grey-darken-1"
                                                            size="20"
                                                            class="mr-3"
                                                            >mdi-check-circle-outline</v-icon
                                                        ></template
                                                    >
                                                    <v-list-item-title
                                                        class="text-body-2 text-medium-emphasis"
                                                        >Status
                                                        Pengajuan</v-list-item-title
                                                    >
                                                    <template #append>
                                                        <BaseBadge
                                                            type="upgrade_status"
                                                            :value="
                                                                detail.status
                                                                    ?.code
                                                            "
                                                            inline
                                                        />
                                                    </template>
                                                </v-list-item>
                                                <v-list-item>
                                                    <template #prepend
                                                        ><v-icon
                                                            color="grey-darken-1"
                                                            size="20"
                                                            class="mr-3"
                                                            >mdi-calendar-clock-outline</v-icon
                                                        ></template
                                                    >
                                                    <v-list-item-title
                                                        class="text-body-2 text-medium-emphasis"
                                                        >Tanggal
                                                        Diproses</v-list-item-title
                                                    >
                                                    <template #append
                                                        ><span
                                                            class="font-weight-bold text-body-2"
                                                            >{{
                                                                detail.approved_at
                                                                    ? formatDateTime(
                                                                          detail.approved_at,
                                                                      )
                                                                    : "-"
                                                            }}</span
                                                        ></template
                                                    >
                                                </v-list-item>
                                                <v-list-item>
                                                    <template #prepend
                                                        ><v-icon
                                                            color="grey-darken-1"
                                                            size="20"
                                                            class="mr-3"
                                                            >mdi-calendar-start</v-icon
                                                        ></template
                                                    >
                                                    <v-list-item-title
                                                        class="text-body-2 text-medium-emphasis"
                                                        >Tanggal
                                                        Berlaku</v-list-item-title
                                                    >
                                                    <template #append
                                                        ><span
                                                            class="font-weight-bold text-body-2"
                                                            >{{
                                                                detail.effective_date
                                                                    ? formatDateTime(
                                                                          detail.effective_date,
                                                                      )
                                                                    : "-"
                                                            }}</span
                                                        ></template
                                                    >
                                                </v-list-item>
                                            </v-list>
                                        </v-card>
                                    </div>
                                </div>
                            </v-col>

                            <v-col
                                cols="12"
                                v-if="
                                    detail.achievements &&
                                    detail.achievements.length > 0
                                "
                            >
                                <h3
                                    class="text-subtitle-1 font-weight-bold mb-3 mt-2 d-flex align-center ga-2 text-grey-darken-3"
                                >
                                    <v-icon size="22" color="primary"
                                        >mdi-trophy-outline</v-icon
                                    >
                                    Detail Pencapaian
                                </h3>
                                <v-card
                                    variant="flat"
                                    class="rounded-lg border border-opacity-25"
                                >
                                    <v-table
                                        density="compact"
                                        class="bg-transparent"
                                    >
                                        <thead class="bg-grey-lighten-4">
                                            <tr>
                                                <th
                                                    class="text-left text-caption text-medium-emphasis font-weight-bold"
                                                >
                                                    Periode
                                                </th>
                                                <th
                                                    class="text-right text-caption text-medium-emphasis font-weight-bold"
                                                >
                                                    Poin
                                                </th>
                                                <th
                                                    class="text-right text-caption text-medium-emphasis font-weight-bold"
                                                >
                                                    Customer Aktif
                                                </th>
                                                <th
                                                    class="text-right text-caption text-medium-emphasis font-weight-bold"
                                                >
                                                    Total Transaksi
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(
                                                    achievement, i
                                                ) in detail.achievements"
                                                :key="i"
                                            >
                                                <td
                                                    class="text-body-2 font-weight-medium"
                                                >
                                                    {{
                                                        formatMonthYear(
                                                            achievement.month,
                                                            achievement.year,
                                                        )
                                                    }}
                                                </td>
                                                <td
                                                    class="text-body-2 text-right"
                                                >
                                                    {{ achievement.point }}
                                                </td>
                                                <td
                                                    class="text-body-2 text-right"
                                                >
                                                    {{
                                                        achievement.customer_count
                                                    }}
                                                </td>
                                                <td
                                                    class="text-body-2 text-right font-weight-bold"
                                                >
                                                    Rp{{
                                                        formatPrice(
                                                            achievement.total_transaction_amount,
                                                        )
                                                    }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </v-table>
                                </v-card>
                            </v-col>

                            <v-col
                                cols="12"
                                v-if="
                                    detail.network_transfers &&
                                    detail.network_transfers.length > 0
                                "
                            >
                                <h3
                                    class="text-subtitle-1 font-weight-bold mb-3 mt-2 d-flex align-center ga-2 text-grey-darken-3"
                                >
                                    <v-icon size="22" color="primary"
                                        >mdi-transit-transfer</v-icon
                                    >
                                    Riwayat Transfer Jaringan
                                </h3>
                                <v-card
                                    variant="flat"
                                    class="rounded-lg border border-opacity-25"
                                >
                                    <v-list
                                        density="compact"
                                        class="bg-transparent pa-2"
                                    >
                                        <template
                                            v-for="(
                                                transfer, i
                                            ) in detail.network_transfers"
                                            :key="i"
                                        >
                                            <v-list-item>
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-swap-horizontal</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                >
                                                    {{
                                                        formatDateTime(
                                                            transfer.created_at,
                                                        )
                                                    }}
                                                </v-list-item-title>
                                                <template #append>
                                                    <div
                                                        class="d-flex flex-column align-end text-right"
                                                    >
                                                        <div
                                                            class="d-flex align-center justify-end ga-1"
                                                        >
                                                            <span
                                                                class="font-weight-medium text-body-2 mr-1"
                                                            >
                                                                {{
                                                                    transfer
                                                                        .from_parent
                                                                        ?.name ||
                                                                    "-"
                                                                }}
                                                            </span>
                                                            <BaseBadge
                                                                v-if="
                                                                    transfer
                                                                        .from_parent
                                                                        ?.level_code
                                                                "
                                                                type="level"
                                                                :value="
                                                                    transfer
                                                                        .from_parent
                                                                        ?.level_code
                                                                "
                                                                inline
                                                            />
                                                            <v-icon
                                                                size="16"
                                                                class="mx-2 text-medium-emphasis"
                                                                >mdi-arrow-right</v-icon
                                                            >
                                                            <span
                                                                class="font-weight-medium text-body-2 mr-1"
                                                            >
                                                                {{
                                                                    transfer
                                                                        .to_parent
                                                                        ?.name ||
                                                                    "-"
                                                                }}
                                                            </span>
                                                            <BaseBadge
                                                                v-if="
                                                                    transfer
                                                                        .to_parent
                                                                        ?.level_code
                                                                "
                                                                type="level"
                                                                :value="
                                                                    transfer
                                                                        .to_parent
                                                                        ?.level_code
                                                                "
                                                                inline
                                                            />
                                                        </div>
                                                        <span
                                                            v-if="transfer.note"
                                                            class="text-caption text-medium-emphasis mt-1"
                                                        >
                                                            {{ transfer.note }}
                                                        </span>
                                                    </div>
                                                </template>
                                            </v-list-item>
                                            <v-divider
                                                v-if="
                                                    i !==
                                                    detail.network_transfers
                                                        .length -
                                                        1
                                                "
                                                class="my-1 border-opacity-50"
                                            ></v-divider>
                                        </template>
                                    </v-list>
                                </v-card>
                            </v-col>
                        </v-row>
                    </div>
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

const { formatDateTime, formatPrice, formatYearMonth, formatMonthYear } =
    useFormatter();
const snackbar = useSnackbarStore();

const dialog = ref(false);
const loading = ref(false);
const detail = ref<any>(null);

const open = async (id: number) => {
    dialog.value = true;
    loading.value = true;
    detail.value = null;

    try {
        const { data } = await api.get(`/admin/partnership/upgrades/${id}`);
        if (data?.success) {
            detail.value = data.data;
        }
    } catch (error: any) {
        console.error("Gagal memuat detail upgrade:", error);
        snackbar.showMessage(
            error.response?.data?.message ||
                "Gagal memuat detail upgrade mitra",
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
