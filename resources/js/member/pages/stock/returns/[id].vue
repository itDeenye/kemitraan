<template>
    <div class="screen-body" style="padding-bottom: 80px">
        <div v-if="isLoading" class="p-6 text-center">
            <v-progress-circular
                indeterminate
                color="primary"
                size="24"
            ></v-progress-circular>
            <p class="m-0 mt-3 text-[var(--muted)] text-[13px]">
                Memuat detail retur...
            </p>
        </div>

        <div
            v-else-if="returnDetail"
            class="d-flex flex-column"
            style="gap: 16px"
        >
            <div
                class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3"
            >
                <div class="flex-1 min-w-0">
                    <h2
                        class="text-base font-bold m-0 mt-0.5 break-words text-[var(--ink)]"
                    >
                        {{ returnDetail.code || "Detail Retur" }}
                    </h2>
                    <div
                        class="text-[12px] text-[var(--muted)] mt-1 flex items-center gap-1.5"
                    >
                        <v-icon icon="mdi-calendar-clock" size="14" />
                        <span v-if="returnDetail.created_at">{{
                            formatDateTime(returnDetail.created_at)
                        }}</span>
                    </div>
                </div>
                <div
                    class="shrink-0 flex flex-col items-start sm:items-end gap-2"
                >
                    <BaseBadge
                        type="return_status"
                        :value="returnDetail.status?.code"
                        inline
                        chip-class="font-semibold"
                    />
                </div>
            </div>

            <v-alert
                :type="returnFlowGuidance.type"
                variant="tonal"
                density="comfortable"
                class="rounded-xl text-body-2"
            >
                <div class="font-weight-bold mb-1">
                    {{ returnFlowGuidance.title }}
                </div>
                <div>{{ returnFlowGuidance.message }}</div>
            </v-alert>

            <div v-if="returnDetail.actions?.can_ship_return" class="mt-2">
                <v-btn
                    color="primary"
                    block
                    prepend-icon="mdi-truck-fast-outline"
                    class="rounded-lg"
                    @click="openShipDialog"
                >
                    Jadwalkan Pengiriman Retur
                </v-btn>
            </div>

            <div
                v-if="returnDetail.actions?.can_confirm_replacement"
                class="mt-2"
            >
                <v-btn
                    color="success"
                    block
                    prepend-icon="mdi-package-variant-closed-check"
                    class="rounded-lg"
                    @click="confirmDialog = true"
                >
                    Konfirmasi Barang Pengganti Diterima
                </v-btn>
            </div>

            <div
                v-if="
                    returnDetail.actions?.can_show_pickup_code &&
                    returnDetail.actions?.pickup_code
                "
                class="card pa-4 rounded-xl border text-center"
                style="
                    background: #eef2ff !important;
                    border-color: #c7d2fe !important;
                "
            >
                <div class="text-caption font-weight-bold text-primary mb-1">
                    KODE VERIFIKASI PENYERAHAN BARANG
                </div>
                <div
                    class="text-h4 font-weight-black text-primary my-2"
                    style="letter-spacing: 6px"
                >
                    {{ returnDetail.actions.pickup_code }}
                </div>
                <div class="text-caption text-medium-emphasis">
                    Tunjukkan 5 digit kode verifikasi ini ke petugas gudang saat
                    Anda menyerahkan barang retur.
                </div>
            </div>

            <div class="card bg-surface pa-4 rounded-xl border">
                <div class="d-flex justify-space-between mb-2">
                    <span class="text-caption text-medium-emphasis"
                        >Kode Transaksi</span
                    >
                    <strong class="text-body-2 font-weight-bold">{{
                        returnDetail.transaction?.code || "-"
                    }}</strong>
                </div>
                <div
                    v-if="returnDetail.goods_receive"
                    class="d-flex justify-space-between mb-2"
                >
                    <span class="text-caption text-medium-emphasis"
                        >Kode Penerimaan</span
                    >
                    <span class="text-body-2 font-weight-bold">{{
                        returnDetail.goods_receive.number
                    }}</span>
                </div>
                <div class="d-flex justify-space-between mb-2">
                    <span class="text-caption text-medium-emphasis"
                        >Total Qty Retur</span
                    >
                    <strong class="text-body-2 font-weight-bold"
                        >{{
                            returnDetail.summary?.total_quantity || 0
                        }}
                        pcs</strong
                    >
                </div>
                <div class="d-flex justify-space-between mb-2">
                    <div class="text-caption text-medium-emphasis">
                        Keterangan / Alasan Retur:
                    </div>
                    <p class="text-body-2 font-weight-bold">
                        {{ returnDetail.description || "-" }}
                    </p>
                </div>
            </div>

            <div class="card">
                <div
                    class="border-b border-[var(--line)]"
                    style="padding: 16px 20px"
                >
                    <div class="flex justify-between items-center">
                        <h3
                            class="flex items-center gap-2 m-0"
                            style="font-size: 14px"
                        >
                            <v-icon
                                icon="mdi-package-variant-closed"
                                size="18"
                            />
                            Produk yang Diretur
                        </h3>
                        <span
                            class="text-xs font-semibold text-[var(--primary)]"
                        >
                            {{ returnDetail.items?.length || 0 }} produk
                        </span>
                    </div>
                </div>

                <div style="padding: 12px 20px 16px 20px">
                    <div
                        class="flex gap-3 border-b border-[var(--line)] last:border-b-0"
                        style="padding-top: 12px; padding-bottom: 12px"
                        v-for="(item, index) in returnDetail.items"
                        :key="item.id"
                        :class="
                            index !== (returnDetail.items?.length || 0) - 1
                                ? 'border-dashed'
                                : ''
                        "
                    >
                        <div
                            class="w-[50px] h-[50px] rounded-lg bg-[#f5f5f5] flex items-center justify-center shrink-0 overflow-hidden"
                            style="border: 1px solid #eaeaea"
                        >
                            <v-img
                                v-if="item.product?.image_url"
                                :src="item.product.image_url"
                                cover
                            ></v-img>
                            <v-icon
                                v-else
                                icon="mdi-image-outline"
                                color="#ccc"
                                size="24"
                            ></v-icon>
                        </div>
                        <div
                            class="flex-1 flex flex-col justify-center min-w-0 pr-2"
                        >
                            <strong
                                class="text-[13px] text-[var(--text)] line-clamp-2 leading-tight mb-1"
                            >
                                {{ item.product?.name || `Produk #${item.id}` }}
                            </strong>
                            <div
                                class="text-[12px] text-[var(--muted)] flex items-center gap-1.5 flex-wrap font-semibold"
                            >
                                <span>{{ item.product?.code || "-" }}</span>
                                <span
                                    v-if="item.batch_number"
                                    class="w-1 h-1 rounded-full bg-[var(--muted)]"
                                ></span>
                                <span
                                    v-if="item.batch_number"
                                    class="text-[12px] font-bold"
                                    >Batch: {{ item.batch_number }}</span
                                >
                                <span
                                    v-if="item.expiry_date"
                                    class="w-1 h-1 rounded-full bg-[var(--muted)]"
                                ></span>
                                <span
                                    v-if="item.expiry_date"
                                    class="text-[12px] font-bold"
                                    >Exp:
                                    {{ formatDate(item.expiry_date) }}</span
                                >
                            </div>
                            <div
                                v-if="item.reason"
                                class="text-[12px] text-[var(--danger)] mt-2 flex items-start gap-1 p-2 bg-[#fff1f2] rounded-md"
                            >
                                <v-icon
                                    icon="mdi-alert-circle-outline"
                                    size="14"
                                    class="pt-1 shrink-0"
                                />
                                <span>{{ item.reason }}</span>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <span
                                class="text-[12px] font-bold px-2 py-1 bg-[var(--primary)] text-white rounded-md whitespace-nowrap"
                            >
                                {{ item.quantity }} pcs
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="returnDetail.return_shipping" class="card">
                <div style="padding: 16px 20px">
                    <h3
                        class="flex items-center gap-2 m-0 mb-4"
                        style="font-size: 14px"
                    >
                        <v-icon icon="mdi-truck-outline" size="18" />
                        {{
                            returnDetail.return_shipping.method === "pickup"
                                ? "Pengambilan Retur (Mitra → Perusahaan)"
                                : "Pengiriman Retur (Mitra → Perusahaan)"
                        }}
                    </h3>

                    <div class="flex justify-between mb-2">
                        <span class="soft-label">Metode</span>
                        <span class="text-[13px] font-semibold">
                            {{
                                returnDetail.return_shipping.method === "pickup"
                                    ? "Ambil di Tempat (Gudang Perusahaan)"
                                    : "Kurir Ekspedisi"
                            }}
                        </span>
                    </div>
                    <div
                        v-if="returnDetail.return_shipping.courier"
                        class="flex justify-between mb-2"
                    >
                        <span class="soft-label">Kurir</span>
                        <span class="text-[13px] font-semibold">
                            {{ returnDetail.return_shipping.courier }}
                            <span v-if="returnDetail.return_shipping.service">
                                -
                                {{ returnDetail.return_shipping.service }}</span
                            >
                        </span>
                    </div>
                    <div
                        v-if="returnDetail.return_shipping.tracking_number"
                        class="flex justify-between mb-2"
                    >
                        <span class="soft-label">Nomor Resi</span>
                        <strong class="text-[13px]">{{
                            returnDetail.return_shipping.tracking_number
                        }}</strong>
                    </div>
                    <div
                        v-if="returnDetail.return_shipping.delivery_note_number"
                        class="flex justify-between mb-2"
                    >
                        <span class="soft-label">Nomor Surat Jalan</span>
                        <span class="text-[13px]">{{
                            returnDetail.return_shipping.delivery_note_number
                        }}</span>
                    </div>
                    <div
                        v-if="returnDetail.return_shipping.total_cost"
                        class="flex justify-between mb-2"
                    >
                        <span class="soft-label">Ongkos Kirim</span>
                        <span
                            class="text-[13px] font-bold text-[var(--primary)]"
                        >
                            Rp{{
                                returnDetail.return_shipping.total_cost.toLocaleString(
                                    "id-ID",
                                )
                            }}
                        </span>
                    </div>
                    <div
                        v-if="returnDetail.return_shipping.cost_bearer"
                        class="flex justify-between mb-2"
                    >
                        <span class="soft-label">Biaya Ditanggung Oleh</span>
                        <span class="text-[13px] font-semibold">
                            {{
                                returnDetail.return_shipping.cost_bearer ===
                                "warehouse"
                                    ? "Perusahaan"
                                    : "Mitra"
                            }}
                        </span>
                    </div>
                    <div
                        v-if="
                            returnDetail.return_shipping?.delivery_status ||
                            returnDetail.replacement_shipping?.delivery_status
                        "
                        class="flex justify-between"
                    >
                        <span class="soft-label">{{
                            returnDetail.return_shipping?.method === "pickup"
                                ? "Status Pengambilan"
                                : "Status Pengiriman"
                        }}</span>
                        <BaseBadge
                            type="shipping_status"
                            :value="
                                returnDetail.return_shipping?.delivery_status ||
                                returnDetail.replacement_shipping
                                    ?.delivery_status
                            "
                            inline
                            chip-class="font-semibold"
                        />
                    </div>
                </div>
            </div>

            <div v-if="returnDetail.replacement_shipping" class="card">
                <div style="padding: 16px 20px">
                    <h3
                        class="flex items-center gap-2 m-0 mb-4"
                        style="font-size: 14px"
                    >
                        <v-icon icon="mdi-truck-outline" size="18" />
                        Pengiriman Barang Pengganti (Perusahaan → Mitra)
                    </h3>
                    <div class="flex justify-between mb-2">
                        <span class="soft-label">Metode</span>
                        <span class="text-[13px] font-semibold">
                            {{
                                returnDetail.replacement_shipping.method ===
                                "pickup"
                                    ? "Ambil di Tempat (Gudang Perusahaan)"
                                    : "Kurir Ekspedisi"
                            }}
                        </span>
                    </div>
                    <div
                        v-if="returnDetail.replacement_shipping.courier"
                        class="flex justify-between mb-2"
                    >
                        <span class="soft-label">Kurir</span>
                        <span class="text-[13px] font-semibold">
                            {{ returnDetail.replacement_shipping.courier }}
                            <span
                                v-if="returnDetail.replacement_shipping.service"
                            >
                                -
                                {{
                                    returnDetail.replacement_shipping.service
                                }}</span
                            >
                        </span>
                    </div>
                    <div
                        v-if="returnDetail.replacement_shipping.tracking_number"
                        class="flex justify-between mb-2"
                    >
                        <span class="soft-label">Nomor Resi</span>
                        <strong class="text-[13px]">{{
                            returnDetail.replacement_shipping.tracking_number
                        }}</strong>
                    </div>
                    <div
                        v-if="
                            returnDetail.replacement_shipping
                                .delivery_note_number
                        "
                        class="flex justify-between mb-2"
                    >
                        <span class="soft-label">Nomor Surat Jalan</span>
                        <span class="text-[13px]">{{
                            returnDetail.replacement_shipping
                                .delivery_note_number
                        }}</span>
                    </div>
                    <div
                        v-if="returnDetail.replacement_shipping.delivery_status"
                        class="flex justify-between"
                    >
                        <span class="soft-label">{{
                            returnDetail.return_shipping?.method === "pickup"
                                ? "Status Pengambilan"
                                : "Status Pengiriman"
                        }}</span>
                        <span class="text-[13px] font-bold text-green-600">
                            <BaseBadge
                                type="shipping_status"
                                :value="
                                    returnDetail.return_shipping
                                        ?.delivery_status ||
                                    returnDetail.replacement_shipping
                                        ?.delivery_status
                                "
                                inline
                                chip-class="font-semibold"
                            />
                        </span>
                    </div>
                </div>
            </div>

            <div
                v-if="
                    returnDetail.attachments?.images?.length ||
                    returnDetail.attachments?.video
                "
                class="card"
            >
                <div style="padding: 16px 20px">
                    <h3
                        class="flex items-center gap-2 m-0 mb-4"
                        style="font-size: 14px"
                    >
                        <v-icon icon="mdi-file-document-outline" size="18" />
                        Lampiran Bukti
                    </h3>

                    <div
                        class="flex flex-col sm:flex-row items-start gap-4 sm:gap-8"
                    >
                        <div
                            v-if="returnDetail.attachments?.images?.length"
                            class="w-full sm:w-auto"
                        >
                            <div
                                class="text-[12px] font-semibold text-[var(--ink)] mb-2"
                            >
                                Bukti Foto:
                            </div>
                            <div class="d-flex flex-wrap" style="gap: 12px">
                                <v-img
                                    v-for="(img, idx) in returnDetail
                                        .attachments.images"
                                    :key="idx"
                                    :src="img"
                                    aspect-ratio="1"
                                    cover
                                    class="rounded-lg border bg-[#f5f5f5] cursor-pointer hover:opacity-90 transition-opacity"
                                    style="
                                        width: 80px;
                                        max-width: 80px;
                                        height: 80px;
                                    "
                                    @click="openImage(img)"
                                >
                                    <template v-slot:placeholder>
                                        <div
                                            class="d-flex align-center justify-center fill-height"
                                        >
                                            <v-progress-circular
                                                color="primary"
                                                indeterminate
                                                size="22"
                                                width="2"
                                            />
                                        </div>
                                    </template>
                                </v-img>
                            </div>
                        </div>

                        <div
                            v-if="returnDetail.attachments?.video"
                            class="w-full sm:w-auto"
                        >
                            <div
                                class="text-[12px] font-semibold text-[var(--ink)] mb-2"
                            >
                                Bukti Video:
                            </div>
                            <div
                                class="rounded-lg overflow-hidden bg-black flex items-center justify-center w-full"
                                style="
                                    width: 100%;
                                    max-width: 320px;
                                    aspect-ratio: 16/9;
                                    border: 1px solid var(--line);
                                "
                            >
                                <video
                                    :src="returnDetail.attachments.video"
                                    controls
                                    style="
                                        width: 100%;
                                        height: 100%;
                                        object-fit: contain;
                                    "
                                ></video>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="returnDetail.status_history?.length"
                class="card bg-surface pa-4 rounded-xl border"
            >
                <div class="text-subtitle-2 font-weight-bold mb-3">
                    Riwayat Status
                </div>
                <v-timeline density="compact" align="start" side="end">
                    <v-timeline-item
                        v-for="(history, i) in returnDetail.status_history"
                        :key="i"
                        :dot-color="getTimelineDotColor(history.status)"
                        size="small"
                    >
                        <div class="d-flex flex-column">
                            <strong class="text-body-2">{{
                                history.label
                            }}</strong>
                            <span class="text-caption text-medium-emphasis">{{
                                formatDateTime(history.created_at)
                            }}</span>
                            <span
                                v-if="history.note"
                                class="text-caption mt-1 d-block bg-grey-lighten-4 pa-2 rounded text-medium-emphasis"
                                >{{ history.note }}</span
                            >
                        </div>
                    </v-timeline-item>
                </v-timeline>
            </div>
        </div>
    </div>
    <v-dialog v-model="imageDialog" max-width="90vw" max-height="90vh">
        <v-card class="bg-transparent" elevation="0">
            <v-img :src="selectedImage" max-height="90vh" contain />
            <v-btn
                icon="mdi-close"
                color="white"
                variant="text"
                class="position-absolute"
                style="top: 10px; right: 10px; z-index: 2"
                @click="imageDialog = false"
            />
        </v-card>
    </v-dialog>

    <v-dialog v-model="shipDialog" max-width="500">
        <v-card class="rounded-xl">
            <v-card-title
                class="font-weight-bold pa-4 pb-2 d-flex align-center justify-space-between"
            >
                Jadwalkan Pengiriman
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    density="comfortable"
                    @click="shipDialog = false"
                />
            </v-card-title>
            <v-card-text class="pa-4 pt-2">
                <p class="text-body-2 text-medium-emphasis mb-4">
                    Silakan tentukan jadwal pengambilan barang (pickup) oleh
                    kurir untuk memproses pengiriman retur ini.
                </p>
                <v-form
                    ref="shipFormRef"
                    v-model="isShipFormValid"
                    @submit.prevent="submitShipReturn"
                >
                    <v-select
                        v-model="shipForm.pickup_schedule"
                        :items="availableShipSchedules"
                        item-title="label"
                        item-value="time"
                        label="Jadwal Pengambilan *"
                        placeholder="Pilih jadwal pickup kurir"
                        variant="outlined"
                        density="compact"
                        :loading="loadingShipSchedules"
                        :disabled="loadingShipSchedules"
                        no-data-text="Tidak ada jadwal pickup kurir yang tersedia"
                        :rules="[
                            (v: any) => !!v || 'Jadwal pengambilan wajib diisi',
                        ]"
                    />
                </v-form>
            </v-card-text>
            <v-card-actions class="pa-4 pt-0">
                <v-spacer />
                <v-btn
                    color="primary"
                    variant="flat"
                    class="rounded-lg px-6"
                    :loading="submittingShip"
                    :disabled="!isShipFormValid || loadingShipSchedules"
                    @click="submitShipReturn"
                >
                    Jadwalkan & Kirim
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="confirmDialog" max-width="460" persistent>
        <v-card class="rounded-xl">
            <v-card-title class="font-weight-bold pa-5 pb-2">
                Konfirmasi Penerimaan
            </v-card-title>
            <v-card-text class="pa-5 pt-2 text-body-2">
                Pastikan barang pengganti sudah Anda terima dan jumlahnya
                sesuai.
            </v-card-text>
            <v-card-actions class="pa-4 pt-0">
                <v-spacer />
                <v-btn
                    variant="outlined"
                    class="rounded-lg"
                    :disabled="submittingConfirmation"
                    @click="confirmDialog = false"
                >
                    Batal
                </v-btn>
                <v-btn
                    color="success"
                    variant="flat"
                    class="rounded-lg"
                    :loading="submittingConfirmation"
                    @click="confirmReplacementReceived"
                >
                    Ya, Sudah Diterima
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { computed, ref, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import returnService from "@/member/services/return.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import type { ReturnItem } from "@/member/types/return";
import { decodeRouteId } from "@/shared/utils/route-id";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const router = useRouter();
const route = useRoute();
const { formatDate, formatDateTime } = useFormatter();
const snackbarStore = useSnackbarStore();
const isLoading = ref(true);
const returnDetail = ref<ReturnItem | null>(null);

const returnFlowGuidance = computed(() => {
    const status = returnDetail.value?.status?.code;
    const isPickup = returnDetail.value?.return_shipping?.method === "pickup";

    if (status === "submitted") {
        return isPickup
            ? {
                  type: "info" as const,
                  title: "Menunggu verifikasi oleh admin",
                  message:
                      "Stok produk retur sudah ditahan. Tunjukkan kode verifikasi di bawah saat menyerahkan barang ke gudang. Admin akan menyetujui dan menerima retur setelah kode verifikasi cocok.",
              }
            : {
                  type: "warning" as const,
                  title: "Menunggu keputusan admin",
                  message:
                      "Stok produk retur sudah ditahan. Jika pengajuan ditolak, stok akan otomatis dikembalikan dan dapat dijual kembali.",
              };
    }

    if (status === "approved") {
        return {
            type: "info" as const,
            title: "Retur disetujui",
            message:
                "Pilih jadwal pengiriman melalui tombol di bawah. Setelah barang sampai, admin akan mengonfirmasi penerimaannya.",
        };
    }

    if (
        ["waiting_member_shipment", "return_in_transit"].includes(status || "")
    ) {
        return {
            type: "info" as const,
            title: "Barang retur sedang dikirim",
            message:
                "Tunggu sampai barang diterima perusahaan. Setelah diterima, admin akan menyiapkan dan mengirim barang pengganti.",
        };
    }

    if (status === "received_by_company") {
        return {
            type: "success" as const,
            title: "Barang retur diterima perusahaan",
            message:
                "Perusahaan sedang menyiapkan pengiriman barang pengganti.",
        };
    }

    if (status === "replacement_in_transit") {
        return {
            type: "info" as const,
            title: "Barang pengganti sedang dikirim",
            message:
                "Setelah barang pengganti diterima, tekan tombol konfirmasi penerimaan untuk menyelesaikan retur.",
        };
    }

    if (status === "rejected") {
        return {
            type: "error" as const,
            title: "Pengajuan retur ditolak",
            message:
                "Stok yang sebelumnya ditahan sudah dikembalikan dan dapat digunakan kembali.",
        };
    }

    if (status === "completed") {
        return {
            type: "success" as const,
            title: "Retur selesai",
            message: "Barang pengganti sudah dikonfirmasi diterima.",
        };
    }

    return {
        type: "warning" as const,
        title: "Proses retur memerlukan perhatian",
        message:
            "Periksa riwayat status di bawah untuk melihat proses terakhir dan tindakan berikutnya.",
    };
});

const imageDialog = ref(false);
const selectedImage = ref("");

const openImage = (url: string) => {
    selectedImage.value = url;
    imageDialog.value = true;
};

const fetchReturnDetail = async () => {
    const params = route.params as Record<string, string | string[]>;
    const returnIdStr = Array.isArray(params.id) ? params.id[0] : params.id;
    const returnId = decodeRouteId(returnIdStr);

    if (!returnId || isNaN(Number(returnId))) {
        router.push("/member/stock/returns");
        return;
    }

    isLoading.value = true;
    try {
        const data = await returnService.getReturn(Number(returnId));
        returnDetail.value = data;
    } catch (e: any) {
        snackbarStore.showMessage(
            e.response?.data?.message || "Gagal memuat detail retur",
            "error",
        );
    } finally {
        isLoading.value = false;
    }
};

const getTimelineDotColor = (status: string): string => {
    switch (status) {
        case "approved":
        case "completed":
            return "success";
        case "rejected":
        case "return_shipping_failed":
        case "replacement_shipping_failed":
            return "error";
        case "under_review":
        case "submitted":
            return "warning";
        default:
            return "info";
    }
};

const shipDialog = ref(false);
const shipFormRef = ref();
const isShipFormValid = ref(false);
const submittingShip = ref(false);
const loadingShipSchedules = ref(false);
const shipSchedules = ref<
    Array<{ time: string; available_until: string; is_available: boolean }>
>([]);
const shipForm = ref({
    pickup_schedule: "",
});
const confirmDialog = ref(false);
const submittingConfirmation = ref(false);

const availableShipSchedules = computed(() =>
    shipSchedules.value
        .filter((schedule) => schedule.is_available && schedule.time)
        .map((schedule) => ({
            time: schedule.time,
            label: formatDateTime(schedule.time),
        })),
);

const openShipDialog = async () => {
    if (!returnDetail.value) return;

    shipForm.value.pickup_schedule = "";
    shipSchedules.value = [];
    shipDialog.value = true;
    loadingShipSchedules.value = true;
    try {
        const result = await returnService.getApprovedReturnSchedules(
            returnDetail.value.id,
        );
        shipSchedules.value = result.results || [];
        const firstAvailable = availableShipSchedules.value[0];
        if (firstAvailable) {
            shipForm.value.pickup_schedule = firstAvailable.time;
        }
    } catch (e: any) {
        snackbarStore.showMessage(
            e.response?.data?.message || "Gagal memuat jadwal pickup kurir",
            "error",
        );
    } finally {
        loadingShipSchedules.value = false;
    }
};

const submitShipReturn = async () => {
    if (!returnDetail.value) return;

    const { valid } = await shipFormRef.value?.validate();
    if (!valid) return;

    submittingShip.value = true;
    try {
        const payload = {
            pickup_schedule: shipForm.value.pickup_schedule,
        };
        await returnService.shipReturn(returnDetail.value.id, payload);
        snackbarStore.showMessage(
            "Berhasil menjadwalkan pengiriman retur",
            "success",
        );
        shipDialog.value = false;
        fetchReturnDetail();
    } catch (e: any) {
        snackbarStore.showMessage(
            e.response?.data?.message || "Gagal menjadwalkan pengiriman retur",
            "error",
        );
    } finally {
        submittingShip.value = false;
    }
};

const confirmReplacementReceived = async () => {
    if (!returnDetail.value) return;

    submittingConfirmation.value = true;
    try {
        await returnService.confirmReplacement(returnDetail.value.id);
        snackbarStore.showMessage(
            "Barang pengganti berhasil dikonfirmasi diterima",
            "success",
        );
        confirmDialog.value = false;
        fetchReturnDetail();
    } catch (e: any) {
        snackbarStore.showMessage(
            e.response?.data?.message ||
                "Gagal mengkonfirmasi penerimaan barang pengganti",
            "error",
        );
    } finally {
        submittingConfirmation.value = false;
    }
};

onMounted(() => {
    fetchReturnDetail();
});
</script>
