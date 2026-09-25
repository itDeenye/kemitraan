<template>
    <div class="screen-body" style="padding-bottom: 80px">
        <div v-if="isLoadingInitial" class="card pa-8 text-center mt-4">
            <v-progress-circular
                indeterminate
                color="primary"
                size="32"
                class="mb-3"
            />
            <div class="text-body-2 text-medium-emphasis">
                Memuat data penerimaan barang...
            </div>
        </div>

        <v-form v-else ref="formRef">
            <div class="section-heading mb-2 mt-3">
                <h3>Informasi Penerimaan Barang</h3>
            </div>

            <div v-if="selectedReceipt" class="card mb-3" style="padding: 12px">
                <div style="display: flex; align-items: flex-start; gap: 12px">
                    <span
                        class="row-icon w-[25px] h-[25px] shrink-0"
                        style="margin-top: 2px"
                    >
                        <v-icon
                            icon="mdi-file-document-outline"
                            color="var(--wine)"
                            size="24"
                        />
                    </span>
                    <div style="flex: 1">
                        <div class="d-flex align-start flex-wrap gap-2 mb-1">
                            <strong
                                style="
                                    font-size: 13px;
                                    color: var(--text);
                                    word-break: break-word;
                                "
                                >{{ selectedReceipt.receive_number }}</strong
                            >
                            <v-chip
                                size="x-small"
                                color="primary"
                                variant="tonal"
                                class="shrink-0"
                            >
                                {{ selectedReceipt.transaction.code }}
                            </v-chip>
                        </div>
                        <div
                            style="
                                color: var(--muted);
                                font-size: 12px;
                                margin-bottom: 2px;
                            "
                        >
                            Diterima:
                            {{ formatDate(selectedReceipt.received_at) }}
                        </div>
                        <div
                            style="
                                color: var(--danger);
                                font-size: 12px;
                                font-weight: 600;
                                margin-bottom: 4px;
                            "
                        >
                            Batas Retur:
                            {{ formatDate(selectedReceipt.return_deadline) }}
                        </div>
                        <div style="color: var(--text); font-size: 12px">
                            Pengirim:
                            <strong>{{
                                selectedReceipt.seller.name || "Gudang Pusat"
                            }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-caption text-error pa-2 text-center">
                Data penerimaan barang tidak ditemukan.
            </div>

            <template v-if="selectedReceipt">
                <div class="section-heading mb-2 mt-4">
                    <h3>Dokumen Pengiriman Retur</h3>
                </div>
                <div class="card pa-4 mb-3">
                    <v-text-field
                        v-model="form.delivery_note_number"
                        label="Nomor Surat Jalan Penerimaan"
                        variant="outlined"
                        density="compact"
                        hide-details="auto"
                        readonly
                    />
                    <div class="text-caption text-medium-emphasis mt-2">
                        Nomor surat jalan dan nomor batch otomatis mengikuti
                        data penerimaan yang dipilih.
                    </div>
                </div>
            </template>

            <template v-if="selectedReceipt">
                <div class="section-heading mb-2 mt-4">
                    <h3>Rincian Produk yang Diretur</h3>
                </div>

                <div
                    v-if="form.items.length === 0"
                    class="text-caption text-medium-emphasis text-center pa-4 card mb-3"
                >
                    Tidak ada item yang dapat diretur pada penerimaan ini.
                </div>

                <div class="checkout-list mb-3" v-else>
                    <div
                        v-for="(item, index) in form.items"
                        :key="item.goods_receive_detail_id"
                        class="card checkout-item"
                        :style="
                            item.selected
                                ? 'border: 1px solid var(--wine); background: rgba(183, 28, 28, 0.02);'
                                : 'border: 1px solid #eaeaea; background: #fff;'
                        "
                    >
                        <div style="padding-top: 2px">
                            <v-checkbox
                                v-model="item.selected"
                                density="compact"
                                hide-details
                                color="primary"
                                class="mt-0 pt-0"
                                @update:model-value="onItemSelectionChanged"
                            />
                        </div>

                        <div
                            class="product-art"
                            style="border: 1px solid #eaeaea; background: #fff"
                        >
                            <v-img
                                v-if="item.product_image"
                                :src="item.product_image"
                                cover
                            />
                            <v-icon
                                v-else
                                icon="mdi-image-outline"
                                color="#ccc"
                                size="24"
                            />
                        </div>

                        <div
                            class="checkout-copy"
                            style="flex: 1; min-width: 0"
                        >
                            <div
                                class="d-flex align-start justify-space-between"
                                style="gap: 4px"
                            >
                                <strong
                                    style="
                                        font-size: 13px;
                                        min-width: 0;
                                        word-break: break-word;
                                    "
                                    >{{
                                        item.product_name ||
                                        `Produk #${item.product_id}`
                                    }}</strong
                                >
                                <span
                                    style="
                                        font-size: 11px;
                                        font-weight: 500;
                                        color: var(--primary);
                                        white-space: nowrap;
                                    "
                                >
                                    Max: {{ item.max_quantity }} pcs
                                </span>
                            </div>
                            <div
                                class="d-flex align-center flex-wrap mt-1"
                                style="gap: 6px"
                            >
                                <span
                                    v-if="item.product_code"
                                    style="font-size: 11px; color: var(--muted)"
                                    >Kode: {{ item.product_code }}</span
                                >
                                <span
                                    v-if="item.batch_number"
                                    style="
                                        font-size: 10px;
                                        padding: 2px 6px;
                                        border-radius: 4px;
                                        background: rgba(
                                            var(--v-theme-primary),
                                            0.1
                                        );
                                        color: var(--primary);
                                    "
                                >
                                    No. Batch: {{ item.batch_number }}
                                </span>
                                <span
                                    v-if="item.expire_date"
                                    style="
                                        font-size: 10px;
                                        padding: 2px 6px;
                                        border-radius: 4px;
                                        background: #fff8e1;
                                        color: #f57f17;
                                    "
                                >
                                    Exp: {{ formatDate(item.expire_date) }}
                                </span>
                            </div>

                            <div
                                v-if="item.selected"
                                class="mt-3 pt-3"
                                style="border-top: 1px dashed var(--border)"
                            >
                                <div
                                    class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2"
                                >
                                    <span
                                        style="
                                            font-size: 12px;
                                            font-weight: 600;
                                        "
                                        >Jumlah Retur:</span
                                    >
                                    <div
                                        class="quantity-control"
                                        style="margin-top: 0"
                                    >
                                        <button
                                            type="button"
                                            @click="
                                                item.quantity--;
                                                onItemSelectionChanged();
                                            "
                                            :disabled="item.quantity <= 1"
                                        >
                                            -
                                        </button>
                                        <input
                                            type="number"
                                            v-model.number="item.quantity"
                                            :min="1"
                                            :max="item.max_quantity"
                                            class="qty-input"
                                            @input="onItemQtyInput(item)"
                                        />
                                        <button
                                            type="button"
                                            @click="
                                                item.quantity++;
                                                onItemSelectionChanged();
                                            "
                                            :disabled="
                                                item.quantity >=
                                                item.max_quantity
                                            "
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>
                                <v-text-field
                                    v-model="item.reason"
                                    label="Alasan retur produk ini *"
                                    placeholder="Contoh: Kemasan rusak, bocor, expired"
                                    variant="outlined"
                                    density="compact"
                                    hide-details="auto"
                                    :rules="[
                                        (v) => !!v || 'Alasan wajib diisi',
                                    ]"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template v-if="selectedReceipt">
                <div class="section-heading mb-2 mt-4">
                    <h3>Alamat Asal Retur</h3>
                </div>
                <div
                    v-if="isLoadingAddresses"
                    class="card pa-4 text-center mb-3"
                >
                    <v-progress-circular
                        indeterminate
                        size="20"
                        color="primary"
                    />
                </div>
                <div
                    v-else-if="addressList.length > 0"
                    class="mb-3 d-flex flex-column"
                    style="gap: 8px"
                >
                    <div
                        v-for="addr in addressList"
                        :key="addr.id"
                        class="rate-option card"
                        :class="{ active: form.address_id === addr.id }"
                        @click="
                            form.address_id = addr.id;
                            onAddressChanged();
                        "
                    >
                        <div
                            style="
                                display: flex;
                                align-items: flex-start;
                                gap: 12px;
                                flex: 1;
                            "
                        >
                            <div
                                class="rate-radio"
                                :class="{
                                    selected: form.address_id === addr.id,
                                }"
                                style="margin-top: 2px"
                            ></div>
                            <div class="address-details" style="flex: 1">
                                <strong
                                    style="font-size: 13px; color: var(--text)"
                                    >{{ addr.label }}
                                    <span
                                        v-if="addr.is_default"
                                        style="
                                            font-size: 10px;
                                            padding: 2px 6px;
                                            border-radius: 4px;
                                            background: #ffebee;
                                            color: var(--danger);
                                            margin-left: 4px;
                                        "
                                        >Utama</span
                                    ></strong
                                >
                                <span
                                    style="
                                        display: block;
                                        font-size: 12px;
                                        color: var(--muted);
                                        margin: 2px 0;
                                    "
                                    >{{ addr.recipient }} ·
                                    {{ addr.phone }}</span
                                >
                                <p
                                    style="
                                        margin: 0;
                                        font-size: 12px;
                                        color: var(--muted);
                                    "
                                >
                                    {{
                                        formatLocation({
                                            ...addr,
                                            ...(addr.region || {}),
                                        })
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="text-caption text-error card pa-3 mb-3 text-center"
                >
                    Tidak ada data alamat.
                </div>
            </template>

            <template v-if="selectedReceipt">
                <div class="section-heading mb-2 mt-4">
                    <h3>Keterangan & Bukti Retur</h3>
                </div>
                <div class="card mb-3" style="padding: 12px">
                    <v-textarea
                        v-model="form.description"
                        label="Keterangan / Deskripsi Kerusakan *"
                        placeholder="Jelaskan kondisi barang secara lengkap..."
                        variant="outlined"
                        density="comfortable"
                        rows="3"
                        hide-details="auto"
                        class="mb-4"
                        :rules="[(v) => !!v || 'Keterangan wajib diisi']"
                    ></v-textarea>

                    <div class="mb-4">
                        <div
                            class="d-flex align-center justify-space-between mb-2"
                        >
                            <strong class="text-body-2"
                                >Bukti Foto (Wajib, Maks 3 Foto) *</strong
                            >
                            <span class="text-caption text-medium-emphasis"
                                >{{ form.image_urls.length }} / 3 Foto</span
                            >
                        </div>

                        <div
                            v-if="form.image_urls.length > 0"
                            class="d-flex flex-wrap mb-3"
                            style="gap: 10px"
                        >
                            <div
                                v-for="(img, idx) in form.image_urls"
                                :key="idx"
                                class="position-relative rounded-lg overflow-hidden border"
                                style="
                                    width: 80px;
                                    height: 80px;
                                    background: #000;
                                "
                            >
                                <v-img
                                    :src="img"
                                    width="80"
                                    height="80"
                                    cover
                                />
                                <v-btn
                                    icon="mdi-close"
                                    size="x-small"
                                    color="error"
                                    variant="flat"
                                    density="compact"
                                    class="position-absolute"
                                    style="
                                        top: 2px;
                                        right: 2px;
                                        width: 20px;
                                        height: 20px;
                                        min-width: 20px;
                                    "
                                    @click="removePhoto(idx)"
                                />
                            </div>
                        </div>

                        <div
                            v-if="form.image_urls.length < 3"
                            class="upload-proof-box cursor-pointer pa-4 rounded-xl border text-center"
                            @click="photoFileInput?.click()"
                        >
                            <v-progress-circular
                                v-if="isUploadingPhoto"
                                indeterminate
                                color="primary"
                                size="24"
                                class="mb-2"
                            />
                            <v-icon
                                v-else
                                icon="mdi-camera-plus-outline"
                                size="32"
                                color="primary"
                                class="mb-2"
                            />
                            <div
                                class="text-body-2 font-weight-bold"
                                style="color: var(--ink)"
                            >
                                {{
                                    isUploadingPhoto
                                        ? `Mengunggah Foto (${uploadProgressPhoto}%)...`
                                        : "Pilih Foto Bukti"
                                }}
                            </div>
                            <div class="text-caption text-medium-emphasis mt-1">
                                Format JPG, PNG · Maksimal 5 MB per foto
                            </div>
                            <input
                                type="file"
                                ref="photoFileInput"
                                class="d-none"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                @change="onPhotoSelected"
                            />
                        </div>
                    </div>

                    <div>
                        <div
                            class="d-flex align-center justify-space-between mb-2"
                        >
                            <strong class="text-body-2"
                                >Bukti Video (Opsional, Maks 1 Video)</strong
                            >
                            <span
                                v-if="form.video_url"
                                class="text-caption text-success font-weight-medium"
                                >1 Video Terunggah</span
                            >
                        </div>

                        <div
                            v-if="form.video_url"
                            class="mb-3 rounded-lg overflow-hidden border pa-2 bg-grey-lighten-4"
                        >
                            <video
                                :src="form.video_url"
                                controls
                                style="
                                    max-width: 100%;
                                    max-height: 200px;
                                    border-radius: 8px;
                                    background: #000;
                                    display: block;
                                    margin: 0 auto;
                                "
                            ></video>
                            <div class="text-center mt-2">
                                <v-btn
                                    size="small"
                                    color="error"
                                    variant="text"
                                    prepend-icon="mdi-delete"
                                    class="text-none"
                                    @click="form.video_url = null"
                                >
                                    Hapus Video
                                </v-btn>
                            </div>
                        </div>

                        <div
                            v-else
                            class="upload-proof-box cursor-pointer pa-4 rounded-xl border text-center"
                            @click="videoFileInput?.click()"
                        >
                            <v-progress-circular
                                v-if="isUploadingVideo"
                                indeterminate
                                color="primary"
                                size="24"
                                class="mb-2"
                            />
                            <v-icon
                                v-else
                                icon="mdi-video-plus-outline"
                                size="32"
                                color="primary"
                                class="mb-2"
                            />
                            <div
                                class="text-body-2 font-weight-bold"
                                style="color: var(--ink)"
                            >
                                {{
                                    isUploadingVideo
                                        ? `Mengunggah Video (${uploadProgressVideo}%)...`
                                        : "Pilih / Rekam Video Bukti"
                                }}
                            </div>
                            <div class="text-caption text-medium-emphasis mt-1">
                                Format MP4, MOV, WebM · Maksimal 20 MB
                            </div>
                            <input
                                type="file"
                                ref="videoFileInput"
                                class="d-none"
                                accept="video/mp4,video/quicktime,video/webm"
                                @change="onVideoSelected"
                            />
                        </div>
                    </div>
                </div>
            </template>

            <template v-if="selectedReceipt">
                <div class="section-heading mb-2 mt-4">
                    <h3>Opsi Pengiriman Retur</h3>
                </div>
                <div class="mb-3">
                    <div
                        style="
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 8px;
                            margin-bottom: 16px;
                        "
                    >
                        <div
                            class="card delivery-option-card"
                            :class="{
                                active:
                                    form.shipping_method === 'courier_express',
                            }"
                            @click="
                                form.shipping_method = 'courier_express';
                                onShippingMethodChanged();
                            "
                        >
                            <v-icon icon="mdi-truck-outline" size="24" />
                            <strong>Dikirim via Kurir</strong>
                        </div>
                        <div
                            class="card delivery-option-card"
                            :class="{
                                active: form.shipping_method === 'pickup',
                            }"
                            @click="
                                form.shipping_method = 'pickup';
                                onShippingMethodChanged();
                            "
                        >
                            <v-icon icon="mdi-storefront-outline" size="24" />
                            <strong>Serah di Tempat</strong>
                        </div>
                    </div>

                    <template v-if="form.shipping_method === 'pickup'">
                        <v-alert
                            type="info"
                            variant="tonal"
                            density="comfortable"
                            class="text-body-2 rounded-xl mb-2"
                        >
                            <div class="font-weight-bold mb-1">
                                Ambil Langsung / Serah di Tempat
                            </div>
                            <div>
                                Setelah pengajuan dibuat, sistem langsung
                                menampilkan
                                <strong>Kode Verifikasi 5 Digit</strong>
                                untuk ditunjukkan ke admin saat penyerahan
                                barang di gudang.
                            </div>
                        </v-alert>
                    </template>

                    <template
                        v-else-if="form.shipping_method === 'courier_express'"
                    >
                        <div class="section-heading mb-2 mt-4">
                            <h3>Kurir Pengiriman</h3>
                            <v-btn
                                size="x-small"
                                variant="text"
                                color="primary"
                                :loading="isLoadingRates"
                                @click="calculateReturnRates"
                            >
                                <v-icon start size="14">mdi-refresh</v-icon> Cek
                                Ulang Tarif
                            </v-btn>
                        </div>

                        <div
                            v-if="isLoadingRates"
                            class="pa-6 text-center rounded-xl border mb-3 bg-grey-lighten-4"
                            style="border: 1px dashed var(--border) !important"
                        >
                            <v-progress-circular
                                indeterminate
                                size="24"
                                color="primary"
                                class="mb-2"
                            />
                            <div class="text-caption text-medium-emphasis">
                                Menghitung tarif kurir pengiriman...
                            </div>
                        </div>

                        <div
                            v-else-if="availableRates.length === 0"
                            class="pa-4 text-center rounded-xl border text-error mb-3"
                            style="
                                background: #fff5f5 !important;
                                border-color: #ffcdd2 !important;
                            "
                        >
                            <v-icon
                                icon="mdi-alert-circle-outline"
                                size="24"
                                class="mb-1"
                            />
                            <div class="text-caption font-weight-medium">
                                {{
                                    ratesErrorMessage ||
                                    "Belum ada tarif kurir tersedia. Pastikan produk dan alamat penjemputan sudah dipilih."
                                }}
                            </div>
                        </div>

                        <div
                            v-else
                            class="d-flex flex-column mb-4"
                            style="gap: 8px"
                        >
                            <div
                                v-for="rate in availableRates"
                                :key="`${rate.courier_code}-${rate.service_type}`"
                                class="rate-option card"
                                :class="{
                                    active:
                                        selectedRate?.service_type ===
                                            rate.service_type &&
                                        selectedRate?.courier_code ===
                                            rate.courier_code,
                                }"
                                @click="selectRate(rate)"
                            >
                                <div
                                    style="
                                        display: flex;
                                        align-items: flex-start;
                                        gap: 12px;
                                        flex: 1;
                                    "
                                >
                                    <div
                                        class="rate-radio"
                                        :class="{
                                            selected:
                                                selectedRate?.service_type ===
                                                    rate.service_type &&
                                                selectedRate?.courier_code ===
                                                    rate.courier_code,
                                        }"
                                        style="margin-top: 2px"
                                    ></div>
                                    <img
                                        v-if="rate.logo_url"
                                        :src="rate.logo_url"
                                        :alt="rate.courier_name"
                                        style="
                                            height: 22px;
                                            max-width: 60px;
                                            object-fit: contain;
                                            margin-top: 2px;
                                        "
                                    />
                                    <div
                                        class="address-details"
                                        style="flex: 1; margin-left: 4px"
                                    >
                                        <strong
                                            style="
                                                font-size: 13px;
                                                color: var(--text);
                                            "
                                        >
                                            {{ rate.courier_name }} ({{
                                                rate.service_type
                                            }})
                                        </strong>
                                        <span
                                            style="
                                                display: block;
                                                font-size: 12px;
                                                color: var(--muted);
                                                margin: 2px 0;
                                            "
                                        >
                                            Estimasi:
                                            {{ rate.etd || "2-3" }} hari
                                            <span
                                                v-if="
                                                    rate.force_insurance &&
                                                    rate.insurance > 0
                                                "
                                                style="
                                                    color: var(--success);
                                                    font-weight: 500;
                                                "
                                            >
                                                · + Asuransi Rp
                                                {{
                                                    rate.insurance.toLocaleString(
                                                        "id-ID",
                                                    )
                                                }}
                                            </span>
                                        </span>
                                    </div>
                                    <strong
                                        style="
                                            font-size: 13px;
                                            color: var(--wine);
                                        "
                                    >
                                        Rp
                                        {{
                                            (
                                                rate.cost +
                                                (rate.insurance || 0)
                                            ).toLocaleString("id-ID")
                                        }}
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <template v-if="selectedReceipt">
                <div class="card summary-card mt-4">
                    <div class="summary-line">
                        <span>Total Produk Dipilih</span>
                        <strong
                            >{{ selectedItemsCount }} Produk ({{
                                totalQuantitySelected
                            }}
                            pcs)</strong
                        >
                    </div>
                    <div class="summary-line">
                        <span>Metode Pengiriman</span>
                        <strong>{{
                            form.shipping_method === "courier_express"
                                ? "Ekspedisi Express"
                                : "Serah di Tempat"
                        }}</strong>
                    </div>
                    <div
                        v-if="
                            form.shipping_method === 'courier_express' &&
                            form.shipping_cost > 0
                        "
                        class="summary-line"
                    >
                        <span>Ongkos Kirim Retur</span>
                        <strong style="color: var(--wine)"
                            >Rp{{
                                form.shipping_cost.toLocaleString("id-ID")
                            }}</strong
                        >
                    </div>
                    <div class="summary-line total">
                        <span>Total Nilai Retur</span>
                        <span style="color: var(--primary)"
                            >Rp{{
                                totalNilaiReturEstimasi.toLocaleString("id-ID")
                            }}</span
                        >
                    </div>
                </div>

                <div class="mt-4 mb-4">
                    <v-alert
                        v-if="formValidationMessage"
                        type="warning"
                        variant="tonal"
                        density="compact"
                        class="mb-3 text-body-2 rounded-lg"
                    >
                        {{ formValidationMessage }}
                    </v-alert>
                    <button
                        class="primary-button block"
                        @click.prevent="submitReturn"
                        :disabled="
                            isSubmitting || isUploadingPhoto || isUploadingVideo
                        "
                    >
                        <span v-if="!isSubmitting">Kirim Pengajuan Retur</span>
                        <v-progress-circular
                            v-else
                            indeterminate
                            size="20"
                            color="white"
                        ></v-progress-circular>
                    </button>
                </div>
            </template>
        </v-form>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import returnService from "@/member/services/return.service";
