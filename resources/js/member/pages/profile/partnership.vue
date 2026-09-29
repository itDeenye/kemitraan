<template>
    <div class="screen-body">
        <div class="card list-card">
            <div class="list-row">
                <div class="row-icon">
                    <v-icon icon="mdi-account-outline" size="16" />
                </div>
                <div class="row-main">
                    <strong>Kode mitra</strong>
                    <span>ID unik kemitraan</span>
                </div>
                <div
                    v-if="isLoading"
                    class="skeleton-text skeleton-stat w-16"
                ></div>
                <div
                    v-else
                    class="row-side"
                    style="color: var(--ink); font-weight: 600"
                >
                    {{ profileData?.member?.code || "-" }}
                </div>
            </div>
            <div class="list-row">
                <div class="row-icon">
                    <v-icon icon="mdi-account-network-outline" size="16" />
                </div>
                <div class="row-main">
                    <strong>Sponsor</strong>
                    <span>Mitra yang mereferensikan</span>
                </div>
                <div
                    v-if="isLoading"
                    class="skeleton-text skeleton-stat w-24"
                ></div>
                <div
                    v-else
                    class="row-side"
                    style="color: var(--ink); font-weight: 600"
                >
                    {{ profileData?.member?.parent?.name || "-" }}
                    <br />
                    {{ profileData?.member?.parent?.code || "-" }}
                </div>
            </div>
            <div class="list-row">
                <div class="row-icon">
                    <v-icon icon="mdi-gift-outline" size="16" />
                </div>
                <div class="row-main">
                    <strong>Level aktif</strong>
                    <span>Berlaku sejak terdaftar</span>
                </div>
                <div
                    v-if="isLoading"
                    class="skeleton-text skeleton-stat w-20"
                ></div>
                <div
                    v-else
                    class="row-side"
                    style="color: var(--ink); font-weight: 600"
                >
                    {{ profileData?.member?.level?.name || "-" }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import profileService from "@/member/services/profile.service";

const profileData = ref<any>(null);
const isLoading = ref(true);

const fetchProfile = async () => {
    try {
        isLoading.value = true;
        const res = await profileService.getProfile();
        if (res.success) {
            profileData.value = res.data;
        }
    } catch (error) {
        console.error("Error fetching profile:", error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchProfile();
});
</script>
