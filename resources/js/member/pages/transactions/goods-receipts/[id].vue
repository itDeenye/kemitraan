<template>
    <div class="screen-body">
        <div v-if="isLoading" style="padding: 24px; text-align: center">
            <v-progress-circular
                indeterminate
                color="primary"
                size="24"
            ></v-progress-circular>
            <p style="margin: 12px 0 0; color: var(--muted); font-size: 13px">
                Memuat detail penerimaan...
            </p>
        </div>

        <div v-else-if="receipt" class="pb-6">
            <!-- View when Form is active -->
            <div
                v-if="showReceiveForm"
                class="card"
                style="margin-top: 16px; margin-bottom: 16px"
            >
                <div
                    class="border-b border-[var(--line)]"
                    style="padding: 16px 20px"
                >
                    <div class="section-heading m-0">
                        <h3
                            class="flex items-center gap-2 m-0"
                            style="font-size: 14px"
                        >
                            <v-icon
                                icon="mdi-truck-delivery-outline"
                                size="18"
                            />
                            Data Pengiriman
                        </h3>
                    </div>
                </div>

                <div
                    class="flex flex-col gap-3"
                    style="padding: 16px 20px"
                    :style="
                        receipt.actions?.requires_delivery_note_number
                            ? 'border-bottom: 1px solid var(--line)'
                            : ''
                    "
                >
                    <div
                        class="flex justify-between items-center text-[13px]"
                        v-if="receipt.receive_number"
                    >
                        <span class="text-[var(--muted)]">Kode Penerimaan</span>
                        <strong
                            class="text-[var(--text)] text-right max-w-[60%]"
                            >{{ receipt.receive_number }}</strong
                        >
                    </div>
                    <div class="flex justify-between items-center text-[13px]">
                        <span class="text-[var(--muted)]">Kode Transaksi</span>
                        <strong
                            class="text-[var(--text)] text-right max-w-[60%]"
                            >{{
                                receipt.transaction?.code || receipt.code
                            }}</strong
                        >
                    </div>
                    <div class="flex justify-between items-center text-[13px]">
                        <span class="text-[var(--muted)]">Asal Barang</span>
                        <strong class="text-[var(--text)] text-right max-w-[60%]">{{ receipt.seller?.name || "-" }}</strong>
                    </div>
                    <div class="flex justify-between items-center text-[13px]">
                        <span class="text-[var(--muted)]">Metode Pengiriman</span>
                        <BaseBadge type="shipping_method" :value="receipt.shipping_method" inline />
                    </div>
                    <div class="flex justify-between items-center text-[13px]">
                        <span class="text-[var(--muted)]">Status</span>
                        <BaseBadge
                            type="receive_status"
                            :value="
                                receipt.status?.code || receipt.receive_status
                            "
                            inline
                        />
                    </div>
                </div>

                <div
                    style="padding: 16px 20px"
                    v-if="receipt.actions?.requires_delivery_note_number"
                >
                    <v-alert
                        type="info"
                        variant="tonal"
                        density="compact"
                        class="mb-4 text-[13px]"
                    >
                        Silakan masukkan Nomor Surat Jalan yang sesuai dengan
                        fisik pengiriman untuk melakukan konfirmasi penerimaan.
                    </v-alert>
                    <v-text-field
                        v-model="form.delivery_note_number"
                        label="Nomor Surat Jalan"
                        variant="outlined"
                        density="compact"
                        placeholder="SJ-2026-0001"
                        hide-details="auto"
                        class="mb-3"
                        :rules="[(v) => !!v || 'Nomor surat jalan wajib diisi']"
                    ></v-text-field>
                </div>
            </div>

            <div
                v-else
                class="card"
                style="margin-top: 16px; margin-bottom: 16px"
            >
                <div
                    class="border-b border-[var(--line)]"
                    style="padding: 16px 20px"
                >
                    <div class="section-heading m-0">
                        <h3
                            class="flex items-center gap-2 m-0"
                            style="font-size: 14px"
                        >
                            <v-icon
                                icon="mdi-truck-delivery-outline"
                                size="18"
                            />
                            Data Pengiriman
                        </h3>
                    </div>
                </div>

                <div class="flex flex-col gap-3" style="padding: 16px 20px">
                    <div
                        class="flex justify-between items-center text-[13px]"
                        v-if="receipt.receive_number"
                    >
                        <span class="text-[var(--muted)]">Kode Penerimaan</span>
                        <strong
                            class="text-[var(--text)] text-right max-w-[60%]"
                            >{{ receipt.receive_number }}</strong
                        >
                    </div>
                    <div class="flex justify-between items-center text-[13px]">
                        <span class="text-[var(--muted)]">Kode Transaksi</span>
                        <strong
                            class="text-[var(--text)] text-right max-w-[60%]"
                            >{{
                                receipt.transaction?.code || receipt.code
                            }}</strong
                        >
                    </div>
                    <div class="flex justify-between items-center text-[13px]">
                        <span class="text-[var(--muted)]">Asal Barang</span>
                        <strong class="text-[var(--text)] text-right max-w-[60%]">{{ receipt.seller?.name || "-" }}</strong>
                    </div>
                    <div class="flex justify-between items-center text-[13px]">
                        <span class="text-[var(--muted)]">Metode Pengiriman</span>
                        <BaseBadge type="shipping_method" :value="receipt.shipping_method" inline />
                    </div>
                    <div class="flex justify-between items-center text-[13px]">
                        <span class="text-[var(--muted)]"
                            >Status Pengiriman</span
                        >
                        <BaseBadge
                            type="receive_status"
                            :value="
                                receipt.status?.code || receipt.receive_status
                            "
                            inline
                        />
                    </div>

                    <template
                        v-if="
                            receipt.status?.code === 'completed' ||
                            receipt.status?.code === 'received' ||
                            receipt.receive_status === 'completed'
                        "
                    >
                        <div
                            class="flex justify-between items-center text-[13px]"
                            v-if="receipt.delivery_note_number"
                        >
                            <span class="text-[var(--muted)]"
                                >Nomor Surat Jalan</span
                            >
                            <strong
                                class="text-[var(--text)] text-right max-w-[60%]"
                                >{{ receipt.delivery_note_number }}</strong
                            >
                        </div>
                        <div
                            class="flex justify-between items-center text-[13px]"
                            v-if="receipt.invoice_number"
                        >
                            <span class="text-[var(--muted)]">No. Invoice</span>
                            <strong
                                class="text-[var(--text)] text-right max-w-[60%]"
                                >{{ receipt.invoice_number }}</strong
                            >
                        </div>
                        <div
                            class="flex justify-between items-center text-[13px]"
                        >
                            <span class="text-[var(--muted)]"
                                >Tanggal Diterima</span
                            >
                            <strong
                                class="text-[var(--text)] text-right max-w-[60%]"
                                >{{
                                    formatDateTime(receipt.received_at)
                                }}</strong
                            >
                        </div>
                    </template>
                    <template v-else>
                        <div
                            class="flex justify-between items-center text-[13px]"
                        >
                            <span class="text-[var(--muted)]">Keterangan</span>
                            <strong
                                class="text-[var(--muted)] text-right max-w-[60%]"
                                >Pesanan belum diterima.</strong
                            >
                        </div>
                    </template>
                </div>
            </div>

            <div class="card" style="margin-bottom: 16px">
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
                            {{
                                receipt.status?.code === "completed" ||
                                receipt.status?.code === "received"
                                    ? "Detail Produk Diterima"
                                    : "Detail Produk Dipesan"
                            }}
                        </h3>
                        <span
                            class="text-xs font-semibold text-[var(--primary)]"
                        >
                            {{
                                receipt.received_items?.length ||
                                receipt.shipment_items?.length ||
                                receipt.ordered_items?.length ||
                                receipt.details?.length ||
                                0
                            }}
                            produk
                        </span>
                    </div>
                </div>

                <div style="padding: 12px 20px 16px 20px">
                    <div
                        v-for="(item, index) in groupedItems"
                        :key="item.product_id || index"
                        class="border-b border-[var(--line)] last:border-b-0 py-4"
                        :class="
                            index !== groupedItems.length - 1
                                ? 'border-dashed'
                                : ''
                        "
                    >
                        <div class="flex gap-3 items-center">
                            <div
                                class="w-[50px] h-[50px] rounded-lg bg-[#f5f5f5] flex items-center justify-center shrink-0 overflow-hidden"
                                style="border: 1px solid #eaeaea"
                            >
                                <v-img
                                    v-if="item.image || item.product?.image"
                                    :src="item.image || item.product?.image"
                                    cover
                                ></v-img>
                                <v-icon
                                    v-else
                                    icon="mdi-image-outline"
                                    color="#ccc"
                                    size="24"
                                ></v-icon>
                            </div>
                            <div class="flex-1 flex flex-col justify-center">
                                <strong
                                    class="text-[13px] text-[var(--text)] leading-tight mb-1"
                                    >{{
                                        item.name || item.product?.name
                                    }}</strong
                                >
                                <span
                                    class="text-[11px] text-[var(--muted)] mt-0.5"
                                >
                                    BPOM: {{ item.bpom_number || "-" }}
                                </span>
                                <span
                                    class="text-[12px] text-[var(--muted)] mt-0.5"
                                    >Dipesan:
                                    {{ item.total_quantity }} pcs</span
                                >
                            </div>
                        </div>

                        <div
                            class="flex flex-col gap-2 mt-3"
                            v-if="item.batches && item.batches.length > 0"
                        >
                            <div
                                v-for="(batch, bIdx) in item.batches"
                                :key="bIdx"
                                class="flex gap-3 px-2 py-2 bg-[#fafafa] rounded-md border border-[var(--line)]"
                            >
                                <div class="flex-1 text-[13px]">
                                    <div
                                        class="text-[11px] text-[var(--muted)] mb-0.5"
                                    >
                                        Nomor Batch
                                    </div>
                                    <strong class="text-[var(--text)]">{{
                                        batch.batch_number || batch.batch || "-"
                                    }}</strong>
                                </div>
                                <div class="flex-1 text-[13px]">
                                    <div
                                        class="text-[11px] text-[var(--muted)] mb-0.5"
                                    >
                                        Expired Date
                                    </div>
                                    <strong class="text-[var(--text)]">{{
                                        formatDate(
                                            batch.expiry_date ||
                                                batch.expired_date ||
                                                "-",
                                        )
                                    }}</strong>
                                </div>
                                <div
                                    class="text-[13px] text-right flex flex-col justify-center"
                                >
                                    <div
                                        class="text-[11px] text-[var(--muted)] mb-0.5"
                                        v-if="item.batches.length > 1"
                                    >
                                        Qty
                                    </div>
                                    <strong
                                        class="text-[var(--text)]"
                                        v-if="item.batches.length > 1"
                                        >{{
                                            batch.quantity || batch.qty || 1
                                        }}</strong
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="mt-4 mb-4"
                v-if="receipt.actions?.can_receive"
                style="display: flex; flex-direction: column; gap: 12px"
            >
                <template
                    v-if="!receipt.actions?.requires_delivery_note_number"
                >
                    <v-btn
                        color="primary"
                        block
                        size="large"
                        rounded="lg"
                        elevation="0"
                        @click="confirmReceipt"
                        :loading="isSubmitting"
                        class="text-none font-weight-bold"
                    >
                        Konfirmasi Penerimaan Barang
                    </v-btn>
                </template>
                <template v-else>
                    <v-btn
                        v-if="!showReceiveForm"
                        color="primary"
                        block
                        size="large"
                        rounded="lg"
                        elevation="0"
                        @click="showReceiveForm = true"
                        class="text-none font-weight-bold"
                    >
                        Buat Penerimaan
                    </v-btn>
                    <template v-else>
                        <v-btn
                            color="primary"
                            block
                            size="large"
                            rounded="lg"
                            elevation="0"
                            @click="confirmReceipt"
                            :loading="isSubmitting"
                            class="text-none font-weight-bold"
                        >
                            Konfirmasi Penerimaan Barang
                        </v-btn>
                        <v-btn
                            variant="outlined"
                            color="var(--ink)"
                            block
                            size="large"
                            rounded="lg"
                            @click="showReceiveForm = false"
                            class="text-none font-weight-bold bg-white"
                            style="border-color: #d1d5db"
                            :disabled="isSubmitting"
                        >
                            Batal
                        </v-btn>
                    </template>
                </template>
            </div>
        </div>

        <div v-else class="card mt-3" style="padding: 24px; text-align: center">
            <p>Data penerimaan tidak ditemukan.</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import purchaseService from "@/member/services/purchase.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { decodeRouteId } from "@/shared/utils/route-id";