import profileService from "@/member/services/profile.service";
import { useMediaUpload } from "@/shared/composables/useMediaUpload";
import { useFormatter } from "@/shared/composables/useFormatter";
import type { EligibleReceipt } from "@/member/types/inventory";
import type {
    ReturnCourierRate,
    ReturnPickupSchedule,
} from "@/member/types/return";
import { encodeRouteId } from "@/shared/utils/route-id";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const { formatDate, formatLocation } = useFormatter();
const snackbarStore = useSnackbarStore();

interface FormItem {
    goods_receive_detail_id: number;
    product_id: number;
    product_name: string;
    product_code: string;
    product_image?: string | null;
    product_price: number;
    batch_number: string;
    expire_date?: string | null;
    max_quantity: number;
    quantity: number;
    reason: string;
    selected: boolean;
}

const router = useRouter();
const route = useRoute();

// Media Upload Composables
const {
    isUploading: isUploadingPhoto,
    progress: uploadProgressPhoto,
    upload: uploadPhoto,
} = useMediaUpload();
const {
    isUploading: isUploadingVideo,
    progress: uploadProgressVideo,
    upload: uploadVideo,
} = useMediaUpload();

const photoFileInput = ref<HTMLInputElement | null>(null);
const videoFileInput = ref<HTMLInputElement | null>(null);

