<template>
    <div class="screen-body">
        <div class="card" style="padding: 16px">
            <v-form ref="profileFormRef" @submit.prevent="saveProfile">
                <div class="d-flex flex-column align-center mb-6">
                    <MediaUpload
                        v-model="profileImage"
                        collection="profile"
                        :fallback-text="formProfile.name || 'Member'"
                        :size="112"
                        accept="image/jpeg,image/png,image/webp"
                        @success="saveProfilePhoto"
                    />
                    <span class="soft-label mt-2"
                        >Ketuk untuk mengganti foto</span
                    >
                </div>

                <v-text-field
                    v-model="formProfile.name"
                    label="Nama Lengkap *"
                    variant="outlined"
                    density="comfortable"
                    :rules="[(v) => !!v || 'Nama wajib diisi']"
                    class="mb-3"
                />
                <v-text-field
                    v-model="formProfile.email"
                    label="Email (Opsional)"
                    type="email"
                    variant="outlined"
                    density="comfortable"
                    class="mb-3"
                />
                <v-text-field
                    v-model="formProfile.phone"
                    label="No. Telepon (Opsional)"
                    variant="outlined"
                    density="comfortable"
                    class="mb-3"
                />
                <v-select
                    v-model="formProfile.gender"
                    label="Jenis Kelamin (Opsional)"
                    variant="outlined"
                    density="comfortable"
                    :items="['Laki-laki', 'Perempuan']"
                    class="mb-3"
                />
                <v-text-field
                    v-model="formProfile.birth_date"
                    label="Tanggal Lahir (Opsional)"
                    type="date"
                    variant="outlined"
                    density="comfortable"
                    class="mb-3"
                />
                <v-select
                    v-model="formProfile.identity_type"
                    label="Jenis Identitas (Opsional)"
                    variant="outlined"
                    density="comfortable"
                    :items="['KTP', 'SIM', 'PASPOR']"
                    class="mb-3"
                />
                <v-text-field
                    v-model="formProfile.identity_no"
                    label="Nomor Identitas (Opsional)"
                    variant="outlined"
                    density="comfortable"
                    :rules="[
                        (v) =>
                            !v ||
                            v.length <= 20 ||
                            'Nomor identitas maksimal 20 karakter',
                    ]"
                    class="mb-3"
                />
                <v-text-field
                    v-model="formProfile.nib"
                    label="NIB (Opsional)"
                    variant="outlined"
                    density="comfortable"
                    class="mb-3"
                />

                <div class="section-heading mt-2 mb-3">
                    <h3>Media Sosial</h3>
                </div>
                <v-text-field
                    v-model="formProfile.instagram"
                    label="Instagram (Opsional)"
                    prepend-inner-icon="mdi-instagram"
                    variant="outlined"
                    density="comfortable"
                    placeholder="Username atau tautan Instagram"
                    class="mb-3"
                />
                <v-text-field
                    v-model="formProfile.facebook"
                    label="Facebook (Opsional)"
                    prepend-inner-icon="mdi-facebook"
                    variant="outlined"
                    density="comfortable"
                    placeholder="Nama atau tautan Facebook"
                    class="mb-3"
                />
                <v-text-field
                    v-model="formProfile.tiktok"
                    label="TikTok (Opsional)"
                    prepend-inner-icon="mdi-music-note"
                    variant="outlined"
                    density="comfortable"
                    placeholder="Username atau tautan TikTok"
                    class="mb-4"
                />

                <button
                    class="primary-button block"
                    type="submit"
                    :disabled="loadingProfile"
                >
                    <template v-if="loadingProfile">Menyimpan...</template>
                    <template v-else>Simpan Perubahan</template>
                </button>
            </v-form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useAuthStore } from "@/shared/stores/auth";
import profileService from "@/member/services/profile.service";
import MediaUpload from "@/shared/components/MediaUpload.vue";

const router = useRouter();
const snackbar = useSnackbarStore();
const authStore = useAuthStore();

const profileFormRef = ref<any>(null);
const loadingProfile = ref(false);
const profileImage = ref("");

const formProfile = reactive({
    name: "",
    email: "",
    phone: "",
    gender: "",
    birth_date: "",
    identity_type: "KTP",
    identity_no: "",
    nib: "",
    instagram: "",
    facebook: "",
    tiktok: "",
});

async function loadProfile() {
    try {
        const response = await profileService.getProfile();
        if (response.success && response.data.member) {
            const member = response.data.member;
            formProfile.name = member.name || "";
            profileImage.value = member.image || "";
            formProfile.email = member.email || "";
            formProfile.phone = member.mobile_phone || "";
            formProfile.gender = member.gender || "";
            formProfile.birth_date = member.birth_date || "";
            formProfile.identity_type = member.identity?.type || "KTP";
            formProfile.identity_no = member.identity?.number || "";
            formProfile.nib = member.nib || "";
            formProfile.instagram = member.social_media?.instagram || "";
            formProfile.facebook = member.social_media?.facebook || "";
            formProfile.tiktok = member.social_media?.tiktok || "";
        }
    } catch (error) {
        console.error("Failed to load profile:", error);
    }
}

async function saveProfilePhoto(imageUrl: string) {
    try {
        const response = await profileService.updateProfilePhoto(imageUrl);
        if (response.success) {
            profileImage.value = response.data.member.image || imageUrl;
            await authStore.fetchUser();
            snackbar.showMessage("Foto profil berhasil diperbarui", "success");
        }
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message ||
                "Foto profil belum dapat diperbarui.",
            "error",
        );
    }
}

async function saveProfile() {
    const { valid } = await profileFormRef.value?.validate();
    if (!valid) return;

    loadingProfile.value = true;
    try {
        const response = await profileService.updateProfile(formProfile);
        if (response.success) {
            await authStore.fetchUser();
            snackbar.showMessage("Profil berhasil diperbarui");
            router.push("/member/profile");
        } else {
            snackbar.showMessage(
                response.message || "Gagal memperbarui profil",
                "error",
            );
        }
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message ||
                "Profil belum dapat diperbarui. Silakan coba lagi.",
            "error",
        );
    } finally {
        loadingProfile.value = false;
    }
}

onMounted(() => {
    loadProfile();
});
</script>
