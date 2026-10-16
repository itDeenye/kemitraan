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
                        @uploading="mediaUploads.profile = $event"
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
                    :items="identityOptions"
                    class="mb-3"
                />
                <v-text-field
                    v-model="formProfile.identity_no"
                    label="Nomor Identitas (Opsional)"
                    :maxlength="identitySettings.maxLength"
                    :hint="identitySettings.hint"
                    persistent-hint
                    :inputmode="formProfile.identity_type === 'PASPOR' ? 'text' : 'numeric'"
                    variant="outlined"
                    density="comfortable"
                    :rules="[validateIdentityNumber]"
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
                    <h3>Dokumen Identitas</h3>
                </div>
                <div class="d-flex align-center ga-3 mb-4">
                    <MediaUpload
                        v-model="identityImage"
                        collection="general"
                        :size="92"
                        :rounded="false"
                        fallback-icon="mdi-card-account-details-outline"
                        accept="image/jpeg,image/png,image/webp"
                        @success="identityImageChanged = true"
                        @uploading="mediaUploads.identity = $event"
                    />
                    <div>
                        <div class="text-body-2">Ketuk untuk mengganti dokumen</div>
                        <div class="text-caption text-medium-emphasis">
                            JPG, PNG, atau WEBP
                        </div>
                    </div>
                </div>

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
                    :disabled="loadingProfile || isUploadingMedia"
                >
                    <template v-if="loadingProfile">Menyimpan...</template>
                    <template v-else>Simpan Perubahan</template>
                </button>
            </v-form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useAuthStore } from "@/shared/stores/auth";
import profileService from "@/member/services/profile.service";
import MediaUpload from "@/shared/components/MediaUpload.vue";
import { identityNumberRule, identityNumberSettings, memberIdentityOptions } from "@/shared/utils/member-identity";

const router = useRouter();
const snackbar = useSnackbarStore();
const authStore = useAuthStore();

const profileFormRef = ref<any>(null);
const loadingProfile = ref(false);
const profileImage = ref("");
const identityImage = ref("");
const identityImageChanged = ref(false);
const mediaUploads = reactive({ profile: false, identity: false });
const savingProfilePhoto = ref(false);
const isUploadingMedia = computed(() =>
    savingProfilePhoto.value || Object.values(mediaUploads).some(Boolean),
);
const originalIdentity = ref({ type: "KTP", number: "" });

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
const identityOptions = memberIdentityOptions();
const identitySettings = computed(() => identityNumberSettings(formProfile.identity_type));
const validateIdentityNumber = (value: unknown) => identityNumberRule(formProfile.identity_type, value, originalIdentity.value);

async function loadProfile() {
    try {
        const response = await profileService.getProfile();
        if (response.success && response.data.member) {
            const member = response.data.member;
            formProfile.name = member.name || "";
            profileImage.value = member.image || "";
            identityImage.value = member.identity?.image || "";
            identityImageChanged.value = false;
            formProfile.email = member.email || "";
            formProfile.phone = member.mobile_phone || "";
            formProfile.gender = member.gender || "";
            formProfile.birth_date = member.birth_date || "";
            formProfile.identity_type = member.identity?.type || "KTP";
            formProfile.identity_no = member.identity?.number || "";
            originalIdentity.value = { type: formProfile.identity_type, number: formProfile.identity_no };
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
    savingProfilePhoto.value = true;
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
    } finally {
        savingProfilePhoto.value = false;
    }
}

async function saveProfile() {
    if (isUploadingMedia.value) {
        snackbar.showMessage("Tunggu hingga proses upload selesai.", "info");
        return;
    }
    const { valid } = await profileFormRef.value?.validate();
    if (!valid) return;

    loadingProfile.value = true;
    try {
        const response = await profileService.updateProfile({
            ...formProfile,
            ...(identityImageChanged.value && identityImage.value
                ? { identity_image_url: identityImage.value }
                : {}),
        });
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