// Loading states
const isLoadingInitial = ref(true);
const isLoadingAddresses = ref(false);
const isLoadingRates = ref(false);
const isSubmitting = ref(false);

// Data states
const eligibleReceipts = ref<EligibleReceipt[]>([]);
const selectedReceiptId = ref<number | null>(null);
const selectedReceipt = ref<EligibleReceipt | null>(null);
const addressList = ref<any[]>([]);
const availableRates = ref<ReturnCourierRate[]>([]);
const selectedRate = ref<ReturnCourierRate | null>(null);
const ratesErrorMessage = ref<string>("");
const formRef = ref<any>(null);

const defaultForm = () => ({
    goods_receive_id: 0,
    address_id: 0,
    description: "",
    image_urls: [] as string[],
    video_url: null as string | null,
    shipping_method: "pickup" as "pickup" | "courier_express",
    delivery_note_number: "",
    shipping_cost: 0,
    courier: null as any,
    items: [] as FormItem[],
});

const form = ref(defaultForm());

const shippingMethodOptions = [
    { title: "Ambil di Tempat / Serahkan Langsung", value: "pickup" },
    { title: "Dikirim via Kurir", value: "courier_express" },
];

const selectedItemsCount = computed(() => {
    return form.value.items.filter((i) => i.selected).length;
});