const route = useRoute();
const router = useRouter();
const snackbar = useSnackbarStore();
const { formatDate, formatDateTime } = useFormatter();

const receiptId = decodeRouteId((route.params as Record<string, unknown>).id);
const receipt = ref<any>(null);
const isLoading = ref(true);
const isSubmitting = ref(false);
const showReceiveForm = ref(false);

const form = ref({
    delivery_note_number: "",
});

const groupedItems = computed(() => {
    if (!receipt.value) return [];

    const items =
        receipt.value.received_items ||
        (receipt.value.shipment_items?.length
            ? receipt.value.shipment_items
            : null) ||
        receipt.value.ordered_items ||
        receipt.value.details ||
        [];
    const groups = new Map();

    items.forEach((item: any) => {
        const productId = item.product_id || item.product?.id;

        if (!groups.has(productId)) {
            groups.set(productId, {
                ...item,
                product_id: productId,
                total_quantity: 0,
                batches: [],
            });
        }

        const group = groups.get(productId);
        group.total_quantity += item.quantity || item.qty || 0;

        if (
            item.batch_number ||
            item.batch ||
            item.expiry_date ||
            item.expired_date
        ) {
            group.batches.push(item);
        }
    });

    return Array.from(groups.values());
});

const fetchReceiptDetail = async () => {
    isLoading.value = true;
    try {
        const response = await purchaseService.getGoodsReceiptDetail(receiptId);
        if (response.success && response.data) {
            receipt.value = response.data;
        } else {
            snackbar.showMessage("Gagal memuat detail penerimaan.", "error");
            router.push("/member/transactions/goods-receipts");
        }
    } catch (error) {
        console.error("Failed to fetch goods receipt detail:", error);
        snackbar.showMessage("Terjadi kesalahan sistem.", "error");
        router.push("/member/transactions/goods-receipts");
    } finally {
        isLoading.value = false;
    }
};

const confirmReceipt = async () => {
    if (
        receipt.value?.actions?.requires_delivery_note_number &&
        !form.value.delivery_note_number
    ) {
        snackbar.showMessage("Mohon isi Nomor Surat Jalan.", "error");
        return;
    }

    isSubmitting.value = true;
    try {
        const response = await purchaseService.confirmGoodsReceipt(
            receiptId,
            form.value,
        );
        if (response.success) {
            snackbar.showMessage(
                "Penerimaan barang berhasil dikonfirmasi.",
                "success",
            );
            showReceiveForm.value = false;
            fetchReceiptDetail();
        } else {
            snackbar.showMessage(
                response.message || "Gagal mengonfirmasi penerimaan.",
                "error",
            );
        }
    } catch (error: any) {
        console.error("Failed to confirm receipt:", error);

        let errorMsg = "Terjadi kesalahan saat mengonfirmasi penerimaan.";
        if (error.response?.data?.message) {
            errorMsg = error.response.data.message;
        }

        snackbar.showMessage(errorMsg, "error");
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    fetchReceiptDetail();
});
</script>
