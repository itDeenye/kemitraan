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
                    :style="{
                        height: '100%',
                        marginTop: 0,
                        opacity:
                            product.stock?.status?.code === 'out_of_stock'
                                ? '0.6'
                                : '1',
                        filter:
                            product.stock?.status?.code === 'out_of_stock'
                                ? 'grayscale(100%)'
                                : 'none',
                    }"
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
                    <div
                        style="
                            display: flex;
                            justify-content: space-between;
                            align-items: flex-start;
                            margin-bottom: 8px;
                        "
                    >
                        <div>
                            <h4 style="margin-bottom: 4px">
                                {{ product.name }}
                            </h4>
                            <p style="margin: 0">{{ product.code }}</p>
                            <p
                                style="
                                    margin: 4px 0 0;
                                    font-size: 11px;
                                    color: var(--muted);
                                "
                            >
                                BPOM: {{ product.bpom_number || "-" }}
                            </p>
                        </div>
                        <v-btn
                            icon
                            variant="text"
                            size="small"
                            @click.stop="openProductDetail(product.id)"
                            style="
                                margin-top: -4px;
                                margin-right: -8px;
                                color: var(--muted);
                            "
                        >
                            <v-icon size="20">mdi-information-outline</v-icon>
                        </v-btn>
                    </div>
                    <div
                        class="catalog-stock"
                        style="
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            width: 100%;
                            margin-bottom: 8px;
                        "
                    >
                        <span
                            :class="{
                                'available-label':
                                    product.stock?.status?.code === 'available',
                                'low-stock-label':
                                    product.stock?.status?.code === 'low_stock',
                                'po-label':
                                    product.stock?.status?.code === 'preorder',
                            }"
                        >
                            {{
                                product.stock?.status?.label ||
                                (product.stock?.available > 0
                                    ? "Tersedia"
                                    : "PO")
                            }}
                        </span>
                        <span
                            :style="{
                                padding: '2px 8px',
                                borderRadius: '4px',
                                fontSize: '11px',
                                fontWeight: '600',
                                backgroundColor: product.is_package
                                    ? '#e8f5e9'
                                    : '#e3f2fd',
                                color: product.is_package
                                    ? '#2e7d32'
                                    : '#1565c0',
                            }"
                        >
                            {{ product.is_package ? "Paket" : "Ecer" }}
                        </span>
                    </div>
                    <div class="catalog-footer">
                        <span class="catalog-price"
                            >Rp{{ formatPrice(product.price) }}</span
                        >
                        <div class="quantity-control">
                            <button
                                type="button"
                                @click="updateQuantity(product.id, -1)"
                                :disabled="
                                    getQuantity(product.id) === 0 ||
                                    product.stock?.status?.code ===
                                        'out_of_stock'
                                "
                            >
                                -
                            </button>
                            <input
                                type="number"
                                :value="getQuantity(product.id)"
                                @change="
                                    setQuantity(
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
                                :disabled="
                                    product.stock?.status?.code ===
                                    'out_of_stock'
                                "
                                class="qty-input"
                            />
                            <button
                                type="button"
                                @click="updateQuantity(product.id, 1)"
                                :disabled="
                                    !canIncreaseProduct(product) ||
                                    product.stock?.status?.code ===
                                        'out_of_stock'
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
                    Tidak ada produk yang ditemukan untuk kategori ini.
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
                    {{
                        isPreorderCart
                            ? "Lanjut checkout PO"
                            : "Lanjut checkout"
                    }}
                    · {{ totalQuantity }} pcs
                    <v-icon
                        v-if="!isProceedingToCheckout"
                        icon="mdi-arrow-right"
                        size="14"
                        class="ml-1"
                    />
                </button>
            </div>

            <div style="height: 80px"></div>
            <!-- Spacing for floating bar -->
        </div>

        <!-- STEP 2: CHECKOUT -->
        <div v-if="step === 2 && checkoutOptions" class="checkout-container">
            <div
                v-if="isPreorderCart"
                class="card mb-3 mt-3"
                style="padding: 14px; background: #fff7ec; border-color: #ffe0ad"
            >
                <div style="display: flex; gap: 10px; align-items: flex-start">
                    <v-icon
                        icon="mdi-information-outline"
                        color="#c77700"
                        size="20"
                    />
                    <div>
                        <strong
                            style="display: block; font-size: 14px; color: #9a5a00"
                        >
                            Pesanan PO (inden)
                        </strong>
                        <span
                            style="font-size: 12px; color: #805000; line-height: 1.5"
                        >
                            Stok belum tersedia di penjual ini. Pesanan akan
                            disiapkan terlebih dahulu sebelum dikirim atau
                            diambil.
                        </span>
                    </div>
                </div>
            </div>
            <div class="section-heading mb-2 mt-3">
                <h3>Rincian Produk</h3>
            </div>
            <div class="product-grid" v-if="cartProducts.length > 0">
                <div
                    v-for="item in cartProducts"
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
                            <div
                                style="
                                    display: flex;
                                    flex-direction: column;
                                    align-items: flex-start;
                                "
                            >
                                <span
                                    style="font-size: 12px; color: var(--text)"
                                    >{{ item.product.code }}</span
                                >
                                <strong>{{ item.product.name }}</strong>
                            </div>
                            <span
                                v-if="item.isPreorder"
                                class="po-label"
                                style="white-space: nowrap"
                                >PO</span
                            >
                        </div>
                        <div
                            style="
                                font-size: 11px;
                                color: var(--muted);
                                margin-top: 2px;
                            "
                        >
                            BPOM: {{ item.product.bpom_number || "-" }}
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
                                }}
                                <br />
                                <span style="font-size: 11px"
                                    >Berat:
                                    {{ item.product.weight_grams || 0 }}gram
                                    /pcs</span
                                > </span
                            ><strong style="font-size: 13px"
                                >Rp{{
                                    formatPrice(
                                        item.quantity * item.product.price,
                                    )
                                }}</strong
                            >
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="card mb-3"
                style="
                    padding: 12px;
                    margin-top: 12px;
                    margin-bottom: 24px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                "
            >
                <span style="font-size: 13px; font-weight: 500"
                    >Total Berat Pesanan</span
                >
                <strong style="font-size: 14px">{{
                    totalWeight >= 1000
                        ? (totalWeight / 1000).toFixed(2) + "kg"
                        : totalWeight + "gram"
                }}</strong>
            </div>

            <div class="section-heading mb-2 mt-4">
                <h3>Opsi Pengiriman</h3>
            </div>
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
                    :class="{ active: deliveryType === 'delivery' }"
                    @click="deliveryType = 'delivery'"
                >
                    <v-icon icon="mdi-truck-outline" size="24" />
                    <strong>Dikirim ke Alamat</strong>
                </div>
                <div
                    class="card delivery-option-card"
                    :class="{ active: deliveryType === 'pickup' }"
                    @click="deliveryType = 'pickup'"
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
                                >{{ effectiveOriginName }}</strong
                            >
                            <span
                                style="color: var(--muted); font-size: 12px"
                                >{{ effectiveOriginAddress }}</span
                            >
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
                                >{{ effectiveOriginName }}</strong
                            >
                            <span
                                style="color: var(--muted); font-size: 12px"
                                >{{ effectiveOriginAddress }}</span
                            >
                        </div>
                    </div>
                </div>

                <div class="section-heading mb-2 mt-4">
                    <h3>Alamat Pengiriman</h3>
                </div>
                <div
                    class="mb-3"
                    style="display: flex; flex-direction: column; gap: 8px"
                >
                    <div
                        v-for="address in checkoutOptions.addresses"
                        :key="address.id"
                        class="rate-option card"
                        :class="{
                            active: form.address_id === address.id,
                        }"
                        @click="
                            form.address_id = address.id;
                            calculateShipping();
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
                                    selected: form.address_id === address.id,
                                }"
                                style="margin-top: 2px"
                            ></div>
                            <div class="address-details" style="flex: 1">
                                <strong
                                    style="font-size: 13px; color: var(--text)"
                                    >{{ address.label }}
                                    <span
                                        v-if="address.is_default"
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
                                    >{{ address.recipient }} ·
                                    {{ address.phone }}</span
                                >
                                <p
                                    style="
                                        margin: 0;
                                        font-size: 12px;
                                        color: var(--muted);
                                    "
                                >
                                    {{
                                        formatLocation(
                                            address.destination || address,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-heading mb-2 mt-4">
                    <h3>Metode Pengiriman</h3>
                </div>

                <div class="mb-3">
                    <!-- SHIPPING METHODS CHIPS -->
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
                            v-for="method in checkoutOptions.shipping_methods.filter(
                                (m) => m.code !== 'pickup',
                            )"
                            :key="method.code"
                            type="button"
                            class="shipping-method-chip"
                            :class="{
                                active: form.shipping_method === method.code,
                                disabled: method.code === 'courier_instant',
                            }"
                            :disabled="method.code === 'courier_instant'"
                            @click="
                                form.shipping_method = method.code;
                                calculateShipping();
                            "
                        >
                            {{ method.name }}
                        </button>
                    </div>

                    <!-- COURIER INSTANT MSG -->
                    <div
                        v-if="
                            form.shipping_method === 'courier_instant' ||
                            checkoutOptions.shipping_methods.find(
                                (m) =>
                                    m.code === 'courier_instant' &&
                                    form.shipping_method === 'courier_instant',
                            )
                        "
                        style="
                            padding: 12px;
                            font-size: 13px;
                            color: var(--danger);
                            background: #fff5f5;
                            border-radius: 8px;
                        "
                    >
                        Metode pengiriman ini belum tersedia saat ini.
                    </div>
                </div>

                <!-- DYNAMIC RATES LIST -->
                <div
                    v-if="
                        form.shipping_method === 'courier_express' ||
                        form.shipping_method === 'courier_manual'
                    "
                >
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
                                        v-if="rate.etd"
                                        style="
                                            font-size: 11px;
                                            color: var(--muted);
                                        "
                                        >Estimasi {{ rate.etd }} hari</span
                                    >
                                    <span
                                        v-if="
                                            rate.force_insurance &&
                                            rate.insurance > 0
                                        "
                                        style="
                                            font-size: 11px;
                                            color: var(--green);
                                            margin-top: 2px;
                                        "
                                        >+ Asuransi Rp{{
                                            formatPrice(rate.insurance)
                                        }}</span
                                    >
                                </div>
                                <strong
                                    style="
                                        font-size: 13px;
                                        color: var(--primary);
                                    "
                                    >Rp{{ formatPrice(rate.cost) }}</strong
                                >
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        style="
                            padding: 16px;
                            font-size: 13px;
                            color: var(--danger);
                            background: #fff5f5;
                            border-radius: 8px;
                            text-align: center;
                            border: 1px dashed #ffcdd2;
                        "
                    >
                        Tarif tidak ditemukan untuk alamat tujuan.
                    </div>
                </div>

                <!-- COURIER INSTANT MSG -->
                <div
                    v-if="
                        form.shipping_method === 'courier_instant' ||
                        checkoutOptions.shipping_methods.find(
                            (m) =>
                                m.code === 'courier_instant' &&
                                form.shipping_method === 'courier_instant',
                        )
                    "
                    style="
                        padding: 12px;
                        font-size: 13px;
                        color: var(--danger);
                        background: #fff5f5;
                        border-radius: 8px;
                    "
                >
                    Metode pengiriman ini belum tersedia saat ini.
                </div>
            </template>

            <template v-if="checkoutOptions?.voucher?.can_apply">
                <div class="section-heading mb-2 mt-4">
                    <h3>Voucher Belanja</h3>
                </div>
                <button
                    type="button"
                    class="checkout-choice-card mb-4"
                    :class="{ selected: form.use_voucher }"
                    @click="form.use_voucher = !form.use_voucher"
                >
                    <div
                        style="
                            display: flex;
                            align-items: center;
                            gap: 12px;
                            flex: 1;
                        "
                    >
                        <span class="choice-dot"></span>
                        <div
                            class="checkout-choice-copy"
                            style="text-align: left"
                        >
                            <strong>Gunakan Voucher Belanja</strong>
                            <span
                                style="color: var(--success); font-weight: 600"
                            >
                                Potongan Rp{{
                                    formatPrice(checkoutOptions.voucher.amount)
                                }}
                            </span>
                            <span
                                v-if="checkoutOptions.voucher.expiry_date"
                                style="
                                    display: block;
                                    color: var(--muted);
                                    font-size: 11px;
                                    margin-top: 2px;
                                "
                            >
                                Berlaku s.d.
                                {{
                                    formatDate(
                                        checkoutOptions.voucher.expiry_date,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </button>
            </template>

            <div class="section-heading mb-2">
                <h3>Pilih Bank Tujuan</h3>
            </div>
            <div class="mb-3">
                <div
                    v-if="
                        checkoutOptions?.banks &&
                        checkoutOptions.banks.length > 0
                    "
                >
                    <button
                        type="button"
                        v-for="bank in checkoutOptions.banks"
                        :key="bank.id"
                        class="checkout-choice-card mb-2"
                        :class="{ selected: form.bank_company_id === bank.id }"
                        @click="form.bank_company_id = bank.id"
                    >
                        <div
                            style="
                                display: flex;
                                align-items: center;
                                gap: 12px;
                                flex: 1;
                            "
                        >
                            <span class="choice-dot"></span>
                            <div class="checkout-choice-copy">
                                <strong>{{ bank.name }}</strong>
                                <span
                                    >{{ bank.account_name }} ·
                                    {{ bank.account_number }}</span
                                >
                            </div>
                        </div>
                    </button>
                </div>
                <div v-else class="card text-center mt-2" style="padding: 16px">
                    <v-icon
                        icon="mdi-bank-off"
                        color="#999"
                        size="32"
                        style="margin-bottom: 8px"
                    ></v-icon>
                    <p style="font-size: 13px; color: #999; margin: 0">
                        Belum ada rekening bank tujuan yang tersedia.
                    </p>
                </div>
            </div>

            <template
                v-if="
                    checkoutOptions.spread_payment_banks &&
                    checkoutOptions.spread_payment_banks.length > 0
                "
            >
                <div class="section-heading mb-2 mt-4">
                    <h3>Bank Kemitraan</h3>
                </div>
                <div class="mb-3">
                    <button
                        type="button"
                        v-for="bank in checkoutOptions.spread_payment_banks"
                        :key="bank.id"
                        class="checkout-choice-card mb-2"
                        :class="{
                            selected: form.bank_spread_payment_id === bank.id,
                        }"
                        @click="form.bank_spread_payment_id = bank.id"
                    >
                        <div
                            style="
                                display: flex;
                                align-items: center;
                                gap: 12px;
                                flex: 1;
                            "
                        >
                            <span class="choice-dot"></span>
                            <div class="checkout-choice-copy">
                                <strong>{{ bank.name }}</strong>
                                <span
                                    >{{ bank.account_name }} ·
                                    {{ bank.account_number }}</span
                                >
                            </div>
                        </div>
                    </button>
                </div>
            </template>

            <div class="card summary-card mt-4">
                <div class="summary-line">
                    <span>Jenis Pesanan</span>
                    <strong>{{
                        isPreorderCart ? "Pesanan PO" : "Pesanan Reguler"
                    }}</strong>
                </div>
                <div class="summary-line">
                    <span>Total Harga Produk</span>
                    <strong>Rp{{ formatPrice(subtotal) }}</strong>
                </div>
                <div class="summary-line">
                    <span>Ongkos Kirim</span>
                    <strong
                        >Rp{{
                            formatPrice(
                                form.shipping_method.includes("courier")
                                    ? shippingCost
                                    : 0,
                            )
                        }}</strong
                    >
                </div>
                <div
                    class="summary-line"
                    v-if="
                        form.shipping_method.includes('courier') &&
                        selectedRate?.force_insurance &&
                        selectedRate.insurance > 0
                    "
                >
                    <span>Asuransi Pengiriman</span>
                    <strong>Rp{{ formatPrice(selectedRate.insurance) }}</strong>
                </div>
                <div class="summary-line" v-if="voucherDiscountAmount > 0">
                    <span>Potongan Voucher</span>
                    <strong style="color: var(--danger)"
                        >-Rp{{ formatPrice(voucherDiscountAmount) }}</strong
                    >
                </div>
                <div class="summary-line total">
                    <span>Total Pembayaran</span>
                    <span>Rp{{ formatPrice(grandTotal) }}</span>
                </div>
            </div>

            <div class="mt-4 mb-4">
                <button
                    class="primary-button block"
                    @click="submitOrder"
                    :disabled="isSubmitting || !isFormValid"
                >
                    <span v-if="!isSubmitting"
                        >Buat Pesanan · Rp{{ formatPrice(grandTotal) }}</span
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

        <ProductDetailModal ref="productDetailModal" />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import purchaseService from "@/member/services/purchase.service";
import ProductDetailModal from "@/member/components/transactions/ProductDetailModal.vue";
import shippingService from "@/member/services/shipping.service";
import type {
    ShippingRateLocation,
    ShippingRateResult,
} from "@/member/types/shipping";
import { useFormatter } from "@/shared/composables/useFormatter";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { encodeRouteId } from "@/shared/utils/route-id";
import type {
    PurchaseProduct,
    PurchaseCategory,
    PurchaseCheckoutOptions,
} from "@/member/types/purchase";

const router = useRouter();
const snackbar = useSnackbarStore();
const { formatPrice, formatLocation, formatDate } = useFormatter();

const step = ref(1);
const isLoadingCatalog = ref(true);
const isSubmitting = ref(false);
const isProceedingToCheckout = ref(false);

const products = ref<PurchaseProduct[]>([]);
const categories = ref<PurchaseCategory[]>([]);
const currentCategory = ref<number | null>(null);
const searchQuery = ref("");
const showFilter = ref(false);
const isLoadingMoreCatalog = ref(false);
const productPagination = ref<any>({
    total_data: 0,
    total_page: 0,
    total_display: 0,
    first_page: false,
    last_page: false,
    prev: 0,
    current: 1,
    next: 0,
    detail: [],
    start: 0,
    end: 0,
});

const cart = ref<Record<number, number>>({});

const productDetailModal = ref<InstanceType<typeof ProductDetailModal> | null>(
    null,
);

const openProductDetail = (productId: number) => {
    productDetailModal.value?.open(productId);
};

const checkoutOptions = ref<PurchaseCheckoutOptions | null>(null);
const shippingCost = ref(0);

const isLoadingRates = ref(false);
const availableRates = ref<ShippingRateResult[]>([]);
const selectedRate = ref<ShippingRateResult | null>(null);
const shippingOrigin = ref<ShippingRateLocation | null>(null);

const deliveryType = ref<"delivery" | "pickup">("delivery");

watch(deliveryType, (val) => {
    if (val === "pickup") {
        form.value.shipping_method = "pickup";
    } else {
        const firstCourier = checkoutOptions.value?.shipping_methods.find(
            (m) => m.code !== "pickup",
        );
        if (firstCourier) form.value.shipping_method = firstCourier.code;
    }
    calculateShipping();
});

const form = ref({
    bank_company_id: null as number | null,
    bank_spread_payment_id: null as number | null,
    address_id: null as number | null,
    shipping_method: "courier_express",
    shipping_cost: 0,
    courier: null as any,
    use_voucher: false,
    items: [] as any[],
});

const hasSpreadPayment = computed(() => {
    return (checkoutOptions.value?.spread_payment_banks?.length ?? 0) > 0;
});

const loadCatalog = async (page = 1, append = false) => {
    if (append) isLoadingMoreCatalog.value = true;
    else isLoadingCatalog.value = true;

    try {
        const promises: Promise<any>[] = [
            purchaseService.getCatalogProducts({
                page,
                limit: 50,
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

const loadCheckoutOptions = async () => {
    try {
        const response = await purchaseService.getCheckoutOptions(
            cartProducts.value.map((item) => ({
                product_id: item.product.id,
                quantity: item.quantity,
            })),
        );
        if (response.success && response.data) {
            checkoutOptions.value = response.data;
            shippingOrigin.value = null;
            // set defaults
            if (response.data.addresses.length > 0) {
                const defaultAddress = response.data.addresses.find(
                    (a: any) => a.is_default,
                );
                form.value.address_id = defaultAddress
                    ? defaultAddress.id
                    : response.data.addresses[0].id;
            }
            if (response.data.banks.length > 0) {
                form.value.bank_company_id = response.data.banks[0].id;
            }
            if ((response.data.spread_payment_banks?.length ?? 0) > 0) {
                form.value.bank_spread_payment_id =
                    response.data.spread_payment_banks[0].id;
            } else {
                form.value.bank_spread_payment_id = null;
            }
            if (response.data.shipping_methods.length > 0) {
                // If there's a non-pickup method, default to delivery
                const hasCourier = response.data.shipping_methods.some(
                    (m: any) => m.code !== "pickup",
                );
                if (hasCourier) {
                    deliveryType.value = "delivery";
                    const firstCourier = response.data.shipping_methods.find(
                        (m: any) => m.code !== "pickup",
                    );
                    form.value.shipping_method = firstCourier.code;
                } else {
                    deliveryType.value = "pickup";
                    form.value.shipping_method = "pickup";
                }
            }
        }

        // After setting defaults, calculate shipping
        await calculateShipping();
    } catch (e) {
        console.error("Failed to load checkout options", e);
    }
};

const getQuantity = (id: number) => cart.value[id] || 0;

const canIncreaseProduct = (product: PurchaseProduct) => {
    const availableStock = product.stock?.available || 0;

    return availableStock === 0 || getQuantity(product.id) < availableStock;
};

const updateQuantity = (id: number, delta: number) => {
    const product = products.value.find((item) => item.id === id);
    if (delta > 0 && product && !canIncreaseProduct(product)) {
        snackbar.showMessage(
            `Jumlah ${product.name} tidak boleh melebihi stok tersedia.`,
            "error",
        );

        return;
    }

    const current = getQuantity(id);
    const newVal = Math.max(0, current + delta);
    cart.value[id] = newVal;
};

const setQuantity = (product: PurchaseProduct, value: number) => {
    const qty = Math.max(0, Math.floor(value) || 0);
    const availableStock = product.stock?.available || 0;

    if (availableStock > 0 && qty > availableStock) {
        snackbar.showMessage(
            `Jumlah ${product.name} tidak boleh melebihi stok tersedia (${availableStock}).`,
            "error",
        );
        cart.value[product.id] = availableStock;
        return;
    }

    cart.value[product.id] = qty;
};

const totalQuantity = computed(() => {
    return Object.values(cart.value).reduce((sum, q) => sum + q, 0);
});

const poProductCount = computed(() => {
    return cartProducts.value.filter((item) => item.isPreorder).length;
});

const subtotal = computed(() => {
    let total = 0;
    products.value.forEach((p) => {
        total += getQuantity(p.id) * p.price;
    });
    return total;
});

const totalWeight = computed(() => {
    let weight = 0;
    cartProducts.value.forEach((item) => {
        const w = item.product.weight_grams || 0;
        weight += item.quantity * w;
    });
    return weight;
});

const totalShippingAndInsurance = computed(() => {
    const sCost = form.value.shipping_method.includes("courier")
        ? shippingCost.value
        : 0;
    let insurance = 0;
    if (
        form.value.shipping_method.includes("courier") &&
        selectedRate.value?.force_insurance
    ) {
        insurance = selectedRate.value.insurance || 0;
    }
    return sCost + insurance;
});

const voucherDiscountAmount = computed(() => {
    if (!form.value.use_voucher || !checkoutOptions.value?.voucher?.amount)
        return 0;

    const totalBeforeVoucher = subtotal.value + totalShippingAndInsurance.value;
    return Math.min(checkoutOptions.value.voucher.amount, totalBeforeVoucher);
});

const grandTotal = computed(() => {
    let total = subtotal.value + totalShippingAndInsurance.value;

    if (form.value.use_voucher && checkoutOptions.value?.voucher?.amount) {
        total -= voucherDiscountAmount.value;
    }

    return Math.max(0, total);
});

const cartProducts = computed(() => {
    const result = [];
    for (const [id, qty] of Object.entries(cart.value)) {
        if (qty > 0) {
            const product = products.value.find((p) => p.id === Number(id));
            if (product) {
                const isPreorder = product.stock?.requires_preorder === true;
                result.push({ product, quantity: qty, isPreorder });
            }
        }
    }
    return result;
});

const isPreorderCart = computed(() => {
    return (
        cartProducts.value.length > 0 &&
        poProductCount.value === cartProducts.value.length
    );
});

const hasMixedStockAndPreorder = computed(() => {
    const hasReadyStock = cartProducts.value.some((item) => !item.isPreorder);
    const hasPreorder = cartProducts.value.some((item) => item.isPreorder);

    return hasReadyStock && hasPreorder;
});

const selectRate = (rate: ShippingRateResult) => {
    selectedRate.value = rate;
    shippingCost.value = rate.cost;
    form.value.shipping_cost = rate.cost;
    form.value.courier = { ...rate };
};

const calculateShipping = async () => {
    const method = form.value.shipping_method;

    if (method !== "courier_express" && method !== "courier_manual") {
        shippingCost.value = 0;
        form.value.shipping_cost = 0;
        availableRates.value = [];
        selectedRate.value = null;
        form.value.courier = null;
        return;
    }

    if (!checkoutOptions.value || !form.value.address_id) return;

    isLoadingRates.value = true;
    availableRates.value = [];
    selectedRate.value = null;
    shippingOrigin.value = null;
    form.value.courier = null;
    shippingCost.value = 0;
    form.value.shipping_cost = 0;

    try {
        const payload = {
            address_id: form.value.address_id,
            couriers: ["jne", "jnt", "sicepat"],
            items: cartProducts.value.map((item) => ({
                product_id: item.product.id,
                quantity: item.quantity,
            })),
        };

        const response = await shippingService.getExpressRates(payload);
        if (response.success && response.data?.results) {
            availableRates.value = response.data.results;
            shippingOrigin.value = response.data.origin ?? null;
            const recommendedMethod = response.data.shipping_method;
            if (
                recommendedMethod === "courier_express" ||
                recommendedMethod === "courier_manual"
            ) {
                form.value.shipping_method = recommendedMethod;
                checkoutOptions.value.shipping_methods = [
                    {
                        code: recommendedMethod,
                        name:
                            recommendedMethod === "courier_express"
                                ? "Kurir Ekspres"
                                : "Kurir",
                    },
                    ...checkoutOptions.value.shipping_methods.filter(
                        (method) => method.code === "pickup",
                    ),
                ];
            }
            if (availableRates.value.length > 0) {
                // Auto-select the first (cheapest) rate
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

const effectiveOriginName = computed(() => {
    return (
        shippingOrigin.value?.name ||
        checkoutOptions.value?.seller.origin?.name ||
        checkoutOptions.value?.seller.name ||
        "Penjual"
    );
});

const effectiveOriginAddress = computed(() => {
    const origin = shippingOrigin.value || checkoutOptions.value?.seller.origin;
    if (origin) {
        return formatLocation(origin);
    }
    return checkoutOptions.value?.seller.address || "-";
});

const goToCheckout = async () => {
    if (totalQuantity.value === 0) return;
    if (hasMixedStockAndPreorder.value) {
        snackbar.showMessage(
            "Pesanan tidak dapat mencampur produk tersedia dan produk PO. Pisahkan pesanan terlebih dahulu.",
            "error",
        );

        return;
    }

    isProceedingToCheckout.value = true;
    try {
        await loadCheckoutOptions();
        step.value = 2;
    } finally {
        isProceedingToCheckout.value = false;
    }
};

const handleBack = () => {
    if (step.value === 2) {
        step.value = 1;
    } else {
        router.back();
    }
};

const isFormValid = computed(() => {
    const spreadBankValid = hasSpreadPayment.value
        ? form.value.bank_spread_payment_id !== null
        : true;

    return (
        form.value.address_id !== null &&
        form.value.bank_company_id !== null &&
        form.value.shipping_method !== "" &&
        spreadBankValid
    );
});

const submitOrder = async () => {
    if (!isFormValid.value) return;
    isSubmitting.value = true;

    const selectedItems = [];
    for (const [id, qty] of Object.entries(cart.value)) {
        if (qty > 0) {
            selectedItems.push({ product_id: Number(id), quantity: qty });
        }
    }
    form.value.items = selectedItems;

    try {
        const { shipping_cost, bank_spread_payment_id, ...basePayload } =
            form.value;
        const payloadData: any = { ...basePayload };

        // Handle parameter bank berdasarkan level
        const buyerLevel =
            checkoutOptions.value?.buyer?.level?.toLowerCase() || "";
        if (buyerLevel !== "distributor") {
            payloadData.bank_account_id = payloadData.bank_company_id;
            delete payloadData.bank_company_id;
        }

        // Include spread payment bank if applicable
        if (hasSpreadPayment.value && bank_spread_payment_id) {
            payloadData.bank_spread_payment_id = bank_spread_payment_id;
        }

        const response = await purchaseService.createOrder(payloadData);
        snackbar.showMessage("Pesanan berhasil dibuat!", "success");
        if (response.data && response.data.id) {
            router.push(
                `/member/transactions/orders/payment?id=${encodeRouteId(response.data.id)}&success=1`,
            );
        } else {
            router.push("/member/transactions/orders");
        }
    } catch (e: any) {
        console.error("Submit order failed", e);
        snackbar.showMessage(
            e?.response?.data?.message ||
                "Terjadi kesalahan saat membuat pesanan.",
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