const totalQuantitySelected = computed(() => {
    return form.value.items
        .filter((i) => i.selected)
        .reduce((sum, item) => sum + (Number(item.quantity) || 0), 0);
});

const totalNilaiReturEstimasi = computed(() => {
    return form.value.items
        .filter((i) => i.selected)
        .reduce(
            (sum, item) =>
                sum +
                (Number(item.quantity) || 0) *
                    (Number(item.product_price) || 0),
            0,
        );
});

const formValidationMessage = computed(() => {
    if (!form.value.goods_receive_id) {
        return "Pilih penerimaan barang yang akan diretur.";
    }
    if (!form.value.address_id) {
        return "Pilih alamat asal retur.";
    }

    const selectedItems = form.value.items.filter((i) => i.selected);
    if (selectedItems.length === 0) {
        return "Pilih minimal satu produk yang akan diretur.";
    }

    for (const item of selectedItems) {
        if (
            !item.goods_receive_detail_id ||
            !item.quantity ||
            item.quantity < 1 ||
            item.quantity > item.max_quantity
        ) {
            return "Periksa kembali jumlah produk yang akan diretur.";
        }
        if (!item.reason?.trim()) {
            return `Isi alasan retur untuk ${item.product_name}.`;
        }
    }

    if (!form.value.description?.trim()) {
        return "Isi keterangan atau deskripsi kerusakan barang.";
    }
    if (form.value.image_urls.length === 0) {
        return "Unggah minimal satu foto bukti retur.";
    }
    if (!form.value.delivery_note_number?.trim()) {
        return "Nomor surat jalan pada data penerimaan belum tersedia.";
    }

    if (form.value.shipping_method === "courier_express") {
        if (!selectedRate.value || !form.value.courier) {
            return "Pilih layanan kurir untuk pengiriman retur.";
        }
    }

    return "";
});

