<template>
    <v-dialog v-model="dialog" max-width="850" scrollable persistent>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-avatar color="warning" variant="tonal" size="48">
                        <v-icon>mdi-package-variant-closed-remove</v-icon>
                    </v-avatar>
                    <div>
                        <div class="text-h6 font-weight-bold">
                            Detail Retur Penjualan
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ detail?.code || "Memuat..." }}
                        </div>
                    </div>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    @click="close"
                />
            </v-card-title>

            <v-card-text class="pa-6" style="max-height: 70vh">
                <div v-if="loading" class="d-flex justify-center py-12">
                    <v-progress-circular
                        indeterminate
                        color="primary"
                        size="48"
                        width="4"
                    />
                </div>
                <div
                    v-else-if="!detail"
                    class="text-center py-12 text-medium-emphasis"
                >
                    <v-icon size="48" class="mb-2"
                        >mdi-alert-circle-outline</v-icon
                    >
                    <div>Gagal memuat detail retur</div>
                </div>
                <div v-else class="d-flex flex-column ga-6">
                    <div
                        class="d-flex align-center justify-space-between flex-wrap ga-4"
                    >
                        <div class="d-flex align-center ga-4">
                            <div>
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    Status Retur
                                </div>
                                <BaseBadge
                                    type="return_status"
                                    :value="detail.status"
                                />
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-caption text-medium-emphasis mb-1">
                                Tanggal Pengajuan
                            </div>
                            <div class="text-body-2 font-weight-medium">
                                {{ formatDateTime(detail.created_at) }}
                            </div>
                        </div>
                    </div>

                    <v-row>
                        <v-col cols="12" md="6">
                            <v-card
                                variant="outlined"
                                class="rounded-lg h-100 pa-4"
                            >
                                <h3
                                    class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center ga-2 text-info"
                                >
                                    <v-icon size="small"
                                        >mdi-account-outline</v-icon
                                    >
                                    Mitra Pengaju
                                </h3>
                                <div class="text-body-2 font-weight-medium">
                                    {{ detail.member?.name || "-" }}
                                </div>
                                <div
                                    class="text-caption text-medium-emphasis mb-1"
                                >
                                    {{ detail.member?.code || "-" }}
                                </div>
                            </v-card>
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-card
                                variant="outlined"
                                class="rounded-lg h-100 pa-4"
                            >
                                <h3
                                    class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center ga-2 text-info"
                                >
                                    <v-icon size="small"
                                        >mdi-file-document-outline</v-icon
                                    >
                                    Referensi
                                </h3>
                                <div class="d-flex flex-column ga-1">
                                    <div class="text-body-2">
                                        <span class="text-medium-emphasis"
                                            >KodeTransaksi:</span
                                        >
                                        <span class="font-weight-medium ml-1">{{
                                            detail.transaction?.code || "-"
                                        }}</span>
                                    </div>
                                    <div class="text-body-2">
                                        <span class="text-medium-emphasis"
                                            >Kode Penerimaan:</span
                                        >
                                        <span class="font-weight-medium ml-1">{{
                                            detail.goods_receive?.number || "-"
                                        }}</span>
                                    </div>
                                </div>
                            </v-card>
                        </v-col>
                    </v-row>

                    <div>
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-2 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="primary"
                                >mdi-text-box-outline</v-icon
                            >
                            Keterangan / Alasan Retur
                        </h3>
                        <v-card variant="outlined" class="rounded-lg pa-4">
                            <p class="text-body-2 mb-0">
                                {{ detail.description || "-" }}
                            </p>
                        </v-card>
                    </div>

                    <div
                        v-if="
                            detail.attachments?.images?.length ||
                            detail.attachments?.video
                        "
                    >
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="primary"
                                >mdi-image-multiple-outline</v-icon
                            >
                            Lampiran Bukti
                        </h3>
                        <div class="d-flex flex-wrap ga-3">
                            <v-card
                                v-for="(img, idx) in detail.attachments.images"
                                :key="idx"
                                variant="outlined"
                                class="rounded-lg overflow-hidden"
                                style="width: 120px; height: 120px"
                            >
                                <v-img
                                    :src="img"
                                    height="120"
                                    cover
                                    class="cursor-pointer"
                                    @click="openImagePreview(img)"
                                >
                                    <template v-slot:placeholder>
                                        <div
                                            class="d-flex align-center justify-center fill-height"
                                        >
                                            <v-progress-circular
                                                indeterminate
                                                color="grey-lighten-1"
                                                size="24"
                                            />
                                        </div>
                                    </template>
                                </v-img>
                            </v-card>

                            <v-card
                                v-if="detail.attachments?.video"
                                variant="outlined"
                                class="rounded-lg overflow-hidden d-flex flex-column align-center justify-center bg-grey-lighten-4 cursor-pointer position-relative"
                                style="width: 120px; height: 120px"
                                @click="
                                    openVideoPreview(detail.attachments.video)
                                "
                            >
                                <v-icon size="36" color="primary"
                                    >mdi-play-circle-outline</v-icon
                                >
                                <div
                                    class="text-caption text-medium-emphasis mt-2"
                                >
                                    Video
                                </div>
                            </v-card>
                        </div>
                    </div>

                    <div v-if="detail.details && detail.details.length > 0">
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="primary"
                                >mdi-package-variant-closed</v-icon
                            >
                            Produk yang Diretur
                        </h3>
                        <v-card
                            variant="outlined"
                            class="rounded-lg overflow-hidden"
                        >
                            <v-table density="comfortable">
                                <thead class="">
                                    <tr>
                                        <th class="font-weight-bold text-left">
                                            Produk
                                        </th>
                                        <th
                                            class="text-center font-weight-bold"
                                            style="width: 60px"
                                        >
                                            Qty
                                        </th>
                                        <th class="font-weight-bold text-left">
                                            Batch / Exp
                                        </th>
                                        <th class="font-weight-bold text-left">
                                            Alasan
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in detail.details"
                                        :key="item.id"
                                    >
                                        <td class="py-3">
                                            <div
                                                class="font-weight-medium text-body-2"
                                            >
                                                {{ item.product?.name }}
                                            </div>
                                            <div
                                                class="text-caption text-medium-emphasis"
                                            >
                                                {{ item.product?.code }}
                                            </div>
                                        </td>
                                        <td class="text-center text-body-2">
                                            {{ item.quantity }}
                                        </td>
                                        <td class="text-body-2">
                                            <div>{{ item.batch_number }}</div>
                                            <div
                                                class="text-caption text-medium-emphasis"
                                            >
                                                Exp:
                                                {{
                                                    formatDate(item.expiry_date)
                                                }}
                                            </div>
                                        </td>
                                        <td
                                            class="text-body-2"
                                            style="max-width: 200px"
                                        >
                                            {{ item.reason || "-" }}
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </v-card>
                    </div>

                    <div v-if="detail.pickup_address">
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-2 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="primary"
                                >mdi-map-marker-outline</v-icon
                            >
                            {{
                                detail.return_shipping?.method === "pickup" ||
                                detail.return_shipping?.shipping_method ===
                                    "pickup"
                                    ? "Alamat Pengambilan"
                                    : "Alamat Pengiriman"
                            }}
                        </h3>
                        <v-card variant="outlined" class="rounded-lg pa-4">
                            <div class="text-body-2 font-weight-medium">
                                {{ detail.pickup_address.name }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                {{ detail.pickup_address.phone }}
                            </div>
                            <div class="text-body-2 mt-1">
                                {{ formatLocation(detail.pickup_address) }}
                            </div>
                        </v-card>
                    </div>

                    <div v-if="detail.return_shipping">
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-2 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="primary"
                                >mdi-truck-outline</v-icon
                            >
                            {{
                                detail.return_shipping.method === "pickup" ||
                                detail.return_shipping.shipping_method ===
                                    "pickup"
                                    ? "Pengambilan Retur (Mitra → Perusahaan)"
                                    : "Pengiriman Retur (Mitra → Perusahaan)"
                            }}
                        </h3>
                        <v-card variant="outlined" class="rounded-lg pa-4 mb-4">
                            <div class="d-flex flex-column ga-2 text-body-2">
                                <div
                                    v-if="
                                        detail.return_shipping.method ||
                                        detail.return_shipping.shipping_method
                                    "
                                    class="d-flex justify-space-between align-center"
                                >
                                    <span class="text-medium-emphasis"
                                        >Metode</span
                                    >
                                    <BaseBadge
                                        type="shipping_method"
                                        :value="
                                            detail.return_shipping.method ||
                                            detail.return_shipping
                                                .shipping_method
                                        "
                                        size="x-small"
                                        inline
                                    />
                                </div>
                                <div
                                    v-if="detail.return_shipping.courier"
                                    class="d-flex justify-space-between"
                                >
                                    <span class="text-medium-emphasis"
                                        >Kurir</span
                                    >
                                    <span class="font-weight-medium">
                                        {{
                                            typeof detail.return_shipping
                                                .courier === "object"
                                                ? `${detail.return_shipping.courier.name} ${detail.return_shipping.courier.service ? "- " + detail.return_shipping.courier.service : ""}`
                                                : `${detail.return_shipping.courier} ${detail.return_shipping.service ? "- " + detail.return_shipping.service : ""}`
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        detail.return_shipping
                                            .tracking_number ||
                                        detail.return_shipping.courier
                                            ?.tracking_number
                                    "
                                    class="d-flex justify-space-between"
                                >
                                    <span class="text-medium-emphasis"
                                        >Nomor Resi</span
                                    >
                                    <span class="font-weight-medium">{{
                                        detail.return_shipping
                                            .tracking_number ||
                                        detail.return_shipping.courier
                                            ?.tracking_number
                                    }}</span>
                                </div>
                                <div
                                    v-if="
                                        detail.return_shipping
                                            .delivery_note_number
                                    "
                                    class="d-flex justify-space-between"
                                >
                                    <span class="text-medium-emphasis"
                                        >Nomor Surat Jalan</span
                                    >
                                    <span class="font-weight-medium">{{
                                        detail.return_shipping
                                            .delivery_note_number
                                    }}</span>
                                </div>
                                <div
                                    v-if="
                                        detail.return_shipping.cost !==
                                            undefined &&
                                        detail.return_shipping.cost > 0
                                    "
                                    class="d-flex justify-space-between"
                                >
                                    <span class="text-medium-emphasis"
                                        >Ongkos Kirim</span
                                    >
                                    <span class="font-weight-medium">
                                        Rp{{
                                            detail.return_shipping.cost.toLocaleString(
                                                "id-ID",
                                            )
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        detail.return_shipping.insurance !==
                                            undefined &&
                                        detail.return_shipping.insurance > 0
                                    "
                                    class="d-flex justify-space-between"
                                >
                                    <span class="text-medium-emphasis"
                                        >Asuransi Pengiriman</span
                                    >
                                    <span class="font-weight-medium">
                                        Rp
                                        {{
                                            detail.return_shipping.insurance.toLocaleString(
                                                "id-ID",
                                            )
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        detail.return_shipping.total_cost !==
                                            undefined &&
                                        detail.return_shipping.total_cost > 0
                                    "
                                    class="d-flex justify-space-between pt-1 border-t"
                                >
                                    <span
                                        class="text-medium-emphasis font-weight-bold"
                                        >Total Biaya Pengiriman</span
                                    >
                                    <span class="font-weight-bold text-primary">
                                        Rp{{
                                            detail.return_shipping.total_cost.toLocaleString(
                                                "id-ID",
                                            )
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="detail.return_shipping.cost_bearer"
                                    class="d-flex justify-space-between"
                                >
                                    <span class="text-medium-emphasis"
                                        >Biaya Ditanggung Oleh</span
                                    >
                                    <span class="font-weight-medium">
                                        {{
                                            detail.return_shipping
                                                .cost_bearer === "warehouse"
                                                ? "Perusahaan"
                                                : "Mitra"
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        detail.return_shipping.delivery_status
                                    "
                                    class="d-flex justify-space-between align-center"
                                >
                                    <span class="text-medium-emphasis">{{
                                        detail.return_shipping.method ===
                                        "pickup"
                                            ? "Status Pengambilan"
                                            : "Status Pengiriman"
                                    }}</span>
                                    <BaseBadge
                                        type="shipping_status"
                                        :value="
                                            detail.return_shipping
                                                .delivery_status
                                        "
                                        inline
                                    />
                                </div>
                            </div>
                        </v-card>
                    </div>

                    <div v-if="detail.replacement_shipping">
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-2 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="success"
                                >mdi-truck-check-outline</v-icon
                            >
                            Pengiriman Barang Pengganti (Perusahaan → Mitra)
                        </h3>
                        <v-card variant="outlined" class="rounded-lg pa-4 mb-4">
                            <div class="d-flex flex-column ga-2 text-body-2">
                                <div
                                    v-if="
                                        detail.replacement_shipping.method ||
                                        detail.replacement_shipping
                                            .shipping_method
                                    "
                                    class="d-flex justify-space-between align-center"
                                >
                                    <span class="text-medium-emphasis"
                                        >Metode</span
                                    >
                                    <BaseBadge
                                        type="shipping_method"
                                        :value="
                                            detail.replacement_shipping
                                                .method ||
                                            detail.replacement_shipping
                                                .shipping_method
                                        "
                                        size="x-small"
                                        inline
                                    />
                                </div>
                                <div
                                    v-if="detail.replacement_shipping.courier"
                                    class="d-flex justify-space-between"
                                >
                                    <span class="text-medium-emphasis"
                                        >Kurir</span
                                    >
                                    <span class="font-weight-medium">
                                        {{
                                            typeof detail.replacement_shipping
                                                .courier === "object"
                                                ? `${detail.replacement_shipping.courier.name} ${detail.replacement_shipping.courier.service ? "- " + detail.replacement_shipping.courier.service : ""}`
                                                : `${detail.replacement_shipping.courier} ${detail.replacement_shipping.service ? "- " + detail.replacement_shipping.service : ""}`
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        detail.replacement_shipping
                                            .tracking_number ||
                                        detail.replacement_shipping.courier
                                            ?.tracking_number
                                    "
                                    class="d-flex justify-space-between"
                                >
                                    <span class="text-medium-emphasis"
                                        >Nomor Resi</span
                                    >
                                    <span class="font-weight-medium">{{
                                        detail.replacement_shipping
                                            .tracking_number ||
                                        detail.replacement_shipping.courier
                                            ?.tracking_number
                                    }}</span>
                                </div>
                                <div
                                    v-if="
                                        detail.replacement_shipping
                                            .delivery_note_number
                                    "
                                    class="d-flex justify-space-between"
                                >
                                    <span class="text-medium-emphasis"
                                        >Nomor Surat Jalan</span
                                    >
                                    <span class="font-weight-medium">{{
                                        detail.replacement_shipping
                                            .delivery_note_number
                                    }}</span>
                                </div>
                                <div
                                    v-if="
                                        detail.replacement_shipping.cost !==
                                            undefined &&
                                        detail.replacement_shipping.cost > 0
                                    "
                                    class="d-flex justify-space-between"
                                >
                                    <span class="text-medium-emphasis"
                                        >Ongkos Kirim</span
                                    >
                                    <span class="font-weight-medium">
                                        Rp{{
                                            detail.replacement_shipping.cost.toLocaleString(
                                                "id-ID",
                                            )
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        detail.replacement_shipping
                                            .insurance !== undefined &&
                                        detail.replacement_shipping.insurance >
                                            0
                                    "
                                    class="d-flex justify-space-between"
                                >
                                    <span class="text-medium-emphasis"
                                        >Asuransi Pengiriman</span
                                    >
                                    <span class="font-weight-medium">
                                        Rp{{
                                            detail.replacement_shipping.insurance.toLocaleString(
                                                "id-ID",
                                            )
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        detail.replacement_shipping
                                            .total_cost !== undefined &&
                                        detail.replacement_shipping.total_cost >
                                            0
                                    "
                                    class="d-flex justify-space-between pt-1 border-t"
                                >
                                    <span
                                        class="text-medium-emphasis font-weight-bold"
                                        >Total Biaya Kirim</span
                                    >
                                    <span class="font-weight-bold text-primary">
                                        Rp{{
                                            detail.replacement_shipping.total_cost.toLocaleString(
                                                "id-ID",
                                            )
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        detail.replacement_shipping
                                            .delivery_status
                                    "
                                    class="d-flex justify-space-between"
                                >
                                    <span class="text-medium-emphasis"
                                        >Status Pengiriman</span
                                    >
                                    <span
                                        class="font-weight-medium text-caption"
                                        ><BaseBadge
                                            type="shipping_status"
                                            :value="
                                                detail.replacement_shipping
                                                    ?.delivery_status
                                            "
                                            inline
                                            chip-class="font-semibold"
                                    /></span>
                                </div>
                            </div>
                        </v-card>
                    </div>

                    <div
                        v-if="
                            detail.status_history &&
                            detail.status_history.length > 0
                        "
                    >
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="primary"
                                >mdi-timeline-clock-outline</v-icon
                            >
                            Riwayat Status
                        </h3>
                        <v-card variant="outlined" class="rounded-lg pa-4">
                            <v-timeline
                                density="compact"
                                side="end"
                                truncate-line="both"
                            >
                                <v-timeline-item
                                    v-for="(
                                        history, idx
                                    ) in detail.status_history"
                                    :key="idx"
                                    :dot-color="
                                        getTimelineDotColor(history.status)
                                    "
                                    size="x-small"
                                >
                                    <div class="d-flex flex-column">
                                        <BaseBadge
                                            type="return_status"
                                            :value="history.status"
                                            size="x-small"
                                            class="mb-1"
                                            style="width: fit-content"
                                        />
                                        <div
                                            v-if="history.note"
                                            class="text-body-2 text-medium-emphasis"
                                        >
                                            {{ history.note }}
                                        </div>
                                        <div class="text-caption text-disabled">
                                            {{
                                                formatDateTime(
                                                    history.created_at,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </v-timeline-item>
                            </v-timeline>
                        </v-card>
                    </div>
                </div>
            </v-card-text>

            <v-divider />

            <v-card-actions v-if="detail && hasActions" class="pa-4">
                <v-spacer />
                <v-btn
                    v-if="detail.actions.can_reject"
                    color="error"
                    variant="outlined"
                    class="rounded-lg px-6"
                    prepend-icon="mdi-close-circle-outline"
                    :loading="rejecting"
                    @click="handleReject"
                >
                    Tolak Retur
                </v-btn>
                <v-btn
                    v-if="detail.actions.can_approve"
                    color="success"
                    variant="outlined"
                    class="rounded-lg px-6"
                    prepend-icon="mdi-check-circle-outline"
                    @click="openApproveDialog"
                >
                    Setujui Retur
                </v-btn>
                <v-btn
                    v-if="detail.actions.can_receive"
                    color="primary"
                    variant="outlined"
                    class="rounded-lg px-6"
                    prepend-icon="mdi-package-down"
                    @click="openReceiveDialog"
                >
                    Konfirmasi Terima Barang
                </v-btn>
                <v-btn
                    v-if="detail.actions.can_ship_replacement"
                    color="success"
                    variant="outlined"
                    class="rounded-lg px-6"
                    :prepend-icon="
                        detail.return_shipping?.method === 'pickup'
                            ? 'mdi-barcode-scan'
                            : 'mdi-truck-fast-outline'
                    "
                    @click="openShipReplacementDialog"
                >
                    {{
                        detail.return_shipping?.method === "pickup"
                            ? "Input No Batch Baru"
                            : "Kirim Barang Pengganti"
                    }}
                </v-btn>
            </v-card-actions>
            <v-card-actions
                v-else-if="detail && !hasActions"
                class="pa-6 pt-4 border-t"
            >
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

    <v-dialog v-model="imagePreview" max-width="800">
        <v-card class="rounded-lg">
            <v-toolbar
                color="transparent"
                class="position-absolute w-100"
                style="z-index: 1"
            >
                <v-spacer></v-spacer>
                <v-btn
                    icon="mdi-close"
                    variant="elevated"
                    color="white"
                    class="mr-2"
                    @click="imagePreview = false"
                ></v-btn>
            </v-toolbar>
            <v-img
                :src="previewImageUrl"
                width="100%"
                contain
                class="bg-grey-darken-4"
                style="max-height: 80vh"
            ></v-img>
        </v-card>
    </v-dialog>

    <v-dialog v-model="videoPreview" max-width="800">
        <v-card class="rounded-xl bg-black position-relative">
            <video
                :src="previewVideoUrl"
                controls
                style="max-height: 80vh; width: 100%"
                autoplay
            ></video>
            <v-btn
                icon="mdi-close"
                variant="flat"
                color="rgba(0,0,0,0.5)"
                class="text-white position-absolute"
                style="top: 8px; right: 8px; z-index: 10"
                size="small"
                @click="videoPreview = false"
            />
        </v-card>
    </v-dialog>

    <v-dialog v-model="rejectConfirmDialog" max-width="460" persistent>
        <v-card class="rounded-xl pa-2">
            <v-card-title class="text-h6 font-weight-bold">
                Tolak Retur?
            </v-card-title>
            <v-card-text>
                <p class="text-body-2 text-medium-emphasis mb-4">
                    Apakah Anda yakin ingin menolak pengajuan retur ini?
                    Tindakan ini tidak bisa dibatalkan.
                </p>
                <v-textarea
                    v-model="rejectNote"
                    label="Alasan Penolakan"
                    variant="outlined"
                    density="compact"
                    rows="3"
                    hide-details="auto"
                    placeholder="Opsional — jelaskan alasan penolakan"
                />
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn
                    variant="outlined"
                    class="rounded-lg"
                    @click="rejectConfirmDialog = false"
                >
                    Batal
                </v-btn>
                <v-btn
                    color="error"
                    variant="flat"
                    class="rounded-lg"
                    :loading="rejecting"
                    @click="confirmReject"
                >
                    Ya, Tolak
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <ReturnApproveDialog
        ref="approveDialogRef"
        @success="handleActionSuccess"
    />
    <ReturnReceiveDialog
        ref="receiveDialogRef"
        @success="handleActionSuccess"
    />
    <ReturnShipReplacementDialog
        ref="shipReplacementDialogRef"
        @success="handleActionSuccess"
    />
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useBadge } from "@/shared/composables/useBadge";
import transactionReturnService from "@/admin/services/transaction-return.service";
import type { TransactionReturnDetail } from "@/admin/types/transaction-return";
import ReturnApproveDialog from "./ReturnApproveDialog.vue";
import ReturnReceiveDialog from "./ReturnReceiveDialog.vue";
import ReturnShipReplacementDialog from "./ReturnShipReplacementDialog.vue";

const { formatDate, formatDateTime, formatLocation } = useFormatter();
const { getBadgeData } = useBadge();

import { useSnackbarStore } from "@/shared/stores/snackbar";

const emit = defineEmits(["updated"]);
const snackbar = useSnackbarStore();

const dialog = ref(false);
const loading = ref(false);
const rejecting = ref(false);
const detail = ref<TransactionReturnDetail | null>(null);
const imagePreview = ref(false);
const previewImageUrl = ref("");
const videoPreview = ref(false);
const previewVideoUrl = ref("");
const rejectConfirmDialog = ref(false);
const rejectNote = ref("");

const approveDialogRef = ref<InstanceType<typeof ReturnApproveDialog> | null>(
    null,
);
const receiveDialogRef = ref<InstanceType<typeof ReturnReceiveDialog> | null>(
    null,
);
const shipReplacementDialogRef = ref<InstanceType<
    typeof ReturnShipReplacementDialog
> | null>(null);

const hasActions = computed(() => {
    if (!detail.value?.actions) return false;
    const a = detail.value.actions;
    return (
        a.can_approve || a.can_reject || a.can_receive || a.can_ship_replacement
    );
});

const getTimelineDotColor = (status: string): string => {
    const data = getBadgeData("return_status", status);
    const colorMap: Record<string, string> = {
        warning: "warning",
        info: "info",
        secondary: "secondary",
        success: "success",
        error: "error",
    };
    return colorMap[data.color] || "grey";
};

const open = async (id: number | string) => {
    dialog.value = true;
    loading.value = true;
    detail.value = null;
    try {
        detail.value = await transactionReturnService.getDetail(id);
    } catch (error: any) {
        console.error("Failed to load return detail:", error);
        snackbar.showMessage("Gagal memuat detail retur", "error");
    } finally {
        loading.value = false;
    }
};

const close = () => {
    dialog.value = false;
};

const openImagePreview = (url: string) => {
    previewImageUrl.value = url;
    imagePreview.value = true;
};

const openVideoPreview = (url: string) => {
    previewVideoUrl.value = url;
    videoPreview.value = true;
};

const handleReject = () => {
    rejectNote.value = "";
    rejectConfirmDialog.value = true;
};

const confirmReject = async () => {
    if (!detail.value) return;
    rejecting.value = true;
    try {
        await transactionReturnService.reject(detail.value.id, {
            note: rejectNote.value || undefined,
        });
        snackbar.showMessage("Berhasil menolak retur", "success");
        rejectConfirmDialog.value = false;
        close();
        emit("updated");
    } catch (error: any) {
        console.error("Failed to reject return:", error);
        const message = error.response?.data?.message || "Gagal menolak retur";
        snackbar.showMessage(message, "error");
    } finally {
        rejecting.value = false;
    }
};

const openApproveDialog = () => {
    if (!detail.value) return;
    approveDialogRef.value?.open(detail.value);
};

const openReceiveDialog = () => {
    if (!detail.value) return;
    receiveDialogRef.value?.open(detail.value);
};

const openShipReplacementDialog = () => {
    if (!detail.value) return;
    shipReplacementDialogRef.value?.open(detail.value);
};

const handleActionSuccess = () => {
    close();
    emit("updated");
};

defineExpose({ open });
</script>
