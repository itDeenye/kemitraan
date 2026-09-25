<template>
    <div class="screen-body">
        <div
            v-if="showSuccessBanner"
            class="card text-center"
            style="
                padding: 24px;
                margin-bottom: 16px;
                background-color: #f0fdf4;
                border: 1px solid #bbf7d0;
            "
        >
            <v-icon
                icon="mdi-check-circle"
                color="success"
                size="48"
                class="mb-2"
            />
            <h3 class="mb-2 text-green-700">Pendaftaran Berhasil!</h3>
            <p class="soft-label m-0 text-green-600" style="font-size: 13px">
                Pengajuan mitra berhasil dikirim, silakan menunggu persetujuan
                admin perusahaan.
            </p>
        </div>

        <div v-if="isLoading" class="empty-state">
            <v-progress-circular indeterminate color="primary" size="24" />
            <p class="soft-label mt-2">Memuat detail pendaftaran...</p>
        </div>

        <div v-else-if="registration" class="pb-6">
            <div class="card overflow-hidden mb-4 p-0">
                <div
                    class="px-4 py-4 border-b border-[var(--line)] flex flex-row items-center bg-[var(--bg-light)]"
                >
                    <div class="flex-1 min-w-0">
                        <h2
                            class="text-base m-0 mt-1 whitespace-nowrap overflow-hidden text-ellipsis"
                        >
                            {{ registration.applicant.name }}
                        </h2>
                    </div>
                    <div class="shrink-0 ml-3">
                        <BaseBadge
                            type="registration_status"
                            :value="registration.status?.code"
                            inline
                            {{
                            registration.status?.label
                            }}
                        />
                    </div>
                </div>

                <div
                    class="bg-[var(--bg-light)] px-4 py-3 border-b border-[var(--line)]"
                >
                    <h3
                        class="flex items-center gap-2 m-0 text-[13px] font-semibold"
                    >
                        <v-icon
                            icon="mdi-account-group-outline"
                            size="16"
                            color="var(--primary)"
                        />
                        Informasi Jaringan
                    </h3>
                </div>
                <div
                    class="flex flex-col gap-3 px-4 py-4 border-b border-[var(--line)]"
                >
                    <div class="flex justify-between items-start text-[13px]">
                        <span class="text-[var(--muted)]">Sponsor</span>
                        <div class="text-right">
                            <strong class="text-[var(--text)] block">{{
                                registration.sponsor?.name || "-"
                            }}</strong>
                            <span class="text-[11px] text-[var(--muted)]">{{
                                registration.sponsor?.code || ""
                            }}</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-start text-[13px]">
                        <span class="text-[var(--muted)]">Level Diajukan</span>
                        <div class="text-right">
                            <strong class="text-[var(--text)] block">{{
                                registration.level.name
                            }}</strong>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-[var(--bg-light)] px-4 py-3 border-b border-[var(--line)]"
                >
                    <h3
                        class="flex items-center gap-2 m-0 text-[13px] font-semibold"
                    >
                        <v-icon
                            icon="mdi-account-outline"
                            size="16"
                            color="var(--primary)"
                        />
                        Data Pribadi
                    </h3>
                </div>
                <div
                    class="flex flex-col gap-3 px-4 py-4 border-b border-[var(--line)]"
                >
                    <div class="flex justify-between items-start text-[13px]">
                        <span class="text-[var(--muted)] shrink-0 mr-4"
                            >Nama Lengkap</span
                        >
                        <strong class="text-[var(--text)] text-right">{{
                            registration.applicant.name
                        }}</strong>
                    </div>
                    <div class="flex justify-between items-start text-[13px]">
                        <span class="text-[var(--muted)] shrink-0 mr-4"
                            >Nomor Telepon</span
                        >
                        <strong class="text-[var(--text)] text-right">{{
                            registration.applicant.mobile_phone
                        }}</strong>
                    </div>
                    <div class="flex justify-between items-start text-[13px]">
                        <span class="text-[var(--muted)] shrink-0 mr-4"
                            >Email</span
                        >
                        <strong class="text-[var(--text)] text-right">{{
                            registration.applicant.email || "-"
                        }}</strong>
                    </div>
                    <div class="flex justify-between items-start text-[13px]">
                        <span class="text-[var(--muted)] shrink-0 mr-4"
                            >Jenis Kelamin</span
                        >
                        <strong class="text-[var(--text)] text-right">{{
                            registration.applicant.gender
                        }}</strong>
                    </div>
                    <div class="flex justify-between items-start text-[13px]">
                        <span class="text-[var(--muted)] shrink-0 mr-4"
                            >Tanggal Lahir</span
                        >
                        <strong class="text-[var(--text)] text-right">{{
                            formatDate(registration.applicant.birth_date)
                        }}</strong>
                    </div>
                </div>

                <div
                    class="bg-[var(--bg-light)] px-4 py-3 border-b border-[var(--line)]"
                >
                    <h3
                        class="flex items-center gap-2 m-0 text-[13px] font-semibold"
                    >
                        <v-icon
                            icon="mdi-card-account-details-outline"
                            size="16"
                            color="var(--primary)"
                        />
                        Data Identitas & Alamat
                    </h3>
                </div>
                <div
                    class="flex flex-col gap-3 px-4 py-4"
                    :class="{
                        'border-b border-[var(--line)]': registration.bank,
                    }"
                >
                    <div class="flex justify-between items-start text-[13px]">
                        <span class="text-[var(--muted)] shrink-0 mr-4"
                            >Jenis Identitas</span
                        >
                        <strong class="text-[var(--text)] text-right">{{
                            registration.identity.type
                        }}</strong>
                    </div>
                    <div class="flex justify-between items-start text-[13px]">
                        <span class="text-[var(--muted)] shrink-0 mr-4"
                            >Nomor Identitas</span
                        >
                        <strong class="text-[var(--text)] text-right">{{
                            registration.identity.number
                        }}</strong>
                    </div>
                    <div
                        class="flex justify-between items-start text-[13px]"
                        v-if="registration.identity.nib"
                    >
                        <span class="text-[var(--muted)] shrink-0 mr-4"
                            >NIB</span
                        >
                        <strong class="text-[var(--text)] text-right">{{
                            registration.identity.nib
                        }}</strong>
                    </div>
                    <div class="flex justify-between items-start text-[13px]">
                        <span class="text-[var(--muted)] shrink-0 mr-4"
                            >Alamat Lengkap</span
                        >
                        <div class="text-[var(--text)] text-right font-bold">
                            {{ registration.address.address }}<br />
                            {{ registration.address.subdistrict_name }},
                            {{ registration.address.district_name }}<br />
                            {{ registration.address.city_name }},
                            {{ registration.address.province_name }}
                        </div>
                    </div>
                </div>

                <template v-if="registration.bank">
                    <div
                        class="bg-[var(--bg-light)] px-4 py-3 border-b border-[var(--line)]"
                    >
                        <h3
                            class="flex items-center gap-2 m-0 text-[13px] font-semibold"
                        >
                            <v-icon
                                icon="mdi-bank-outline"
                                size="16"
                                color="var(--primary)"
                            />
                            Data Bank
                        </h3>
                    </div>
                    <div class="flex flex-col gap-3 px-4 py-4">
                        <div
                            class="flex justify-between items-start text-[13px]"
                        >
                            <span class="text-[var(--muted)] shrink-0 mr-4"
                                >Bank</span
                            >
                            <strong class="text-[var(--text)] text-right">{{
                                registration.bank.name
                            }}</strong>
                        </div>
                        <div
                            class="flex justify-between items-start text-[13px]"
                        >
                            <span class="text-[var(--muted)] shrink-0 mr-4"
                                >Rekening</span
                            >
                            <div class="text-right">
                                <strong class="text-[var(--text)] block">{{
                                    registration.bank.account_number
                                }}</strong>
                                <span class="text-[11px] text-[var(--muted)]"
                                    >a.n
                                    {{ registration.bank.account_name }}</span
                                >
                            </div>
                        </div>
                    </div>
                </template>

                <div
                    v-if="registration.note"
                    class="px-4 py-4 border-t border-[var(--line)]"
                >
                    <v-alert
                        :color="
                            registration.status?.code === 'rejected'
                                ? 'error'
                                : 'success'
                        "
                        variant="tonal"
                        density="compact"
                        class="text-[13px]"
                    >
                        <template #prepend>
                            <v-icon
                                :icon="
                                    registration.status?.code === 'rejected'
                                        ? 'mdi-alert-circle-outline'
                                        : 'mdi-check-circle-outline'
                                "
                                size="24"
                            ></v-icon>
                        </template>
                        <div class="font-bold mb-1">
                            {{
                                registration.status?.code === "rejected"
                                    ? "Catatan Penolakan"
                                    : "Catatan Penyetujuan"
                            }}
                        </div>
                        <div>{{ registration.note }}</div>
                    </v-alert>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import networkService from "@/member/services/network.service";
import type { RegistrationDetail } from "@/member/types/network";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useFormatter } from "@/shared/composables/useFormatter";
import { decodeRouteId } from "@/shared/utils/route-id";

const route = useRoute();
const router = useRouter();
const snackbar = useSnackbarStore();
const { formatDate } = useFormatter();

const id = decodeRouteId(
    (route.params as Record<string, unknown>).id,
);
const showSuccessBanner = ref(route.query.success === "1");

const isLoading = ref(true);
const registration = ref<RegistrationDetail | null>(null);

async function fetchDetail() {
    isLoading.value = true;
    try {
        const data = await networkService.getRegistration(id);
        registration.value = data;
    } catch (e: any) {
        console.error("Failed to load registration details:", e);
        snackbar.showError("Gagal memuat detail pendaftaran");
    } finally {
        isLoading.value = false;
    }
}

function goBack() {
    if (showSuccessBanner.value) {
        router.replace("/member/network/registrations?tab=list");
    } else {
        router.back();
    }
}

onMounted(() => {
    fetchDetail();
});
</script>