const isFormValid = computed(() => formValidationMessage.value === "");

// Photo Upload Handler
const onPhotoSelected = async (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (!target.files || target.files.length === 0) return;

    const remainingSlots = 3 - form.value.image_urls.length;
    if (remainingSlots <= 0) {
        snackbarStore.showMessage("Maksimal 3 foto bukti", "warning");
        return;
    }

    const filesToUpload = Array.from(target.files).slice(0, remainingSlots);
    for (const file of filesToUpload) {
        try {
            const url = await uploadPhoto(file, "return_proof");
            if (url) {
                form.value.image_urls.push(url);
            }
        } catch (err: any) {
            snackbarStore.showMessage(
                err.message || "Gagal mengunggah foto",
                "error",
            );
        }
    }
    target.value = "";
};

const removePhoto = (index: number) => {
    form.value.image_urls.splice(index, 1);
};

// Video Upload Handler
const onVideoSelected = async (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (!target.files || target.files.length === 0) return;

    const file = target.files[0];
    try {
        const url = await uploadVideo(file, "return_proof_video");
        if (url) {
            form.value.video_url = url;
        }
    } catch (err: any) {
        snackbarStore.showMessage(
            err.message || "Gagal mengunggah video",
            "error",
        );
    }
    target.value = "";
};

