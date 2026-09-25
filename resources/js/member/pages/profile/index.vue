<template>
    <div class="screen-body">
        <div class="card profile-card">
            <v-avatar
                color="primary"
                size="66"
                class="mx-auto mb-3 text-white font-weight-bold text-h5"
            >
                <img
                    v-if="profileData?.member?.image"
                    :src="profileData.member.image"
                    :alt="profileData.member.name"
                    style="width: 100%; height: 100%; object-fit: cover"
                />
                <span v-else>{{
                    profileData?.member?.name?.charAt(0)?.toUpperCase() || "U"
                }}</span>
            </v-avatar>
            <h2>{{ profileData?.member?.name || "Memuat..." }}</h2>
            <p>
                {{ profileData?.member?.email }} ·
                {{ profileData?.member?.code }}
            </p>
            <span class="badge gold">{{
                profileData?.member?.level?.name || "Mitra"
            }}</span>
            <button
                class="secondary-button block"
                style="width: 100%; margin-top: 12px"
                @click="router.push('/member/profile/edit')"
            >
                <v-icon icon="mdi-pencil-outline" size="14" />
                Edit Profil
            </button>
        </div>

        <div class="menu-group-title">Pengaturan akun</div>
        <div class="card menu-list">
            <div
                class="flow-link"
                @click="router.push('/member/profile/address')"
            >
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-truck-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Alamat</strong>
                        <span>Kelola alamat pengiriman</span>
                    </div>
                    <div class="row-side chevron">›</div>
                </div>
            </div>
            <div class="flow-link" @click="router.push('/member/profile/bank')">
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-bank-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Rekening</strong>
                        <span>Kelola rekening pencairan</span>
                    </div>
                    <div class="row-side chevron">›</div>
                </div>
            </div>
        </div>

        <div class="menu-group-title">Manajemen Kemitraan</div>
        <div class="card menu-list">
            <div
                class="flow-link"
                @click="router.push('/member/profile/partnership')"
            >
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-account-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Kemitraan</strong>
                        <span>Kode, level, dan status mitra</span>
                    </div>
                    <div class="row-side chevron">›</div>
                </div>
            </div>
            <div
                v-if="isDistributorOrAgent"
                class="flow-link"
                @click="router.push('/member/network')"
            >
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-account-network-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Jaringan Mitra</strong>
                        <span>Downline dan pendaftaran mitra</span>
                    </div>
                    <div class="row-side chevron">›</div>
                </div>
            </div>
        </div>

        <div class="menu-group-title">Lainnya</div>
        <div class="card menu-list">
            <div
                class="flow-link"
                @click="router.push('/member/profile/security')"
            >
                <div class="list-row">
                    <div class="row-icon">
                        <v-icon icon="mdi-lock-outline" size="16" />
                    </div>
                    <div class="row-main">
                        <strong>Keamanan Akun</strong>
                        <span>Ubah kata sandi</span>
                    </div>
                    <div class="row-side chevron">›</div>
                </div>
            </div>
            <div class="flow-link" @click="handleLogout">
                <div class="list-row">
                    <div class="row-icon logout-icon">
                        <v-icon icon="mdi-logout" size="16" />
                    </div>
                    <div class="row-main">
                        <strong class="logout-text">Keluar</strong>
                        <span>Keluar dari aplikasi DNY</span>
                    </div>
                    <div class="row-side chevron">›</div>
                </div>
            </div>
        </div>

        <MobileConfirm ref="confirmDialogRef" />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/shared/stores/auth";
import profileService from "@/member/services/profile.service";
import type { MemberProfile } from "@/member/types/profile";
import MobileConfirm from "@/member/components/MobileConfirm.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const router = useRouter();
const authStore = useAuthStore();
const snackbar = useSnackbarStore();
const profileData = ref<MemberProfile | null>(null);
const confirmDialogRef = ref<InstanceType<typeof MobileConfirm> | null>(null);

const isDistributorOrAgent = computed(() => {
    const roleId = authStore.user?.role?.id;
    return roleId === 1 || roleId === 2;
});

async function fetchProfile() {
    try {
        const response = await profileService.getProfile();
        if (response.success) {
            profileData.value = response.data;
        }
    } catch (error) {
        console.error("Failed to load profile:", error);
    }
}

async function handleLogout() {
    if (!confirmDialogRef.value) return;

    const isConfirmed = await confirmDialogRef.value.open({
        title: "Keluar Aplikasi",
        message: "Apakah Anda yakin ingin keluar dari aplikasi DNY?",
        confirmText: "Ya, Keluar",
        confirmColor: "error",
        icon: "mdi-logout",
    });

    if (isConfirmed) {
        confirmDialogRef.value.isLoading = true;
        try {
            await authStore.logout(false);
            snackbar.showMessage("Anda berhasil keluar dari sistem.");
            router.push("/member/login");
        } catch (error) {
            console.error("Logout failed:", error);
        } finally {
            confirmDialogRef.value.isLoading = false;
        }
    }
}

onMounted(() => {
    fetchProfile();
});
</script>
