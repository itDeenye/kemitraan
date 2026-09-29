<template>
    <div class="bottom-nav">
        <router-link
            to="/member/dashboard"
            custom
            v-slot="{ isActive, navigate }"
        >
            <button
                class="nav-item"
                :class="{ active: isActive }"
                @click="navigate"
            >
                <v-icon icon="mdi-home-outline" size="20" />
                <span>Beranda</span>
            </button>
        </router-link>

        <router-link to="/member/stock" custom v-slot="{ isActive, navigate }">
            <button
                class="nav-item"
                :class="{ active: isActive }"
                @click="navigate"
            >
                <v-icon icon="mdi-cube-outline" size="20" />
                <span>Stok</span>
            </button>
        </router-link>

        <router-link
            to="/member/transactions"
            custom
            v-slot="{ isActive, navigate }"
        >
            <button
                class="nav-item"
                :class="{ active: isActive }"
                @click="navigate"
            >
                <v-icon icon="mdi-receipt-text-outline" size="20" />
                <span>Transaksi</span>
            </button>
        </router-link>

        <router-link
            to="/member/rewards"
            custom
            v-slot="{ isActive, navigate }"
        >
            <button
                class="nav-item"
                :class="{ active: isActive }"
                @click="navigate"
            >
                <v-icon icon="mdi-wallet-outline" size="20" />
                <span>Komisi</span>
            </button>
        </router-link>

        <router-link
            to="/member/profile"
            custom
            v-slot="{ isActive, navigate }"
        >
            <button
                class="nav-item"
                :class="{ active: isActive }"
                @click="navigate"
            >
                <v-icon icon="mdi-account-outline" size="20" />
                <span>Akun</span>
            </button>
        </router-link>
    </div>
</template>

<script setup lang="ts">
// Static bottom navigation for member mobile layout
</script>

<style scoped>
/*
 * Layout styles for .bottom-nav (.position, .grid, .height, .z-index)
 * are provided globally by member.css.
 * Only component-specific overrides belong here.
 */
.nav-item :deep(.v-btn__content) {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.nav-item :deep(.v-icon) {
    font-size: 20px;
}
</style>

