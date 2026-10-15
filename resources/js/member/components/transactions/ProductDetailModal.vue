<template>
    <v-dialog
        v-model="isOpen"
        max-width="500"
        content-class="detail-modal-mobile"
    >
        <div
            class="card"
            style="
                padding: 0;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                max-height: 90vh;
                background: #fff;
            "
        >
            <!-- Header -->
            <div
                style="
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 16px 20px;
                    border-bottom: 1px solid #eaeaea;
                "
            >
                <h3
                    style="
                        margin: 0;
                        font-size: 16px;
                        font-weight: 600;
                        color: #333;
                    "
                >
                    Detail Produk
                </h3>
                <v-btn
                    icon
                    variant="text"
                    size="small"
                    @click="close"
                    style="margin: -8px; color: #666"
                >
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </div>

            <!-- Content -->
            <div style="padding: 20px; overflow-y: auto; flex: 1">
                <div
                    v-if="isLoading"
                    style="
                        display: flex;
                        justify-content: center;
                        padding: 40px 0;
                    "
                >
                    <v-progress-circular
                        indeterminate
                        color="var(--wine)"
                    ></v-progress-circular>
                </div>

                <div v-else-if="product">
                    <div
                        class="product-art"
                        style="
                            border: 1px solid #eaeaea;
                            background: #fff;
                            width: 100%;
                            aspect-ratio: 1;
                            border-radius: 8px;
                            margin-bottom: 16px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            overflow: hidden;
                        "
                    >
                        <v-img
                            v-if="
                                product.image &&
                                !product.image.includes('default')
                            "
                            :src="product.image"
                            aspect-ratio="1"
                            style="width: 100%; height: 100%"
                        >
                            <template #error>
                                <img
                                    src="/images/default-product.svg"
                                    alt="Default Product"
                                    style="
                                        width: 100%;
                                        height: 100%;
                                        object-fit: contain;
                                    "
                                />
                            </template>
                        </v-img>
                        <img
                            v-else
                            src="/images/default-product.svg"
                            alt="Default Product"
                            style="width: 100%; height: 100%; object-fit: contain"
                        />
                    </div>

                    <h4
                        style="
                            font-size: 16px;
                            font-weight: 600;
                            color: #333;
                            margin-bottom: 4px;
                        "
                    >
                        {{ product.name }}
                    </h4>
                    <p
                        style="
                            font-size: 13px;
                            color: #666;
                            margin-bottom: 12px;
                            font-family: monospace;
                        "
                    >
                        {{ product.code }}
                    </p>

                    <p
                        style="
                            font-size: 13px;
                            color: #666;
                            margin-bottom: 8px;
                            font-family: monospace;
                        "
                    >
                        BPOM: {{ product.bpom_number || "-" }}
                    </p>
                    <div
                        v-if="
                            product.member_prices &&
                            product.member_prices.length
                        "
                        style="
                            font-size: 12px;
                            color: #666;
                            margin-bottom: 12px;
                            line-height: 1.4;
                        "
                    >
                        <div
                            v-for="mp in product.member_prices"
                            :key="mp.id ?? mp.member_level_id"
                        >
                            Harga {{ mp.level || mp.member_level_name }}: Rp{{
                                formatPrice(mp.price)
                            }}
                        </div>
                    </div>

                    <div
                        style="
                            display: flex;
                            align-items: center;
                            gap: 8px;
                            margin-bottom: 16px;
                        "
                    >
                        <span
                            style="
                                font-size: 18px;
                                font-weight: 700;
                                color: var(--wine);
                            "
                            >Rp{{ formatPrice(product.price) }}</span
                        >
                        <span
                            :class="{
                                'available-label':
                                    product.stock?.status?.code === 'available' ||
                                    (!product.stock?.status &&
                                        product.stock?.available > 0),
                                'low-stock-label':
                                    product.stock?.status?.code === 'low_stock',
                                'po-label':
                                    product.stock?.status?.code === 'preorder',
                                'out-of-stock-label':
                                    product.stock?.status?.code ===
                                        'out_of_stock' ||
                                    (!product.stock?.status &&
                                        product.stock?.available <= 0) ||
                                    (product.stock?.available <= 0 &&
                                        product.stock?.status?.code !==
                                            'preorder'),
                            }"
                        >
                            {{
                                product.stock?.status?.label ||
                                (product.stock?.available > 0
                                    ? "Tersedia"
                                    : "Habis")
                            }}
                        </span>
                        <span
                            v-if="product.is_package !== undefined"
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

                    <div
                        style="
                            border-top: 1px solid #eaeaea;
                            padding-top: 16px;
                            margin-top: 16px;
                        "
                    >
                        <h5
                            style="
                                font-size: 14px;
                                font-weight: 600;
                                margin-bottom: 8px;
                                color: #333;
                            "
                        >
                            Deskripsi
                        </h5>
                        <p
                            style="
                                font-size: 14px;
                                color: #555;
                                line-height: 1.5;
                                white-space: pre-line;
                                margin: 0;
                            "
                        >
                            {{ product.description || "Tidak ada deskripsi." }}
                        </p>
                    </div>

                    <div
                        style="
                            border-top: 1px solid #eaeaea;
                            padding-top: 16px;
                            margin-top: 16px;
                        "
                    >
                        <h5
                            style="
                                font-size: 14px;
                                font-weight: 600;
                                margin-bottom: 8px;
                                color: #333;
                            "
                        >
                            Informasi Tambahan
                        </h5>
                        <table style="width: 100%; font-size: 13px">
                            <tbody>
                                <tr v-if="product.category">
                                    <td
                                        style="
                                            padding: 6px 0;
                                            color: #666;
                                            width: 40%;
                                        "
                                    >
                                        Kategori
                                    </td>
                                    <td
                                        style="
                                            padding: 6px 0;
                                            font-weight: 500;
                                            color: #333;
                                        "
                                    >
                                        {{ product.category.name }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; color: #666">
                                        Berat
                                    </td>
                                    <td
                                        style="
                                            padding: 6px 0;
                                            font-weight: 500;
                                            color: #333;
                                        "
                                    >
                                        {{ product.weight_grams }} gram
                                    </td>
                                </tr>
                                <tr v-if="product.dimensions_cm">
                                    <td style="padding: 6px 0; color: #666">
                                        Dimensi
                                    </td>
                                    <td
                                        style="
                                            padding: 6px 0;
                                            font-weight: 500;
                                            color: #333;
                                        "
                                    >
                                        {{ product.dimensions_cm.length }} x
                                        {{ product.dimensions_cm.width }} x
                                        {{ product.dimensions_cm.height }} cm
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; color: #666">
                                        Satuan
                                    </td>
                                    <td
                                        style="
                                            padding: 6px 0;
                                            font-weight: 500;
                                            color: #333;
                                        "
                                    >
                                        {{ product.unit }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; color: #666">Poin Bulanan</td>
                                    <td style="padding: 6px 0; font-weight: 500; color: #333">
                                        {{ formatPrice(product.monthly_points ?? 1) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; color: #666">Poin Tahunan</td>
                                    <td style="padding: 6px 0; font-weight: 500; color: #333">
                                        {{ formatPrice(product.annual_points ?? 1) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                style="
                    padding: 12px 20px;
                    border-top: 1px solid #eaeaea;
                    display: flex;
                    justify-content: flex-end;
                    background: #fff;
                "
            >
                <v-btn
                    variant="tonal"
                    color="var(--wine)"
                    @click="close"
                    style="font-weight: 600; text-transform: none; border-radius: 8px"
                >
                    Tutup
                </v-btn>
            </div>
        </div>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import purchaseService from "@/member/services/purchase.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatPrice } = useFormatter();
const snackbar = useSnackbarStore();

const isOpen = ref(false);
const isLoading = ref(false);
const product = ref<any>(null);

const open = async (productId: number) => {
    isOpen.value = true;
    isLoading.value = true;
    product.value = null;

    try {
        const response =
            await purchaseService.getCatalogProductDetail(productId);
        if (response.success) {
            product.value = response.data;
        }
    } catch (e: any) {
        snackbar.showMessage("Gagal memuat detail produk.", "error");
        isOpen.value = false;
    } finally {
        isLoading.value = false;
    }
};

const close = () => {
    isOpen.value = false;
};

defineExpose({
    open,
});
</script>
