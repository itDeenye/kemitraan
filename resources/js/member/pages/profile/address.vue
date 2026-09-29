<template>
    <div class="screen-body">
        <button
            class="primary-button block"
            style="margin-bottom: 16px"
            @click="router.push('/member/profile/address-form')"
        >
            Tambah Alamat
        </button>

        <div class="welcome-row items-end justify-end">
            <span class="badge">{{ addresses.length }} Alamat</span>
        </div>

        <div class="card list-card" v-if="isLoading">
            <div style="padding: 24px; text-align: center">
                <v-progress-circular indeterminate color="primary" size="24" />
                <p
                    style="
                        margin-top: 8px;
                        margin-bottom: 0;
                        color: var(--muted);
                        font-size: 13px;
                    "
                >
                    Memuat data...
                </p>
            </div>
        </div>

        <div class="card list-card" v-else-if="addresses.length > 0">
            <div
                v-for="address in addresses"
                :key="address.id"
                class="list-row"
            >
                <div class="row-icon">
                    <v-icon icon="mdi-home-outline" size="20" />
                </div>
                <div
                    class="row-main flex flex-col justify-center overflow-hidden"
                >
                    <div class="flex items-center gap-2 mb-1">
                        <p class="text-md font-semibold">
                            {{ address.label }}
                        </p>
                        <v-chip
                            v-if="address.is_default"
                            color="primary"
                            size="x-small"
                            variant="flat"
                            >Utama</v-chip
                        >
                    </div>
                    <div class="d-flex text-xs truncate mb-1">
                        <p class="font-semibold">
                            {{ address.recipient }}
                        </p>
                        <p class="mx-1 font-semibold">|</p>
                        <p>{{ address.phone }}</p>
                    </div>
                    <div class="text-xs text-gray-500 truncate">
                        {{ address.full_address }}
                    </div>
                    <div class="text-xs text-gray-500 truncate mt-1">
                        {{ formatRegion(address) }}
                    </div>
                </div>
                <div class="row-side flex items-center gap-2">
                    <v-btn
                        v-if="!address.is_default"
                        variant="outlined"
                        color="warning"
                        icon="mdi-star-outline"
                        size="small"
                        density="comfortable"
                        title="Jadikan alamat utama"
                        @click="setDefaultAddress(address.id)"
                    />
                    <v-btn
                        variant="outlined"
                        color="info"
                        icon="mdi-pencil-outline"
                        size="small"
                        density="comfortable"
                        @click="
                            router.push(
                                `/member/profile/address-form?id=${encodeRouteId(address.id)}`,
                            )
                        "
                    />
                    <v-btn
                        variant="outlined"
                        color="error"
                        icon="mdi-delete-outline"
                        size="small"
                        density="comfortable"
                        @click="confirmDeleteAddress(address.id)"
                    />
                </div>
            </div>
        </div>
        <div
            v-else
            class="card list-card"
            style="padding: 24px; text-align: center"
        >
            <v-icon
                icon="mdi-map-marker-off-outline"
                size="32"
                color="grey"
                class="mb-2"
            />
            <p style="margin: 0; color: var(--muted); font-size: 13px">
                Belum ada alamat tersimpan
            </p>
        </div>

        <BaseConfirm ref="confirmDialogRef" />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import profileService from "@/member/services/profile.service";
import type { MemberAddress } from "@/member/types/profile";
import BaseConfirm from "@/shared/components/BaseConfirm.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { encodeRouteId } from "@/shared/utils/route-id";

const snackbar = useSnackbarStore();
const router = useRouter();
const addresses = ref<MemberAddress[]>([]);
const confirmDialogRef = ref<InstanceType<typeof BaseConfirm> | null>(null);
const isLoading = ref(true);

async function fetchAddresses() {
    isLoading.value = true;
    try {
        const response = await profileService.getAddresses();
        if (response.success) {
            addresses.value = response.data;
        }
    } catch (error) {
        console.error("Failed to load addresses:", error);
    } finally {
        isLoading.value = false;
    }
}

function formatRegion(address: MemberAddress) {
    return [
        address.region.subdistrict_name,
        address.region.district_name,
        address.region.city_name,
        address.region.province_name,
        address.region.postal_code,
    ]
        .filter(Boolean)
        .join(", ");
}

async function setDefaultAddress(id: number) {
    try {
        const response = await profileService.setDefaultAddress(id);
        if (response.success) {
            snackbar.showMessage("Alamat utama berhasil diperbarui", "success");
            await fetchAddresses();
        }
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message ||
                "Alamat utama belum dapat diperbarui.",
            "error",
        );
    }
}

async function confirmDeleteAddress(id: number) {
    if (!confirmDialogRef.value) return;

    const isConfirmed = await confirmDialogRef.value.open({
        title: "Hapus Alamat",
        message: "Apakah Anda yakin ingin menghapus alamat ini?",
        confirmText: "Ya, Hapus",
        confirmColor: "error",
        icon: "mdi-delete-outline",
    });

    if (isConfirmed) {
        confirmDialogRef.value.isLoading = true;
        try {
            const response = await profileService.deleteAddress(id);
            if (response.success) {
                snackbar.showMessage("Alamat berhasil dihapus", "success");
                await fetchAddresses();
            } else {
                snackbar.showMessage("Alamat belum dapat dihapus.", "error");
            }
        } catch (error: any) {
            snackbar.showMessage(
                error.response?.data?.message || "Alamat belum dapat dihapus.",
                "error",
            );
        } finally {
            confirmDialogRef.value.isLoading = false;
        }
    }
}

onMounted(() => {
    fetchAddresses();
});
</script>