const onReceiptSelected = (receiveId: number | string) => {
    const numericId = Number(receiveId);
    const receipt = eligibleReceipts.value.find(
        (r) => Number(r.receive_id) === numericId,
    );
    if (!receipt) return;

    selectedReceipt.value = receipt;
    form.value.goods_receive_id = Number(receipt.receive_id);
    form.value.delivery_note_number = receipt.delivery_note_number || "";

    const items = (receipt as any).items || [];
    if (items.length > 0) {
        form.value.items = items.map((item: any) => ({
            goods_receive_detail_id: Number(item.goods_receive_detail_id),
            product_id: Number(item.product_id),
            product_name: item.product_name,
            product_code: item.product_code,
            product_image: formatProductImage(item.product_image),
            product_price: Number(item.product_price) || 0,
            batch_number: item.batch_number,
            expire_date: item.expire_date,
            max_quantity: Number(item.remaining_quantity) || 1,
            quantity: Math.min(1, Number(item.remaining_quantity) || 1),
            reason: "",
            selected: true,
        }));
    } else {
        form.value.items = [];
    }

    if (form.value.shipping_method === "courier_express") {
        calculateReturnRates();
    }
};

const formatProductImage = (img?: string | null) => {
    if (!img) return null;
    if (img.startsWith("http://") || img.startsWith("https://")) return img;
    if (img.startsWith("/")) return img;
    return `/${img}`;
};

