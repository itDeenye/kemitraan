<template>
    <div class="screen-body">
        <div class="welcome-row">
            <h2>Semua Menu</h2>
            <span class="badge" v-if="authStore.user?.member?.level?.name">{{
                authStore.user?.member?.level?.name
            }}</span>
        </div>

        <div class="menu-group-title">Manajemen Kemitraan</div>
        <div class="card menu-list">
            <div
                class="list-row flow-link"
                @click="router.push('/member/profile/partnership')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-account-star-outline" size="20" />
                </div>
                <div class="row-main">
                    <strong>Kemitraan</strong
                    ><span>Data level dan status kemitraan</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
            <div
                v-if="isDistributorOrAgent"
                class="list-row flow-link"
                @click="router.push('/member/network')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-account-network-outline" size="20" />
                </div>
                <div class="row-main">
                    <strong>Jaringan Mitra</strong
                    ><span>Downline Agent dan Reseller bertingkat</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
        </div>

        <div class="menu-group-title">Transaksi</div>
        <div class="card menu-list">
            <div
                class="list-row flow-link"
                @click="router.push('/member/transactions/orders')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-cart-outline" size="20" />
                </div>
                <div class="row-main">
                    <strong>Pesanan Pembelian</strong
                    ><span>Buat dan pantau pembelian</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
            <div
                class="list-row flow-link"
                @click="router.push('/member/transactions/goods-receipts')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-package-variant-closed" size="20" />
                </div>
                <div class="row-main">
                    <strong>Penerimaan Barang</strong
                    ><span>Daftar berdasarkan kode penerimaan</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
            <div
                v-if="isDistributorOrAgent"
                class="list-row flow-link"
                @click="router.push('/member/transactions/sales')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-storefront-outline" size="20" />
                </div>
                <div class="row-main">
                    <strong>Pesanan Penjualan</strong
                    ><span>Verifikasi bayar dan pengiriman</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
            <div
                class="list-row flow-link"
                @click="router.push('/member/transactions/sales/create')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-receipt-text-outline" size="20" />
                </div>
                <div class="row-main">
                    <strong>POS</strong><span>Penjualan kepada customer</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
        </div>

        <div class="menu-group-title">Stok</div>
        <div class="card menu-list">
            <div
                class="list-row flow-link"
                @click="router.push('/member/stock')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-package-variant" size="20" />
                </div>
                <div class="row-main">
                    <strong>Daftar Stok</strong
                    ><span>Stok terkini per produk</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
            <div
                class="list-row flow-link"
                @click="router.push('/member/stock/mutation')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-swap-horizontal" size="20" />
                </div>
                <div class="row-main">
                    <strong>Mutasi Stok</strong
                    ><span>Riwayat stok masuk dan keluar</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
            <div
                v-if="isAgentOrReseller"
                class="list-row flow-link"
                @click="router.push('/member/stock/adjustment')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-pencil-outline" size="20" />
                </div>
                <div class="row-main">
                    <strong>Penyesuaian Stok</strong
                    ><span>Pengurangan stok dengan alasan</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
            <div
                v-if="isDistributor"
                class="list-row flow-link"
                @click="router.push('/member/stock/returns')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-keyboard-return" size="20" />
                </div>
                <div class="row-main">
                    <strong>Retur Barang</strong
                    ><span>Retur berdasarkan penerimaan</span>
                </div>
                <div class="row-side return-row-action">
                    <span v-if="returnActionCount > 0" class="badge red">
                        {{ returnActionCount > 99 ? "99+" : returnActionCount }}
                    </span>
                    &rsaquo;
                </div>
            </div>
        </div>

        <div class="menu-group-title">Komisi &amp; Reward</div>
        <div class="card menu-list">
            <div
                class="list-row flow-link"
                @click="router.push('/member/rewards/monthly')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-wallet-giftcard" size="20" />
                </div>
                <div class="row-main">
                    <strong>Reward Bulanan</strong
                    ><span>Poin dan jumlah bonus</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
            <div
                class="list-row flow-link"
                @click="router.push('/member/rewards/annual')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-gift-outline" size="20" />
                </div>
                <div class="row-main">
                    <strong>Reward Tahunan</strong
                    ><span>Akumulasi poin tahunan</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
            <div
                v-if="isDistributorOrAgent"
                class="list-row flow-link"
                @click="router.push('/member/rewards/stockist')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-store-outline" size="20" />
                </div>
                <div class="row-main">
                    <strong>Reward Stokis</strong
                    ><span>Pembelanjaan dan voucher</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
            <div
                v-if="isDistributorOrAgent"
                class="list-row flow-link"
                @click="router.push('/member/rewards/sharing')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-account-group-outline" size="20" />
                </div>
                <div class="row-main">
                    <strong>Sharing Profit</strong
                    ><span>Komisi per transaksi jaringan</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
        </div>

        <div class="menu-group-title">Akun</div>
        <div class="card menu-list">
            <div
                class="list-row flow-link"
                @click="router.push('/member/profile')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-account-outline" size="20" />
                </div>
                <div class="row-main">
                    <strong>Profil</strong
                    ><span>Informasi akun dan kemitraan</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
            <div
                class="list-row flow-link"
                @click="router.push('/member/profile/address')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-truck-outline" size="20" />
                </div>
                <div class="row-main">
                    <strong>Alamat</strong
                    ><span>Alamat pengiriman tersimpan</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
            <div
                class="list-row flow-link"
                @click="router.push('/member/profile/bank')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-bank-outline" size="20" />
                </div>
                <div class="row-main">
                    <strong>Rekening</strong
                    ><span>Rekening pencairan komisi</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
            <div
                class="list-row flow-link"
                @click="router.push('/member/profile/security')"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-lock-outline" size="20" />
                </div>
                <div class="row-main">
                    <strong>Keamanan Akun</strong
                    ><span>Ubah kata sandi dan perangkat</span>
                </div>
                <div class="row-side">&rsaquo;</div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/shared/stores/auth";
import returnService from "@/member/services/return.service";

const router = useRouter();
const authStore = useAuthStore();
const returnActionCount = ref(0);

const isDistributor = computed(() => {
    return authStore.user?.member?.level?.code === "DST";
});

const isDistributorOrAgent = computed(() => {
    return ["DST", "AGT"].includes(
        authStore.user?.member?.level?.code || "",
    );
});

const isAgentOrReseller = computed(() => {
    return ["AGT", "RSL"].includes(
        authStore.user?.member?.level?.code || "",
    );
});

onMounted(async () => {
    if (!isDistributor.value) return;

    try {
        const summary = await returnService.getActionSummary();
        returnActionCount.value =
            Number(summary.eligible || 0) + Number(summary.action_required || 0);
    } catch (error) {
        console.error("Failed to load return action count:", error);
    }
});
</script>

<style scoped>
.return-row-action {
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>
