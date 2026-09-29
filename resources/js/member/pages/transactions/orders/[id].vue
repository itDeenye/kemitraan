<template>
    <div class="screen-body">
        <div v-if="isLoading" class="p-6 text-center">
            <v-progress-circular
                indeterminate
                color="primary"
                size="24"
            ></v-progress-circular>
            <p class="m-0 mt-3 text-[var(--muted)] text-[13px]">
                Memuat detail pesanan...
            </p>
        </div>

        <div v-else-if="order" class="pb-6">
            <div
                class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3"
            >
                <div class="flex-1 min-w-0">
                    <h2
                        class="text-base font-bold m-0 mt-1 text-[var(--ink)] break-words"
                    >
                        {{ order.code }}
                    </h2>
                    <div
                        class="text-[12px] text-[var(--muted)] mt-1 flex items-center gap-1.5"
                    >
                        <v-icon icon="mdi-calendar-clock" size="14" />
                        <span v-if="order.ordered_at">{{
                            formatDateTime(order.ordered_at)
                        }}</span>
                    </div>
                </div>
                <div
                    class="shrink-0 flex flex-col items-start sm:items-end gap-2"
                >
                    <BaseBadge
                        type="transaction_status"
                        :value="order.status?.code || order.status"
                        inline
                        chip-class="font-semibold"
                    />
                </div>
            </div>

            <PreorderOriginCard
                v-if="order.is_preorder"
                :preorder="order.preorder"
            />

            <div
                v-if="order.actions?.waiting_for_stock_screening"
                class="card mt-4 mb-4"
                style="
                    padding: 16px;
                    background: #fff8e1;
                    border-color: #ffe082;
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;
                "
            >
                <v-icon
                    icon="mdi-clipboard-text-search-outline"
                    size="28"
                    color="orange-darken-3"
                    class="mt-1"
                />
                <div>
                    <h3
                        style="
                            font-size: 14px;
                            margin: 0 0 4px 0;
                            color: #f57f17;
                        "
                    >
                        Menunggu Screening Stok
                    </h3>
                    <p
                        style="
                            font-size: 13px;
                            color: #795548;
                            margin: 0;
                            line-height: 1.4;
                        "
                    >
                        Pesanan ini sedang dalam proses screening stok oleh
                        pusat. Silakan cek kembali secara berkala, Anda dapat
                        melanjutkan pembayaran setelah pesanan disetujui.
                    </p>
                </div>
            </div>

            <div
                v-if="!isIntermediatePickupPreorder"
                class="card"
                style="margin-top: 16px; margin-bottom: 16px"
            >
                <div
                    class="border-b border-[var(--line)]"
                    style="padding: 16px 20px"
                >
                    <h3
                        class="flex items-center gap-2 m-0"
                        style="font-size: 14px"
                    >
                        <v-icon icon="mdi-map-marker-outline" size="18" />
                        {{
                            order.shipping?.method === "pickup"
                                ? "Informasi Pengambilan"
                                : "Informasi Pengiriman"
                        }}
                    </h3>
                </div>
                <div style="padding: 16px 20px">
                    <div class="flex flex-col gap-3">
                        <div v-if="order.shipping?.method === 'pickup'">
                            <span
                                class="text-[11px] font-semibold text-[var(--muted)] uppercase tracking-wider block mb-1"
                                >LOKASI PENGAMBILAN</span
                            >
                            <strong class="text-[13px] block">{{
                                order.shipping?.location ||
                                order.seller?.origin?.name ||
                                order.seller?.name ||
                                "-"
                            }}</strong>
                            <p
                                class="text-[12px] text-[var(--muted)] m-0 leading-relaxed"
                                v-html="
                                    formatLocation(order.seller?.origin)
                                "
                            ></p>
                            <div
                                v-if="
                                    order.shipping?.code &&
                                    (!order.is_preorder ||
                                        !order.parent_transaction_id)
                                "
                                class="mt-3 rounded-lg"
                                style="
                                    padding: 12px 14px;
                                    border: 1px solid
                                        rgba(var(--v-theme-primary), 0.18);
                                    background: rgba(
                                        var(--v-theme-primary),
                                        0.06
                                    );
                                "
                            >
                                <span
                                    class="text-[11px] font-semibold text-[var(--muted)] uppercase tracking-wider block mb-1"
                                >
                                    Kode Verifikasi Pengambilan
                                </span>
                                <strong
                                    class="text-[22px] tracking-[0.18em] text-[var(--primary)]"
                                >
                                    {{ order.shipping.code }}
                                </strong>
                                <p
                                    class="text-[12px] text-[var(--muted)] m-0 mt-1"
                                >
                                    Tunjukkan kode ini ke penjual saat barang
                                    diambil.
                                </p>
                            </div>
                        </div>
                        <template v-else>
                            <div>
                                <span
                                    class="text-[11px] font-semibold text-[var(--muted)] uppercase tracking-wider block mb-1"
                                    >DARI</span
                                >
                                <strong class="text-[13px] block">{{
                                    order.seller?.origin?.name ||
                                    order.seller?.name ||
                                    "-"
                                }}</strong>
                                <p
                                    class="text-[12px] text-[var(--muted)] m-0 leading-relaxed"
                                    v-html="
                                        formatLocation(order.seller?.origin)
                                    "
                                ></p>
                            </div>
                            <div>
                                <span
                                    class="text-[11px] font-semibold text-[var(--muted)] uppercase tracking-wider block mb-1"
                                    >TUJUAN</span
                                >
                                <strong class="text-[13px] block"
                                    >{{
                                        order.buyer?.destination?.name ||
                                        order.buyer?.name ||
                                        "-"
                                    }}
                                    {{
                                        order.buyer?.destination?.phone
                                            ? "(" +
                                              order.buyer.destination.phone +
                                              ")"
                                            : ""
                                    }}</strong
                                >
                                <p
                                    class="text-[12px] text-[var(--muted)] m-0 leading-relaxed"
                                    v-html="
                                        formatLocation(order.buyer?.destination)
                                    "
                                ></p>
                            </div>
                        </template>
                    </div>
                </div>
                <div
                    class="border-t border-dashed border-[var(--line)] bg-[#fafafa] rounded-b-xl"
                    style="padding: 12px 20px"
                >
                    <div
                        class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-3"
                    >
                        <span class="soft-label flex items-center gap-[6px]">
                            <v-icon icon="mdi-truck-outline" size="16" />
                            Pengiriman
                        </span>
                        <strong
                            class="text-[13px] sm:text-right"
                            v-if="order.shipping?.method === 'pickup'"
                        >
                            Pickup (Diambil Sendiri)
                        </strong>
                        <strong class="text-[13px] sm:text-right" v-else>
                            <span class="uppercase"
                                >{{ order.shipping?.courier || "Kurir" }} -
                            </span>
                            {{ order.shipping?.service || "" }}
                        </strong>
                    </div>
                    <div
                        class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-3 mt-3 sm:mt-2"
                        v-if="order.shipping?.tracking_number"
                    >
                        <span class="soft-label">Nomor Resi</span>
                        <strong
                            class="text-[var(--primary)] text-[13px] break-all"
                            >{{ order.shipping.tracking_number }}</strong
                        >
                    </div>
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
                            Daftar Produk
                        </h3>
                        <span
                            class="text-xs font-semibold text-[var(--primary)]"
                        >
                            {{ order.items?.length || 0 }} produk
                        </span>
                    </div>
                </div>

                <div style="padding: 12px 20px 16px 20px">
                    <div
                        class="flex gap-3 border-b border-[var(--line)] last:border-b-0"
                        style="padding-top: 12px; padding-bottom: 12px"
                        v-for="(item, index) in order.items"
                        :key="item.id"
                        :class="
                            index !== order.items.length - 1
                                ? 'border-dashed'
                                : ''
                        "
                    >
                        <div
                            class="w-[50px] h-[50px] rounded-lg bg-[#f5f5f5] flex items-center justify-center shrink-0 overflow-hidden"
                            style="border: 1px solid #eaeaea"
                        >
                            <v-img
                                v-if="item.product?.image"
                                :src="item.product.image"
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
                                v-if="item.product?.code"
                                class="text-[13px] text-[var(--text)] block"
                            >
                                {{ item.product.code }}
                            </strong>
                            <div
                                class="text-[13px] text-[var(--text)] line-clamp-2"
                            >
                                {{ item.product?.name }}
                                <span
                                    v-if="item.preorder_quantity > 0"
                                    class="text-orange-500 font-bold ml-1"
                                    >(PO {{ item.preorder_quantity }})</span
                                >
                            </div>
                            <div class="text-[11px] text-[var(--muted)] mt-0.5">
                                BPOM: {{ item.product.bpom_number || "-" }}
                            </div>
                            <span class="text-[12px]"
                                >{{ item.quantity }} x Rp{{
                                    formatPrice(item.net_price || item.price)
                                }}</span
                            >
                        </div>
                        <div class="flex items-center text-[13px]">
                            <strong>Rp{{ formatPrice(item.subtotal) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 16px">
                <div style="padding: 16px 20px">
                    <div class="flex justify-between items-center mb-4">
                        <h3
                            class="flex items-center gap-2 m-0"
                            style="font-size: 14px"
                        >
                            <v-icon icon="mdi-receipt-text-outline" size="18" />
                            Rincian Pembayaran
                        </h3>
                        <span
                            v-if="order.payment_method"
                            class="text-[11px] font-semibold text-[var(--primary)] px-2 py-1 rounded capitalize"
                            style="
                                background: rgba(var(--v-theme-primary), 0.1);
                            "
                        >
                            {{
                                order.payment_method === "transfer"
                                    ? "Transfer Bank"
                                    : order.payment_method.replace("_", " ")
                            }}
                        </span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="soft-label">Total Harga Produk</span>
                        <span class="text-[13px]"
                            >Rp{{
                                formatPrice(order.summary?.product_total || 0)
                            }}</span
                        >
                    </div>
                    <div
                        class="flex justify-between mb-2"
                        v-if="
                            order.shipping &&
                            order.shipping?.method !== 'pickup'
                        "
                    >
                        <span class="soft-label">Ongkos Kirim</span>
                        <span class="text-[13px]"
                            >Rp{{
                                formatPrice(order.summary?.shipping_cost || 0)
                            }}</span
                        >
                    </div>
                    <div
                        class="flex justify-between mb-2"
                        v-if="
                            order.shipping &&
                            order.shipping?.method !== 'pickup'
                        "
                    >
                        <span class="soft-label">Asuransi Pengiriman</span>
                        <span class="text-[13px]"
                            >Rp{{
                                formatPrice(
                                    order.summary?.shipping_cost_insurance || 0,
                                )
                            }}</span
                        >
                    </div>
                    <div
                        class="flex justify-between mb-2"
                        v-if="order.summary?.total_discount > 0"
                    >
                        <span class="soft-label">Diskon</span>
                        <span style="color: var(--danger); font-size: 13px"
                            >- Rp{{
                                formatPrice(order.summary.total_discount)
                            }}</span
                        >
                    </div>
                    <div
                        class="flex justify-between mb-2"
                        v-if="order.summary?.voucher_discount > 0"
                    >
                        <span class="soft-label">Potongan Voucher</span>
                        <span style="color: var(--danger); font-size: 13px"
                            >- Rp{{
                                formatPrice(order.summary.voucher_discount)
                            }}</span
                        >
                    </div>
                    <div
                        class="flex justify-between mb-2"
                        v-if="order.summary?.payment_charge > 0"
                    >
                        <span class="soft-label">Biaya Layanan</span>
                        <span class="text-[13px]"
                            >Rp{{
                                formatPrice(order.summary.payment_charge)
                            }}</span
                        >
                    </div>

                    <div
                        v-if="order.payment && order.payment.bank"
                        class="mt-4 pt-4 border-t border-dashed border-[var(--line)]"
                    >
                        <div
                            class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1 sm:gap-3 w-full"
                        >
                            <span class="soft-label mt-[2px] shrink-0"
                                >Transfer ke Bank</span
                            >
                            <div class="text-right flex-1 min-w-0">
                                <strong class="text-[13px] block break-words">{{
                                    order.payment.bank.bank_name ||
                                    order.payment.bank.name ||
                                    "Bank"
                                }}</strong>
                                <div
                                    class="flex items-center gap-1 justify-end mt-1 text-[13px]"
                                    style="
                                        font-weight: 700;
                                        color: var(--primary);
                                    "
                                >
                                    {{ order.payment.bank.account_number }}
                                    <v-icon
                                        icon="mdi-content-copy"
                                        size="16"
                                        class="cursor-pointer active:opacity-50"
                                        @click.stop="
                                            copyToClipboard(
                                                order.payment.bank
                                                    .account_number,
                                                'Nomor rekening',
                                            )
                                        "
                                    />
                                </div>
                                <span
                                    class="text-[12px] text-[var(--muted)] font-normal block"
                                    >a.n.
                                    {{ order.payment.bank.account_name }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="order.payment?.spread_payment?.bank"
                        class="mt-3 pt-3 border-t border-dashed border-[var(--line)]"
                    >
                        <div
                            class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1 sm:gap-3 w-full"
                        >
                            <span class="soft-label mt-[2px] shrink-0"
                                >Bank Kemitraan</span
                            >
                            <div class="text-right flex-1 min-w-0">
                                <strong class="text-[13px] block break-words">{{
                                    order.payment.spread_payment.bank.name ||
                                    "Bank"
                                }}</strong>
                                <div
                                    class="flex items-center gap-1 justify-end mt-1 text-[13px]"
                                    style="
                                        font-weight: 700;
                                        color: var(--primary);
                                    "
                                >
                                    {{
                                        order.payment.spread_payment.bank
                                            .account_number
                                    }}
                                    <v-icon
                                        icon="mdi-content-copy"
                                        size="16"
                                        class="cursor-pointer active:opacity-50"
                                        @click.stop="
                                            copyToClipboard(
                                                order.payment.spread_payment
                                                    .bank.account_number,
                                                'Nomor rekening',
                                            )
                                        "
                                    />
                                </div>
                                <span
                                    class="text-[12px] text-[var(--muted)] font-normal block"
                                    >a.n.
                                    {{
                                        order.payment.spread_payment.bank
                                            .account_name
                                    }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="order.payment?.receipt_url"
                        class="payment-receipt-card mt-4"
                    >
                        <div class="payment-receipt-header">
                            <span>Bukti Pembayaran</span>
                            <BaseBadge
                                type="payment_status"
                                :value="order.payment?.status?.code"
                                inline
                            />
                        </div>
                        <div
                            class="d-flex flex-column align-center gap-3 mt-2 text-center"
                        >
                            <div
                                style="
                                    width: 200px;
                                    max-width: 100%;
                                    flex-shrink: 0;
                                "
                            >
                                <button
                                    type="button"
                                    class="payment-receipt-thumbnail m-0 w-100"
                                    @click="
                                        openImage(order.payment.receipt_url)
                                    "
                                >
                                    <v-img
                                        :src="order.payment.receipt_url"
                                        width="100%"
                                        height="100%"
                                        class="payment-receipt-image"
                                        contain
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
                                </button>
                                <span
                                    class="text-[11px] text-[var(--muted)] mt-2 block leading-tight"
                                    >Ketuk gambar untuk memperbesar</span
                                >
                            </div>
                            <div class="text-[12px] text-[var(--muted)] px-2">
                                Bukti pembayaran yang Anda unggah.
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="order.payment?.spread_payment?.receipt_url"
                        class="payment-receipt-card mt-4"
                    >
                        <div class="payment-receipt-header">
                            <span>Bukti Pembayaran Kemitraan</span>
                            <BaseBadge
                                type="payment_status"
                                :value="order.payment.spread_payment.status"
                                inline
                            />
                        </div>
                        <div
                            class="d-flex flex-column align-center gap-3 mt-2 text-center"
                        >
                            <div
                                style="
                                    width: 200px;
                                    max-width: 100%;
                                    flex-shrink: 0;
                                "
                            >
                                <button
                                    type="button"
                                    class="payment-receipt-thumbnail m-0 w-100"
                                    @click="
                                        openImage(
                                            order.payment.spread_payment
                                                .receipt_url,
                                        )
                                    "
                                >
                                    <v-img
                                        :src="
                                            order.payment.spread_payment
                                                .receipt_url
                                        "
                                        width="100%"
                                        height="100%"
                                        class="payment-receipt-image"
                                        contain
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
                                </button>
                                <span
                                    class="text-[11px] text-[var(--muted)] mt-2 block leading-tight"
                                    >Ketuk gambar untuk memperbesar</span
                                >
                            </div>
                            <div class="text-[12px] text-[var(--muted)] px-2">
                                Bukti pembayaran kemitraan yang Anda unggah.
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="border-t border-dashed border-[var(--line)] bg-[#fafafa] rounded-b-xl"
                    style="padding: 16px 20px"
                >
                    <div class="flex justify-between items-center">
                        <strong class="text-sm">Total Pembayaran</strong>
                        <strong class="text-base text-[var(--primary)]"
                            >Rp{{
                                formatPrice(order.summary?.grand_total || 0)
                            }}</strong
                        >
                    </div>
                </div>
            </div>

            <div
                class="mt-4"
                style="display: flex; flex-direction: column; gap: 12px"
                v-if="
                    order.actions?.can_upload_payment ||
                    order.actions?.can_cancel
                "
            >
                <button
                    v-if="order.actions?.can_upload_payment"
                    class="transition-all text-white shadow-[0_1px_2px_rgba(220,38,38,0.2)] active:opacity-90 block w-full text-center"
                    style="
                        background-color: rgb(var(--v-theme-primary));
                        border: 1px solid rgb(var(--v-theme-primary));
                        padding: 12px 16px;
                        font-size: 13px;
                        font-weight: 600;
                        border-radius: 8px;
                    "
                    @click="
                        router.push(
                            `/member/transactions/orders/payment?id=${encodeRouteId(order.id)}`,
                        )
                    "
                >
                    Upload Bukti Pembayaran
                </button>
                <button
                    v-if="order.actions?.can_cancel"
                    class="font-semibold transition-all bg-white text-[var(--ink)] shadow-[0_1px_2px_rgba(0,0,0,0.05)] active:bg-[#f5f5f5] block w-full text-center"
                    style="
                        padding: 12px 16px;
                        font-size: 13px;
                        border-radius: 8px;
                        border: 1px solid #e0e0e0;
                    "
                    @click="cancelOrder(order)"
                >
                    Batalkan Pesanan
                </button>
            </div>

            <div
                v-if="order.tracking && order.tracking.is_available"
                class="card mt-3"
            >
                <div class="row-main">
                    <div
                        class="text-[13px] font-bold text-[var(--ink)] border-b border-[var(--line)]"
                        style="padding: 12px 16px"
                    >
                        Lacak Pesanan
                    </div>
                    <div class="bg-white rounded-b-xl" style="padding: 16px">
                        <div
                            class="mb-5 bg-[#fafafa] rounded-lg p-3 d-flex justify-space-between align-center"
                        >
                            <div>
                                <div
                                    class="text-[11px] text-[var(--placeholder)] mb-1"
                                >
                                    Nomor Resi
                                </div>
                                <div
                                    class="text-[13px] font-bold text-[var(--primary)]"
                                >
                                    {{
                                        order.tracking.tracking_number ||
                                        order.shipping?.tracking_number ||
                                        "-"
                                    }}
                                </div>
                            </div>
                            <div
                                class="text-[var(--primary)] cursor-pointer p-2 rounded-md shadow-sm"
                                @click="
                                    copyToClipboard(
                                        order.tracking.tracking_number ||
                                            order.shipping?.tracking_number,
                                        'Nomor resi',
                                    )
                                "
                            >
                                <v-icon size="16">mdi-content-copy</v-icon>
                            </div>
                        </div>

                        <v-timeline
                            density="compact"
                            side="end"
                            truncate-line="both"
                            align="start"
                            class="tracking-timeline"
                        >
                            <v-timeline-item
                                v-for="(history, index) in order.tracking
                                    .histories"
                                :key="index"
                                :dot-color="index === 0 ? 'success' : '#e0e0e0'"
                                :size="index === 0 ? '12' : '10'"
                                fill-dot
                                width="100%"
                            >
                                <div
                                    :class="{
                                        'text-[var(--ink)]': index === 0,
                                        'text-[var(--placeholder)]': index > 0,
                                    }"
                                >
                                    <div
                                        class="text-[12px] font-semibold leading-tight"
                                    >
                                        {{ history.status }}
                                    </div>
                                    <div class="text-[11px] mt-1 opacity-80">
                                        {{ formatDateTime(history.created_at) }}
                                    </div>
                                </div>
                            </v-timeline-item>
                        </v-timeline>
                        <div
                            v-if="
                                !order.tracking.histories ||
                                order.tracking.histories.length === 0
                            "
                            class="text-center text-[12px] text-[var(--placeholder)] py-2"
                        >
                            Data pelacakan belum tersedia saat ini.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="card mt-3 p-6 text-center">
            <p>Pesanan tidak ditemukan.</p>
        </div>

        <MobileConfirm ref="confirmDialog" />

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
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import purchaseService from "@/member/services/purchase.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import MobileConfirm from "@/member/components/MobileConfirm.vue";
import PreorderOriginCard from "@/member/components/transactions/PreorderOriginCard.vue";
import { decodeRouteId, encodeRouteId } from "@/shared/utils/route-id";

const route = useRoute();
const router = useRouter();
const { formatPrice, formatDateTime, formatLocation } = useFormatter();

const confirmDialog = ref<InstanceType<typeof MobileConfirm> | null>(null);

const orderId = decodeRouteId((route.params as Record<string, unknown>).id);

const copyToClipboard = (text: string, subject: string = "Teks") => {
    if (!text) return;
    navigator.clipboard
        .writeText(text)
        .then(() => {
            snackbar.showMessage(`${subject} berhasil disalin`, "success");
        })
        .catch(() => {
            snackbar.showMessage("Gagal menyalin", "error");
        });
};

const order = ref<any>(null);
const isIntermediatePickupPreorder = computed(
    () =>
        order.value?.is_preorder === true &&
        Number(order.value?.parent_transaction_id) > 0 &&
        order.value?.shipping?.method === "pickup",
);
const isLoading = ref(true);
const imageDialog = ref(false);
const selectedImage = ref("");

const fetchOrderDetail = async () => {
    isLoading.value = true;
    try {
        const response = await purchaseService.getOrderDetail(orderId);
        if (response.success && response.data) {
            order.value = response.data;
        }
    } catch (error) {
        console.error("Failed to fetch order detail:", error);
    } finally {
        isLoading.value = false;
    }
};

import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useBadge } from "@/shared/composables/useBadge";
import BaseBadge from "@/shared/components/BaseBadge.vue";

const snackbar = useSnackbarStore();
const { getBadgeData } = useBadge();

const cancelOrder = async (order: any) => {
    if (!confirmDialog.value) return;
    const isConfirmed = await confirmDialog.value.open({
        title: "Batalkan Pesanan",
        message: `Apakah Anda yakin ingin membatalkan pesanan dengan kode <strong class="font-bold text-[#091426]">${order.code}</strong>?`,
        confirmText: "Ya, Batalkan",
        confirmColor: "error",
        icon: "mdi-alert-circle-outline",
        iconColor: "error",
    });
    if (!isConfirmed) return;

    try {
        await purchaseService.cancelOrder(order.id);
        snackbar.showMessage("Pesanan berhasil dibatalkan.", "success");
        fetchOrderDetail();
    } catch (error) {
        console.error("Failed to cancel order:", error);
        snackbar.showMessage(
            "Terjadi kesalahan saat membatalkan pesanan.",
            "error",
        );
    }
};

const openImage = (url: string) => {
    selectedImage.value = url;
    imageDialog.value = true;
};

onMounted(() => {
    fetchOrderDetail();
});
</script>