const fetchAddresses = async () => {
    isLoadingAddresses.value = true;
    try {
        const res = await profileService.getAddresses();
        const list = res.data || [];
        addressList.value = list;
        if (list.length > 0) {
            const defaultAddr = list.find((a: any) => a.is_default) || list[0];
            form.value.address_id = defaultAddr.id;
        }
    } catch (e) {
        console.error("Failed to load addresses:", e);
    } finally {
        isLoadingAddresses.value = false;
    }
};

const calculateReturnRates = async () => {
    if (form.value.shipping_method !== "courier_express") return;
    const selectedItems = form.value.items
        .filter((i) => i.selected && i.quantity > 0)
        .map((i) => ({
            goods_receive_detail_id: i.goods_receive_detail_id,
            quantity: i.quantity,
        }));

    if (
        !form.value.goods_receive_id ||
        !form.value.address_id ||
        selectedItems.length === 0
    ) {
        availableRates.value = [];
        selectedRate.value = null;
        return;
    }

    isLoadingRates.value = true;
    ratesErrorMessage.value = "";
    availableRates.value = [];
    selectedRate.value = null;

    try {
        const payload = {
            goods_receive_id: form.value.goods_receive_id,
            address_id: form.value.address_id,
            items: selectedItems,
        };

        const courierRes = await returnService.getReturnCouriers(payload);
        if (courierRes?.results) {
            availableRates.value = courierRes.results;
            if (availableRates.value.length > 0) {
                selectRate(availableRates.value[0]);
            }
        }
    } catch (e: any) {
        ratesErrorMessage.value =
            e.response?.data?.message || "Gagal memuat tarif pengiriman.";
    } finally {
        isLoadingRates.value = false;
    }
};

const selectRate = (rate: ReturnCourierRate) => {
    selectedRate.value = rate;
    form.value.shipping_cost = rate.cost + (rate.insurance || 0);
    form.value.courier = {
        courier_code: rate.courier_code,
        courier_name: rate.courier_name,
        service_type: rate.service_type,
        cost: rate.cost,
        etd: rate.etd,
        drop_off_available: rate.drop_off_available,
        force_insurance: rate.force_insurance,
        insurance: rate.insurance,
        pickup_method: rate.pickup_method || "PICKUP",
        logo_url: rate.logo_url || null,
    };
};

const onAddressChanged = () => {
    if (form.value.shipping_method === "courier_express") {
        calculateReturnRates();
    }
};

const onShippingMethodChanged = () => {
    if (form.value.shipping_method === "pickup") {
        form.value.courier = null;
        form.value.shipping_cost = 0;
        selectedRate.value = null;
    } else if (form.value.shipping_method === "courier_express") {
        calculateReturnRates();
    }
};

const onItemSelectionChanged = () => {
    if (form.value.shipping_method === "courier_express") {
        calculateReturnRates();
    }
};

const onItemQtyInput = (item: FormItem) => {
    if (!item.quantity || item.quantity < 1) {
        item.quantity = 1;
    } else if (item.quantity > item.max_quantity) {
        item.quantity = item.max_quantity;
    }
    onItemSelectionChanged();
};

