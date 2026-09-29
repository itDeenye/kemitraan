<template>
    <div class="screen-body">
        <button
            class="primary-button block"
            style="margin-bottom: 16px"
            @click="router.push('/member/profile/bank-form')"
        >
            Tambah Rekening
        </button>

        <div class="welcome-row items-end justify-end">
            <span class="badge">{{ banks.length }} Rekening</span>
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

        <div class="card list-card" v-else-if="banks.length > 0">
            <div v-for="bank in banks" :key="bank.id" class="list-row">
                <div class="row-icon">
                    <v-icon icon="mdi-bank-outline" size="20" />
                </div>
                <div
                    class="row-main flex flex-col justify-center overflow-hidden"
                >
                    <div class="d-flex align-center ga-1 flex-wrap mb-2">
                        <p class="text-md font-semibold mr-1">
                            {{ bank.bank_name || bank.bank_code || "Bank" }}
                        </p>
                        <BaseBadge
                            v-if="bank.is_default"
                            chip-class="!h-auto !py-[2px] !px-2 !text-[10px]"
                            text="Utama"
                            color="primary"
                            variant="flat"
                            inline
                        />
                        <BaseBadge
                            chip-class="!h-auto !py-[2px] !px-2 !text-[10px]"
                            type="active"
                            :value="bank.is_active"
                            inline
                        />
                    </div>
                    <div class="d-flex text-xs truncate mb-1">
                        <p class="font-semibold">
                            {{ bank.account_name }}
                        </p>
                        <p class="mx-1 font-semibold">|</p>
                        <p>{{ bank.account_number }}</p>
                    </div>
                    <p
                        v-if="bank.city || bank.branch"
                        class="text-xs text-gray-500 truncate"
                    >
                        {{
                            [bank.branch, bank.city].filter(Boolean).join(", ")
                        }}
                    </p>
                </div>
                <div class="row-side flex items-center gap-2">
                    <v-btn
                        v-if="bank.is_active && !bank.is_default"
                        variant="outlined"
                        color="warning"
                        icon="mdi-star-outline"
                        size="small"
                        density="comfortable"
                        title="Jadikan rekening utama"
                        @click="setDefaultBank(bank.id)"
                    />
                    <v-btn
                        variant="outlined"
                        color="info"
                        icon="mdi-pencil-outline"
                        size="small"
                        density="comfortable"
                        @click="
                            router.push(
                                `/member/profile/bank-form?id=${encodeRouteId(bank.id)}`,
                            )
                        "
                    />
                    <v-btn
                        variant="outlined"
                        color="error"
                        icon="mdi-delete-outline"
                        size="small"
                        density="comfortable"
                        @click="confirmDeleteBank(bank.id)"
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
                icon="mdi-bank-off-outline"
                size="32"
                color="grey"
                class="mb-2"
            />
            <p style="margin: 0; color: var(--muted); font-size: 13px">
                Belum ada rekening tersimpan
            </p>
        </div>

        <BaseConfirm ref="confirmDialogRef" />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import profileService from "@/member/services/profile.service";
import type { MemberBank } from "@/member/types/profile";
import BaseConfirm from "@/shared/components/BaseConfirm.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { encodeRouteId } from "@/shared/utils/route-id";

const snackbar = useSnackbarStore();
const router = useRouter();
const banks = ref<MemberBank[]>([]);
const confirmDialogRef = ref<InstanceType<typeof BaseConfirm> | null>(null);
const isLoading = ref(true);

async function fetchBanks() {
    isLoading.value = true;
    try {
        const response = await profileService.getBanks();
        if (response.success) {
            banks.value = response.data;
        }
    } catch (error) {
        console.error("Failed to load banks:", error);
    } finally {
        isLoading.value = false;
    }
}

async function setDefaultBank(id: number) {
    try {
        const response = await profileService.setDefaultBank(id);
        if (response.success) {
            snackbar.showMessage(
                "Rekening utama berhasil diperbarui",
                "success",
            );
            await fetchBanks();
        }
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message ||
                "Rekening utama belum dapat diperbarui.",
            "error",
        );
    }
}

async function confirmDeleteBank(id: number) {
    if (!confirmDialogRef.value) return;

    const isConfirmed = await confirmDialogRef.value.open({
        title: "Hapus Rekening",
        message: "Apakah Anda yakin ingin menghapus rekening ini?",
        confirmText: "Ya, Hapus",
        confirmColor: "error",
        icon: "mdi-delete-outline",
    });

    if (isConfirmed) {
        confirmDialogRef.value.isLoading = true;
        try {
            const response = await profileService.deleteBank(id);
            if (response.success) {
                snackbar.showMessage("Rekening berhasil dihapus", "success");
                await fetchBanks();
            } else {
                snackbar.showMessage("Rekening belum dapat dihapus.", "error");
            }
        } catch (error: any) {
            snackbar.showMessage(
                error.response?.data?.message ||
                    "Rekening belum dapat dihapus.",
                "error",
            );
        } finally {
            confirmDialogRef.value.isLoading = false;
        }
    }
}

onMounted(() => {
    fetchBanks();
});
</script>
