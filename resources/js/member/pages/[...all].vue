<template>
    <div class="d-flex flex-column align-center justify-center h-100 py-12">
        <v-icon icon="mdi-alert-circle-outline" size="100" color="grey-lighten-1" class="mb-4" />
        <h1 class="text-h3 font-weight-bold mb-2">404</h1>
        <h2 class="text-h6 text-medium-emphasis mb-6">Halaman Tidak Ditemukan</h2>
        <p class="text-body-1 text-center mb-8" style="max-width: 500px">
            Maaf, halaman atau menu yang Anda tuju belum tersedia atau mungkin sedang dalam tahap pengembangan (Under Construction).
        </p>
        <v-btn color="primary" prepend-icon="mdi-arrow-left" to="/member">
            Kembali ke Dashboard
        </v-btn>
    </div>
</template>

<script setup lang="ts">
// 404 Not Found Page Component
</script>
