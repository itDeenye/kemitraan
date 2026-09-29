<template>
    <div class="screen-body">
        <div v-if="step === 1" class="catalog-container">
            <div class="search-box">
                <v-icon icon="mdi-magnify" size="small" />
                <input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Cari nama atau kode produk..."
                    style="
                        background: transparent;
                        border: none;
                        outline: none;
                        width: 100%;
                        margin-left: 8px;
                    "
                />
            </div>

            <div class="filter-chips">
                <span
                    class="filter-chip"
                    :class="{ active: currentCategory === null }"
                    @click="currentCategory = null"
                    >Semua</span
                >
                <span
                    v-for="cat in categories"
                    :key="cat.id"
                    class="filter-chip"
                    :class="{ active: currentCategory === cat.id }"
                    @click="currentCategory = cat.id"
                >
                    {{ cat.name }}
                </span>
            </div>

            <div
                v-if="isLoadingCatalog"
                class="card mt-4"
                style="padding: 24px; text-align: center"
            >
                <v-progress-circular
                    indeterminate
                    color="primary"
                    size="24"
                ></v-progress-circular>
                <p
                    style="
                        margin: 12px 0 0;
                        color: var(--muted);
                        font-size: 13px;
                    "
                >
                    Memuat katalog produk...
                </p>
            </div>

            <div
                class="catalog-grid"
                v-if="!isLoadingCatalog && products.length > 0"
            >
                <div
                    class="card catalog-card"
                    v-for="product in products"
                    :key="product.id"
                    style="
                        height: 100%;
                        margin-top: 0;
                        display: flex;
                        flex-direction: column;
                    "
                >
                    <div
                        class="product-art"
                        style="border: 1px solid #eaeaea; background: #fff"
                    >
                        <v-img
                            v-if="
                                product.image &&
                                !product.image.includes('default')
                            "
                            :src="product.image"
                            cover
                        ></v-img>
                        <v-icon
                            v-else
                            icon="mdi-image-outline"
                            color="#ccc"
                            size="32"
                        ></v-icon>
                    </div>
                    <h4>{{ product.name }}</h4>
                    <p>{{ product.code }}</p>
                    <p
                        v-if="product.bpom_number"
                        style="
                            margin: 4px 0 0;
                            font-size: 11px;
                            color: var(--muted);
                        "
                    >
                        BPOM: {{ product.bpom_number }}
                    </p>
                    <div class="catalog-stock">
                        <span
                            >Stok {{ product.available_stock || 0 }}
                            {{ product.unit || "pcs" }}</span
                        >
                        <strong
                            v-if="product.available_stock > 0"
                            style="color: #16a34a"
                            >Tersedia</strong
                        >
                        <strong v-else style="color: var(--danger, #ef4444)"
                            >Habis</strong
                        >
                    </div>
                    <div class="catalog-footer">
                        <span class="catalog-price"
                            >Rp{{ formatPrice(product.price) }}</span
                        >
                        <div class="quantity-control">
                            <button
                                type="button"
                                @click="updateQty(product.id, -1)"
                                :disabled="getQty(product.id) === 0"
                            >
                                -
                            </button>
                            <input
                                type="number"
                                :value="getQty(product.id)"
                                @change="
                                    setQty(
                                        product,
                                        Number(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        ),
                                    )
                                "
                                @focus="
                                    ($event.target as HTMLInputElement).select()
                                "
                                min="0"
                                class="qty-input"
                            />
                            <button
                                type="button"
                                @click="updateQty(product.id, 1)"
                                :disabled="
                                    getQty(product.id) >=
                                    product.available_stock
                                "
                            >
                                +
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="productPagination?.next !== 0 && !isLoadingCatalog"
                class="pagination-row mt-4"
            >
                <button
                    class="pagination-btn !w-full"
                    :disabled="isLoadingMoreCatalog"
                    @click="loadMoreProducts"
                >
                    <v-progress-circular
                        v-if="isLoadingMoreCatalog"
                        indeterminate
                        size="16"
                        color="var(--wine)"
                        class="mr-2"
                    />
                    {{ isLoadingMoreCatalog ? "Memuat..." : "Muat lainnya" }}
                </button>
            </div>

            <div
                v-else-if="!isLoadingCatalog && products.length === 0"
                style="padding: 40px 24px; text-align: center"
                class="card mt-4"
            >
                <v-icon
                    icon="mdi-image-outline"
                    color="#ccc"
                    size="48"
                    class="mb-3"
                ></v-icon>
                <p style="margin: 0; color: var(--muted); font-size: 14px">
                    Tidak ada produk yang ditemukan.
                </p>
            </div>

            <div class="floating-action-bar">
                <button
                    class="primary-button block"
                    @click="goToCheckout"
                    :disabled="totalQuantity === 0 || isProceedingToCheckout"
                >
                    <v-progress-circular
                        v-if="isProceedingToCheckout"
                        indeterminate
                        size="16"
                        width="2"
                        color="white"
                        class="mr-2"
                    ></v-progress-circular>
                    Lanjut ke Checkout · {{ totalQuantity }} pcs
                    <v-icon
                        v-if="!isProceedingToCheckout"
                        icon="mdi-arrow-right"
                        size="14"
                        class="ml-1"
                    />
                </button>
            </div>

            <div style="height: 80px"></div>
        </div>

        <div v-if="step === 2" class="checkout-container">
            <div class="section-heading mb-2 mt-3">
                <h3>Rincian Produk</h3>
            </div>
            <div class="product-grid" v-if="cartItems.length > 0">
                <div
                    v-for="item in cartItems"
                    :key="item.product.id"
                    class="card checkout-item"
                >
                    <div
                        class="product-art"
                        style="border: 1px solid #eaeaea; background: #fff"
                    >
                        <v-img
                            v-if="
                                item.product.image &&
                                !item.product.image.includes('default')
                            "
                            :src="item.product.image"
                        ></v-img>
                        <v-icon
                            v-else
                            icon="mdi-image-outline"
                            color="#ccc"
                            size="24"
                        ></v-icon>
                    </div>
                    <div class="checkout-copy" style="flex: 1">
                        <div
                            style="
                                display: flex;
                                align-items: flex-start;
                                justify-content: space-between;
                                gap: 8px;
                            "
                        >
                            <strong>{{ item.product.name }}</strong>
                        </div>
                        <div
                            v-if="item.product?.bpom_number"
                            style="
                                font-size: 11px;
                                color: var(--muted);
                                margin-top: 2px;
                            "
                        >
                            BPOM: {{ item.product.bpom_number }}
                        </div>
                        <div
                            style="
                                display: flex;
                                justify-content: space-between;
                                align-items: flex-end;
                                margin-top: 4px;
                            "
                        >
                            <span style="font-size: 12px; color: var(--muted)"
                                >{{ item.quantity }} × Rp{{
                                    formatPrice(item.product.price)
                                }}</span
                            ><strong style="font-size: 13px"
                                >Rp{{
                                    formatPrice(
                                        item.quantity * item.product.price,
                                    )
                                }}</strong
                            >
                        </div>

                        <!-- Batch Inputs -->
                        <div
                            class="mt-3 pt-3"
                            style="border-top: 1px dashed #eaeaea"
                        >
                            <div
                                style="
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                    margin-bottom: 8px;
                                "
                            >
                                <span style="font-size: 12px; font-weight: 600"
                                    >Batch Produk</span
                                >
                                <button
                                    type="button"
                                    @click="addBatch(item.product.id)"
                                    style="
                                        color: var(--primary);
                                        font-size: 12px;
                                        font-weight: 600;
                                    "
                                >
                                    <v-icon
                                        icon="mdi-plus"
                                        size="14"
                                        class="mr-1"
                                    ></v-icon>
                                    Tambah Batch
                                </button>
                            </div>
                            <div class="d-flex flex-column gap-3 mt-2">
                                <div
                                    v-for="(batch, bIdx) in batchNumbers[
                                        item.product.id
                                    ] || []"
                                    :key="bIdx"
                                    class="d-flex align-start gap-2"
                                >
                                    <div
                                        class="d-flex align-center justify-center pt-3 font-weight-bold"
                                        style="
                                            width: 20px;
                                            font-size: 12px;
                                            color: var(--muted);
                                            flex-shrink: 0;
                                        "
                                    >
                                        #{{ bIdx + 1 }}
                                    </div>
                                    <div style="flex: 1; min-width: 0">
                                        <v-row dense>
                                            <v-col cols="12" sm="5" md="5">
                                                <v-text-field
                                                    v-model="batch.batch"
                                                    label="Nomor Batch *"
                                                    variant="outlined"
                                                    density="compact"
                                                    hide-details="auto"
                                                    :rules="[
                                                        (v) =>
                                                            !!v ||
                                                            'No Batch wajib diisi',
                                                    ]"
                                                />
                                            </v-col>
                                            <v-col cols="12" sm="3" md="3">
                                                <v-text-field
                                                    v-model.number="batch.qty"
                                                    label="Qty *"
                                                    type="number"
                                                    min="1"
                                                    variant="outlined"
                                                    density="compact"
                                                    hide-details="auto"
                                                    :rules="[
                                                        (v) =>
                                                            !!v ||
                                                            'Qty Wajib diisi',
                                                        (v) => v > 0 || 'Min 1',
                                                    ]"
                                                />
                                            </v-col>
                                            <v-col cols="12" sm="4" md="4">
                                                <v-text-field
                                                    v-model="batch.expiryDate"
                                                    label="Tanggal Kedaluwarsa *"
                                                    type="date"
                                                    :min="minExpiryDate"
                                                    variant="outlined"
                                                    density="compact"
                                                    hide-details="auto"
                                                    :rules="[
                                                        (v) =>
                                                            !!v ||
                                                            'Tanggal kedaluwarsa wajib diisi',
                                                        (v) =>
                                                            v >= minExpiryDate ||
                                                            'Tanggal kedaluwarsa harus setelah hari ini',
                                                    ]"
                                                />
                                            </v-col>
                                        </v-row>
                                    </div>
                                    <button
                                        type="button"
                                        @click="
                                            removeBatch(item.product.id, bIdx)
                                        "
                                        class="pt-3 pl-1"
                                        style="color: var(--danger)"
                                    >
                                        <v-icon
                                            icon="mdi-delete-outline"
                                            size="20"
                                        ></v-icon>
                                    </button>
                                </div>
                            </div>
                            <div
                                v-if="
                                    getBatchTotalQty(item.product.id) !==
                                    item.quantity
                                "
                                style="
                                    font-size: 12px;
                                    color: var(--danger);
                                    margin-top: 4px;
                                "
                            >
                                Total qty batch ({{
                                    getBatchTotalQty(item.product.id)
                                }}) tidak sama dengan qty pesanan ({{
                                    item.quantity
                                }}).
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-heading mb-2 mt-4">
                <h3>Data Pelanggan</h3>
            </div>

            <div
                class="card mb-3"
                style="
                    padding: 12px;
                    gap: 8px;
                    display: flex;
                    flex-direction: column;
                "
            >
                <v-autocomplete
                    v-model="form.customer_id"
                    :items="customerOptions"
                    item-title="name"
                    item-value="id"
                    label="Pilih Pelanggan *"
                    variant="outlined"
                    density="comfortable"
                    hide-details
                    :loading="isLoadingCustomers"
                    bg-color="white"
                >
                    <template v-slot:item="{ props, item }">
                        <v-list-item
                            v-bind="props"
                            :title="item.raw.name"
                            :subtitle="item.raw.phone || item.raw.whatsapp"
                        ></v-list-item>
                    </template>
                </v-autocomplete>
                <v-btn
                    variant="tonal"
                    color="primary"
                    block
                    class="text-none font-weight-medium rounded-lg"
                    @click="openCreateCustomer"
                >
                    <v-icon icon="mdi-plus" size="18" class="mr-1"></v-icon>
                    Tambah Pelanggan Baru
                </v-btn>
            </div>

            <div class="section-heading mb-2 mt-4">
                <h3>Opsi Pengiriman</h3>
            </div>
            <div
                style="
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: 8px;
                    margin-bottom: 16px;
                "
            >
                <div
                    class="card delivery-option-card"
                    :class="{ active: deliveryType === 'pickup' }"
                >
                    <v-icon icon="mdi-storefront-outline" size="24" />
                    <strong>Ambil di Tempat</strong>
                </div>
            </div>

            <template v-if="deliveryType === 'pickup'">
                <div class="section-heading mb-2 mt-4">
                    <h3>Lokasi Pengambilan</h3>
                </div>
                <div class="card mb-3" style="padding: 12px">
                    <div
                        style="
                            display: flex;
                            align-items: flex-start;
                            gap: 12px;
                        "
                    >
                        <span class="row-icon w-[25px] h-[25px] shrink-0">
                            <v-icon
                                icon="mdi-storefront-outline"
                                color="var(--wine)"
                                size="24"
                            />
                        </span>
                        <div>
                            <strong
                                style="
                                    display: block;
                                    font-size: 13px;
                                    color: var(--text);
                                "
                                >{{
                                    formOptions?.seller?.name || "Penjual"
                                }}</strong
                            >
                            <span
                                v-if="formOptions?.seller?.origin"
                                class="text-[var(--muted)] text-[12px] leading-snug block"
                                v-html="
                                    formatLocation(formOptions.seller.origin)
                                "
                            ></span>
                        </div>
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="section-heading mb-2 mt-4">
                    <h3>Dikirim Dari</h3>
                </div>
                <div class="card mb-3" style="padding: 12px">
                    <div
                        style="
                            display: flex;
                            align-items: flex-start;
                            gap: 12px;
                        "
                    >
                        <span class="row-icon w-[25px] h-[25px] shrink-0">
                            <v-icon
                                icon="mdi-storefront-outline"
                                color="var(--wine)"
                                size="24"
                            />
                        </span>
                        <div>
                            <strong
                                style="
                                    display: block;
                                    font-size: 13px;
                                    color: var(--text);
                                "
                                >{{
                                    formOptions?.seller?.name || "Penjual"
                                }}</strong
                            >
                            <span
                                v-if="formOptions?.seller?.origin"
                                class="text-[var(--muted)] text-[12px] leading-snug block mt-1"
                                v-html="
                                    formatLocation(formOptions.seller.origin)
                                "
                            ></span>
                        </div>
                    </div>
                </div>

                <div class="section-heading mb-2 mt-4">
                    <h3>Metode Pengiriman</h3>
                </div>
                <div class="mb-3">
                    <div
                        style="
                            display: flex;
                            gap: 8px;
                            overflow-x: auto;
                            padding-bottom: 8px;
                            margin-bottom: 8px;
                            scrollbar-width: none;
                        "
                    >
                        <button
                            v-for="method in (
                                formOptions?.shipping_methods || []
                            ).filter((m: any) => m.code !== 'pickup')"
                            :key="method.code"
                            type="button"
                            class="shipping-method-chip"
                            :class="{
                                active: form.shipping_method === method.code,
                            }"
                            @click="form.shipping_method = method.code"
                        >
                            {{ method.name }}
                        </button>
                    </div>
                </div>

                <!-- DYNAMIC RATES LIST -->
                <div v-if="form.shipping_method.includes('courier')">
                    <div class="section-heading mb-2 mt-4">
                        <h3>Kurir Pengiriman</h3>
                    </div>
                    <div
                        v-if="isLoadingRates"
                        style="
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            justify-content: center;
                            gap: 12px;
                            padding: 32px 16px;
                            border: 1px dashed #eaeaea;
                            border-radius: 12px;
                        "
                    >
                        <v-progress-circular
                            indeterminate
                            size="24"
                            width="2"
                            color="primary"
                        ></v-progress-circular>
                        <span style="font-size: 13px; color: var(--muted)"
                            >Menghitung tarif pengiriman...</span
                        >
                    </div>
                    <div
                        v-else-if="availableRates.length > 0"
                        style="display: flex; flex-direction: column; gap: 8px"
                    >
                        <div
                            v-for="rate in availableRates"
                            :key="rate.service_type"
                            @click="selectRate(rate)"
                            class="rate-option card"
                            :class="{
                                active:
                                    selectedRate?.service_type ===
                                    rate.service_type,
                            }"
                        >
                            <div
                                style="
                                    display: flex;
                                    align-items: center;
                                    gap: 12px;
                                    flex: 1;
                                "
                            >
                                <div
                                    class="rate-radio"
                                    :class="{
                                        selected:
                                            selectedRate?.service_type ===
                                            rate.service_type,
                                    }"
                                ></div>
                                <img
                                    v-if="rate.logo_url"
                                    :src="rate.logo_url"
                                    style="
                                        height: 16px;
                                        object-fit: contain;
                                        border-radius: 2px;
                                    "
                                />
                                <div
                                    style="
                                        display: flex;
                                        flex-direction: column;
                                        flex: 1;
                                    "
                                >
                                    <strong
                                        style="
                                            font-size: 13px;
                                            color: var(--text);
                                        "
                                        >{{ rate.courier_name }} ({{
                                            rate.service_type
                                        }})</strong
                                    >
                                    <span
                                        style="
                                            font-size: 12px;
                                            color: var(--muted);
                                            margin-top: 2px;
                                        "
                                    >
                                        <span v-if="rate.etd"
                                            >Estimasi {{ rate.etd }} hari</span
                                        >
                                        <span v-else
                                            >Estimasi tidak tersedia</span
                                        >
                                    </span>
                                </div>
                                <strong
                                    style="font-size: 13px; color: var(--text)"
                                    >Rp{{ formatPrice(rate.cost) }}</strong
                                >
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        style="
                            padding: 16px;
                            border: 1px dashed #eaeaea;
                            border-radius: 12px;
                            text-align: center;
                            color: var(--muted);
                            font-size: 13px;
                        "
                    >
                        Pilih pelanggan terlebih dahulu untuk melihat opsi kurir
                        pengiriman.
                    </div>
                </div>
            </template>

            <div class="section-heading mb-2 mt-4">
                <h3>Metode Pembayaran</h3>
            </div>
            <div
                style="
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: 8px;
                    margin-bottom: 16px;
                "
            >
                <div
                    v-for="method in formOptions?.payment_methods || []"
                    :key="method.code"
                    class="card delivery-option-card"
                    :class="{ active: form.payment_method === method.code }"
                    @click="form.payment_method = method.code"
                >
                    <v-icon
                        :icon="
                            method.code === 'transfer'
                                ? 'mdi-bank-outline'
                                : 'mdi-cash'
                        "
                        size="24"
                    />
                    <strong>{{ method.name }}</strong>
                </div>
            </div>

            <template v-if="form.payment_method === 'transfer'">
                <div class="section-heading mb-2 mt-4">
                    <h3>Pilih Bank Tujuan</h3>
                </div>
                <div
                    class="mb-3"
                    style="display: flex; flex-direction: column; gap: 8px"
                >
                    <div
                        v-for="bank in formOptions?.bank_accounts || []"
                        :key="bank.id"
                        class="rate-option card"
                        :class="{ active: form.bank_account_id === bank.id }"
                        @click="form.bank_account_id = bank.id"
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
                                    selected: form.bank_account_id === bank.id,
                                }"
                                style="margin-top: 2px"
                            ></div>
                            <div class="address-details" style="flex: 1">
                                <strong
                                    style="font-size: 13px; color: var(--text)"
                                    >{{ bank.bank_name }}</strong
                                >
                                <span
                                    style="
                                        display: block;
                                        font-size: 12px;
                                        color: var(--muted);
                                        margin: 2px 0;
                                    "
                                    >{{ bank.account_name }} ·
                                    {{ bank.account_number }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div class="card summary-card mt-4 mb-4">
                <div
                    class="summary-line"
                    style="
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 8px;
                    "
                >
                    <span style="font-size: 13px; color: var(--muted)"
                        >Total Harga Produk</span
                    >
                    <strong style="font-size: 13px"
                        >Rp{{ formatPrice(subtotal) }}</strong
                    >
                </div>
                <div
                    v-if="form.shipping_method.includes('courier')"
                    class="summary-line"
                    style="
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 8px;
                    "
                >
                    <span style="font-size: 13px; color: var(--muted)"
                        >Ongkos Kirim</span
                    >
                    <strong style="font-size: 13px"
                        >Rp{{ formatPrice(shippingCost) }}</strong
                    >
                </div>
                <div
                    v-if="
                        form.shipping_method.includes('courier') &&
                        selectedRate?.force_insurance &&
                        selectedRate.insurance > 0
                    "
                    class="summary-line"
                    style="
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 8px;
                    "
                >
                    <span style="font-size: 13px; color: var(--muted)"
                        >Asuransi Pengiriman</span
                    >
                    <strong style="font-size: 13px"
                        >Rp{{ formatPrice(selectedRate.insurance) }}</strong
                    >
                </div>
                <div
                    style="border-top: 1px dashed #eaeaea; margin: 8px 0"
                ></div>
                <div
                    class="summary-line total"
                    style="
                        display: flex;
                        justify-content: space-between;
                        margin-top: 8px;
                    "
                >
                    <span style="font-weight: 600; font-size: 14px"
                        >Total Pembayaran</span
                    >
                    <span
                        style="
                            font-weight: 800;
                            font-size: 15px;
                            color: var(--primary);
                        "
                        >Rp{{ formatPrice(grandTotal) }}</span
                    >
                </div>
            </div>

            <div class="mt-4 mb-4">
                <button
                    class="primary-button block"
                    @click="submitOrder"
                    :disabled="isSubmitting || !isFormValid"
                >
                    <span v-if="!isSubmitting"
                        >Buat & Serahkan Pesanan · Rp{{
                            formatPrice(grandTotal)
                        }}</span
                    >
                    <v-progress-circular
                        v-else
                        indeterminate
                        size="20"
                        color="white"
                    ></v-progress-circular>
                </button>
            </div>
        </div>

        <CreateCustomerDialog
            ref="createCustomerDialogRef"
            :default-address="formOptions?.seller.origin"
            @saved="onCustomerCreated"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import saleService from "@/member/services/sale.service";
import purchaseService from "@/member/services/purchase.service";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import CreateCustomerDialog from "@/member/components/sales/CreateCustomerDialog.vue";
import { encodeRouteId } from "@/shared/utils/route-id";
import type {
    ProductCatalog,
    SaleFormOptions,
    SaleOrderPayload,
} from "@/member/types/sale";

const router = useRouter();
const snackbar = useSnackbarStore();
const { formatPrice, formatLocation } = useFormatter();

const step = ref(1);
const isLoadingCatalog = ref(true);
const isLoadingMoreCatalog = ref(false);
const isSubmitting = ref(false);
const isProceedingToCheckout = ref(false);
const isLoadingCustomers = ref(false);

const deliveryType = ref<"pickup">("pickup");

const products = ref<ProductCatalog[]>([]);
const categories = ref<any[]>([]);
const currentCategory = ref<number | null>(null);
const searchQuery = ref("");
const productPagination = ref<any>({ next: 0 });

const cart = ref<Record<number, number>>({});
const batchNumbers = ref<
    Record<number, { batch: string; qty: number; expiryDate: string }[]>
>({});
const minimumDate = new Date();
minimumDate.setDate(minimumDate.getDate() + 1);
const minExpiryDate = [
    minimumDate.getFullYear(),
    String(minimumDate.getMonth() + 1).padStart(2, "0"),
    String(minimumDate.getDate()).padStart(2, "0"),
].join("-");

const formOptions = ref<SaleFormOptions | null>(null);
const customerOptions = ref<any[]>([]);

const createCustomerDialogRef = ref<any>(null);

const form = ref({
    customer_id: null as number | null,
    payment_method: "cash",
    bank_account_id: null as number | null,
    shipping_method: "pickup",
    shipping_cost: 0,
    courier: null as any,
    items: [] as any[],
});

const shippingCost = ref(0);
const isLoadingRates = ref(false);
const availableRates = ref<any[]>([]);
const selectedRate = ref<any | null>(null);

const selectRate = (rate: any) => {
    selectedRate.value = rate;
    shippingCost.value = rate.cost;
    form.value.shipping_cost = rate.cost;
    form.value.courier = { ...rate };
};

const cartProducts = computed(() => {
    const result = [];
    for (const [id, qty] of Object.entries(cart.value)) {
        if (qty > 0) {
            const product = products.value.find((p) => p.id === Number(id));
            if (product) {
                result.push({ product, quantity: qty });
            }
        }
    }
    return result;
});

const calculateShipping = async () => {
    const method = form.value.shipping_method;

    if (!method.includes("courier")) {
        shippingCost.value = 0;
        form.value.shipping_cost = 0;
        availableRates.value = [];
        selectedRate.value = null;
        form.value.courier = null;
        return;
    }

    if (!formOptions.value || !form.value.customer_id) return;

    const selectedCustomer = customerOptions.value.find(
        (c: any) => c.id === form.value.customer_id,
    );
    if (!selectedCustomer) return;

    isLoadingRates.value = true;
    availableRates.value = [];
    selectedRate.value = null;
    form.value.courier = null;
    shippingCost.value = 0;
    form.value.shipping_cost = 0;

    try {
        const payload = {
            customer_id: form.value.customer_id,
            origin: {
                district_id:
                    (formOptions.value.seller as any)?.district_id || 0,
                subdistrict_id:
                    (formOptions.value.seller as any)?.subdistrict_id || 0,
            },
            destination: {
                district_id: selectedCustomer.district_id || 0,
                subdistrict_id: selectedCustomer.subdistrict_id || 0,
            },
            couriers: ["jne", "jnt", "sicepat"],
            items: cartProducts.value.map((item) => ({
                product_id: item.product.id,
                quantity: item.quantity,
            })),
        };

        const response = await saleService.getExpressRates(payload);
        if (response.success && response.data?.results) {
            availableRates.value = response.data.results;
            if (availableRates.value.length > 0) {
                selectRate(availableRates.value[0]);
            }
        } else {
            snackbar.showMessage(
                response.message || "Gagal memuat tarif kurir",
                "error",
            );
        }
    } catch (e) {
        console.error("Failed to load shipping rates", e);
        snackbar.showMessage("Gagal memuat tarif kurir", "error");
    } finally {
        isLoadingRates.value = false;
    }
};

watch(
    () => form.value.customer_id,
    () => {
        calculateShipping();
    },
);

watch(
    () => form.value.shipping_method,
    () => {
        calculateShipping();
    },
);

const loadCatalog = async (page = 1, append = false) => {
    if (append) isLoadingMoreCatalog.value = true;
    else isLoadingCatalog.value = true;

    try {
        const promises: Promise<any>[] = [
            saleService.getCatalog({
                page,
                limit: 20,
                search: searchQuery.value || undefined,
                category_id: currentCategory.value || undefined,
            }),
        ];

        if (categories.value.length === 0) {
            promises.push(purchaseService.getCatalogCategories());
        }

        const [prodRes, catRes] = await Promise.all(promises);

        if (prodRes.success && prodRes.data) {
            if (append) {
                products.value = [...products.value, ...prodRes.data.results];
            } else {
                products.value = prodRes.data.results;
            }
            productPagination.value = prodRes.data.pagination;
        }
        if (catRes && catRes.success && catRes.data) {
            categories.value = catRes.data;
        }
    } catch (e) {
        console.error("Failed to load catalog", e);
    } finally {
        isLoadingCatalog.value = false;
        isLoadingMoreCatalog.value = false;
    }
};

const loadMoreProducts = () => {
    if (productPagination.value?.next !== 0) {
        loadCatalog(productPagination.value.next, true);
    }
};

let productSearchDebounceTimer: ReturnType<typeof setTimeout> | null = null;
watch([searchQuery, currentCategory], () => {
    if (productSearchDebounceTimer) clearTimeout(productSearchDebounceTimer);
    productSearchDebounceTimer = setTimeout(() => {
        loadCatalog(1);
    }, 500);
});

const getFormOptions = async () => {
    try {
        const res = await saleService.getFormOptions();
        if (res.success) {
            formOptions.value = res.data;
            form.value.shipping_method = "pickup";
            form.value.payment_method = "cash";
            if (res.data.bank_accounts.length > 0) {
                form.value.bank_account_id = res.data.bank_accounts[0].id;
            }
        }
    } catch (error) {
        console.error("Failed to fetch options", error);
    }
};

const loadCustomers = async () => {
    isLoadingCustomers.value = true;
    try {
        const res = await saleService.getCustomerOptions();
        if (res.success) {
            customerOptions.value = res.data.results;
        }
    } catch (error) {
        console.error("Failed to load customer options", error);
    } finally {
        isLoadingCustomers.value = false;
    }
};

const openCreateCustomer = () => {
    if (createCustomerDialogRef.value) {
        createCustomerDialogRef.value.open();
    }
};

const onCustomerCreated = (newCustomer: any) => {
    customerOptions.value.push(newCustomer);
    form.value.customer_id = newCustomer.id;
};

const getQty = (id: number) => cart.value[id] || 0;

const updateQty = (id: number, delta: number) => {
    const p = products.value.find((x) => x.id === id);
    if (!p) return;
    const current = getQty(id);
    const newVal = Math.max(0, current + delta);
    if (newVal > p.available_stock) return;
    cart.value[id] = newVal;
    if (newVal === 0) {
        delete batchNumbers.value[id];
    } else if (!batchNumbers.value[id] || batchNumbers.value[id].length === 0) {
        batchNumbers.value[id] = [{ batch: "", qty: newVal, expiryDate: "" }];
    } else if (batchNumbers.value[id].length === 1) {
        batchNumbers.value[id][0].qty = newVal;
    }
};

const setQty = (product: any, value: number) => {
    const qty = Math.max(0, Math.floor(value) || 0);
    const availableStock = product.available_stock || 0;

    if (availableStock > 0 && qty > availableStock) {
        snackbar.showMessage(
            `Jumlah ${product.name} tidak boleh melebihi stok tersedia (${availableStock}).`,
            "error",
        );
        cart.value[product.id] = availableStock;
    } else {
        cart.value[product.id] = qty;
    }

    if (cart.value[product.id] === 0) {
        delete batchNumbers.value[product.id];
    } else if (
        !batchNumbers.value[product.id] ||
        batchNumbers.value[product.id].length === 0
    ) {
        batchNumbers.value[product.id] = [
            { batch: "", qty: cart.value[product.id], expiryDate: "" },
        ];
    } else if (batchNumbers.value[product.id].length === 1) {
        batchNumbers.value[product.id][0].qty = cart.value[product.id];
    }
};

const addBatch = (productId: number) => {
    if (!batchNumbers.value[productId]) {
        batchNumbers.value[productId] = [];
    }
    batchNumbers.value[productId].push({ batch: "", qty: 1, expiryDate: "" });
};

const removeBatch = (productId: number, index: number) => {
    if (batchNumbers.value[productId]) {
        batchNumbers.value[productId].splice(index, 1);
    }
};

const getBatchTotalQty = (productId: number) => {
    const batches = batchNumbers.value[productId] || [];
    return batches.reduce((sum, b) => sum + (Number(b.qty) || 0), 0);
};

const totalQuantity = computed(() => {
    return Object.values(cart.value).reduce((sum, q) => sum + q, 0);
});

const subtotal = computed(() => {
    let total = 0;
    products.value.forEach((p) => {
        total += getQty(p.id) * p.price;
    });
    return total;
});

const cartItems = computed(() => {
    const result = [];
    for (const [id, qty] of Object.entries(cart.value)) {
        if (qty > 0) {
            const product = products.value.find((p) => p.id === Number(id));
            if (product) {
                result.push({ product, quantity: qty });
            }
        }
    }
    return result;
});

const allBatchesFilled = computed(() => {
    return cartItems.value.every((item) => {
        const batches = batchNumbers.value[item.product.id] || [];
        if (batches.length === 0) return false;

        let totalQty = 0;
        for (const b of batches) {
            if (!b.batch || b.batch.trim() === "" || b.batch.length > 50)
                return false;
            if (!Number.isInteger(Number(b.qty)) || b.qty <= 0) return false;
            if (!b.expiryDate || b.expiryDate < minExpiryDate) return false;
            totalQty += Number(b.qty) || 0;
        }

        return totalQty === item.quantity;
    });
});

const goToCheckout = async () => {
    if (totalQuantity.value === 0) return;
    isProceedingToCheckout.value = true;
    try {
        if (!formOptions.value) await getFormOptions();
        if (customerOptions.value.length === 0) await loadCustomers();
        step.value = 2;
    } finally {
        isProceedingToCheckout.value = false;
    }
};

const grandTotal = computed(() => {
    let total = subtotal.value;
    if (form.value.shipping_method.includes("courier")) {
        total += shippingCost.value;
        if (
            selectedRate.value?.force_insurance &&
            selectedRate.value.insurance
        ) {
            total += selectedRate.value.insurance;
        }
    }
    return total;
});

const isFormValid = computed(() => {
    return (
        form.value.customer_id !== null &&
        form.value.payment_method !== "" &&
        (form.value.payment_method !== "transfer" ||
            form.value.bank_account_id !== null) &&
        form.value.shipping_method !== "" &&
        (!form.value.shipping_method.includes("courier") ||
            form.value.courier !== null) &&
        allBatchesFilled.value
    );
});

const submitOrder = async () => {
    if (!isFormValid.value || !form.value.customer_id) return;
    isSubmitting.value = true;

    const items = cartItems.value.map((item) => ({
        product_id: item.product.id,
        quantity: item.quantity,
        batches: (batchNumbers.value[item.product.id] || []).map((batch) => ({
            quantity: Number(batch.qty),
            batch_number: batch.batch.trim(),
            expiry_date: batch.expiryDate,
        })),
    }));

    const payload: any = {
        customer_id: form.value.customer_id,
        payment_method: form.value.payment_method,
        shipping_method: form.value.shipping_method,
        items,
    };

    if (
        form.value.payment_method === "transfer" ||
        form.value.payment_method === "bank_transfer"
    ) {
        payload.bank_account_id = form.value.bank_account_id;
    }

    if (form.value.shipping_method.includes("courier") && form.value.courier) {
        payload.courier = {
            name: form.value.courier.courier_name || form.value.courier.name,
            service:
                form.value.courier.service_type || form.value.courier.service,
            cost: form.value.courier.cost,
            insurance: form.value.courier.insurance || 0,
        };
    }

    try {
        const res = await saleService.createOrder(payload as SaleOrderPayload);
        if (res.success) {
            snackbar.showMessage("Pesanan POS berhasil diselesaikan.", "success");
            router.replace(
                `/member/transactions/sales/${encodeRouteId(res.data.id)}`,
            );
        }
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Gagal membuat pesanan POS",
            "error",
        );
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    loadCatalog();
});
</script>

<style scoped>
.pos-batch-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(150px, 220px);
    gap: 12px;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid #f1e3e6;
}

.pos-batch-row:first-of-type {
    padding-top: 0;
    border-top: 0;
}

.pos-batch-product {
    display: block;
    font-size: 13px;
    line-height: 1.35;
    color: var(--text);
}

.pos-batch-qty {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: var(--muted);
}

@media (max-width: 640px) {
    .pos-batch-row {
        grid-template-columns: 1fr;
        gap: 8px;
    }
}
</style>