const submitReturn = async () => {
    if (!isFormValid.value) {
        snackbarStore.showMessage(formValidationMessage.value, "warning");
        return;
    }

    const selectedItems = form.value.items
        .filter((i) => i.selected)
        .map((i) => ({
            goods_receive_detail_id: i.goods_receive_detail_id,
            quantity: i.quantity,
            reason: i.reason,
            batch_number: i.batch_number,
        }));

    const payload: Record<string, any> = {
        goods_receive_id: form.value.goods_receive_id,
        address_id: form.value.address_id,
        description: form.value.description,
        image_urls: form.value.image_urls,
        video_url: form.value.video_url || null,
        shipping_method: form.value.shipping_method,
        delivery_note_number: form.value.delivery_note_number,
        items: selectedItems,
    };

    if (form.value.shipping_method === "courier_express") {
        payload.courier = {
            ...form.value.courier,
        };
    }

    isSubmitting.value = true;
    try {
        const existingReturn = await returnService.findExistingByReceipt(
            form.value.goods_receive_id,
            selectedReceipt.value?.receive_number,
        );
        if (existingReturn) {
            sessionStorage.removeItem("return_eligible_receipt");
            snackbarStore.showMessage(
                "Penerimaan ini sudah diajukan retur. Menampilkan detail pengajuan.",
                "info",
            );
            await router.replace(
                `/member/stock/returns/${encodeRouteId(existingReturn.id)}`,
            );
            return;
        }

        const createdReturn = await returnService.storeReturn(payload as any);
        snackbarStore.showMessage("Pengajuan retur berhasil dikirim");

        if (createdReturn?.id) {
            sessionStorage.removeItem("return_eligible_receipt");
            await router.replace(
                `/member/stock/returns/${encodeRouteId(createdReturn.id)}`,
            );
            return;
        }

        await router.replace("/member/stock/returns?tab=history");
    } catch (e: any) {
        snackbarStore.showMessage(
            e.response?.data?.message || "Gagal mengirim pengajuan retur",
            "error",
        );
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(async () => {
    isLoadingInitial.value = true;
    try {
        const queryReceiptId = route.query.receipt_id
            ? Number(route.query.receipt_id)
            : null;

        let sessionReceipt = null;
        try {
            const stored = sessionStorage.getItem("return_eligible_receipt");
            if (stored) {
                const parsed = JSON.parse(stored);
                if (
                    !queryReceiptId ||
                    Number(parsed.receive_id) === queryReceiptId
                ) {
                    sessionReceipt = parsed;
                }
            }
        } catch (e) {
            // ignore
        }

        if (sessionReceipt) {
            const existingReturn = await returnService.findExistingByReceipt(
                Number(sessionReceipt.receive_id),
                String(sessionReceipt.receive_number || ""),
            );
            if (existingReturn) {
                sessionStorage.removeItem("return_eligible_receipt");
                snackbarStore.showMessage(
                    "Penerimaan ini sudah diajukan retur. Menampilkan detail pengajuan.",
                    "info",
                );
                await router.replace(
                    `/member/stock/returns/${encodeRouteId(existingReturn.id)}`,
                );
                return;
            }
        }

        await fetchAddresses();

        if (sessionReceipt) {
            eligibleReceipts.value = [sessionReceipt];
        } else {
            const receiptsRes = await returnService.getEligibleReceipts({
                limit: 50,
            });
            eligibleReceipts.value = receiptsRes.results;
        }

        // Auto select if query param receipt_id is provided
        if (queryReceiptId) {
            const selected = eligibleReceipts.value.find(
                (receipt) => Number(receipt.receive_id) === queryReceiptId,
            );
            const existingReturn = await returnService.findExistingByReceipt(
                queryReceiptId,
                selected?.receive_number,
            );
            if (existingReturn) {
                sessionStorage.removeItem("return_eligible_receipt");
                snackbarStore.showMessage(
                    "Penerimaan ini sudah diajukan retur. Menampilkan detail pengajuan.",
                    "info",
                );
                await router.replace(
                    `/member/stock/returns/${encodeRouteId(existingReturn.id)}`,
                );
                return;
            }

            selectedReceiptId.value = queryReceiptId;
            onReceiptSelected(queryReceiptId);
        } else if (eligibleReceipts.value.length > 0) {
            selectedReceiptId.value = eligibleReceipts.value[0].receive_id;
            onReceiptSelected(eligibleReceipts.value[0].receive_id);
        }
    } catch (e) {
        snackbarStore.showMessage("Gagal memuat data penerimaan", "error");
    } finally {
        isLoadingInitial.value = false;
    }
});
</script>

<style scoped>
.upload-proof-box {
    border: 1.5px dashed var(--border) !important;
    background: #fafafa;
    transition: all 0.2s ease-in-out;
}

.upload-proof-box:hover {
    border-color: var(--primary) !important;
    background: rgba(var(--v-theme-primary), 0.02);
}

.rate-option-card {
    background: #fff;
    border: 1px solid var(--border);
    transition: all 0.2s ease-in-out;
}

.rate-option-card.active {
    border-color: var(--primary) !important;
    background: rgba(var(--v-theme-primary), 0.03) !important;
}

.rate-radio-circle {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2px solid var(--border);
    position: relative;
    flex-shrink: 0;
    transition: all 0.2s;
}

.rate-radio-circle.active {
    border-color: var(--primary);
    background: var(--primary);
}

.rate-radio-circle.active::after {
    content: "";
    position: absolute;
    top: 4px;
    left: 4px;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: white;
}

.cursor-pointer {
    cursor: pointer;
}

.transition-all {
    transition: all 0.2s ease-in-out;
}
</style>
