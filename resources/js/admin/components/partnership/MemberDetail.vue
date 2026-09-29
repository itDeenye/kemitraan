<template>
    <v-dialog v-model="dialog" max-width="1100" scrollable>
        <v-card class="rounded-xl elevation-10">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28"
                        >mdi-card-account-details-outline</v-icon
                    >
                    <span class="text-h6 font-weight-bold text-primary">
                        Detail Mitra
                    </span>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    color="medium-emphasis"
                    @click="close"
                    density="comfortable"
                ></v-btn>
            </v-card-title>

            <v-divider></v-divider>

            <v-card-text class="pa-6 pt-2" style="max-height: 75vh">
                <div v-if="loading" class="d-flex justify-center py-12">
                    <v-progress-circular
                        indeterminate
                        color="primary"
                        size="48"
                    ></v-progress-circular>
                </div>

                <div v-else-if="member">
                    <div class="text-center mb-8 pt-4">
                        <v-avatar
                            color="primary"
                            size="90"
                            class="mb-4 elevation-2"
                        >
                            <v-img
                                v-if="member.image"
                                :src="member.image"
                                cover
                            ></v-img>
                            <span
                                v-else
                                class="text-h4 font-weight-bold text-white"
                                >{{ member.name.charAt(0) }}</span
                            >
                        </v-avatar>
                        <h3 class="text-h5 font-weight-bold mb-1">
                            {{ member.name }}
                        </h3>
                        <p class="text-body-1 text-medium-emphasis mb-3">
                            {{ member.email }}
                        </p>
                        <div
                            class="d-flex align-center justify-center ga-2 mt-2"
                        >
                            <BaseBadge
                                inline
                                type="level"
                                :value="member.level?.name"
                                class="text-capitalize font-weight-bold"
                            />
                            <BaseBadge
                                inline
                                type="status"
                                :value="member.status?.code === 1"
                            />
                        </div>
                    </div>

                    <v-row>
                        <v-col cols="12" md="6">
                            <div class="d-flex flex-column ga-4">
                                <div>
                                    <h3
                                        class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3"
                                    >
                                        <v-icon size="22" color="primary"
                                            >mdi-account-circle-outline</v-icon
                                        >
                                        Informasi Utama
                                    </h3>
                                    <v-card
                                        variant="flat"
                                        class="rounded-lg border border-opacity-25"
                                    >
                                        <v-list
                                            density="compact"
                                            class="bg-transparent pa-2"
                                        >
                                            <v-list-item>
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-barcode</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                    >Kode
                                                    Mitra</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{
                                                            member.code || "-"
                                                        }}</span
                                                    ></template
                                                >
                                            </v-list-item>

                                            <v-list-item v-if="member.username">
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-account-badge-outline</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                    >Username</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{
                                                            member.username
                                                        }}</span
                                                    ></template
                                                >
                                            </v-list-item>

                                            <v-list-item>
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-phone-outline</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                    >Nomor
                                                    Telepon</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{
                                                            member.mobile_phone ||
                                                            "-"
                                                        }}</span
                                                    ></template
                                                >
                                            </v-list-item>

                                            <v-list-item>
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-cake-variant-outline</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                    >Tanggal
                                                    Lahir</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{
                                                            member.birth_date
                                                                ? formatDate(
                                                                      member.birth_date,
                                                                  )
                                                                : "-"
                                                        }}</span
                                                    ></template
                                                >
                                            </v-list-item>

                                            <v-list-item>
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-gender-male-female-variant</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                    >Jenis
                                                    Kelamin</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{
                                                            member.gender || "-"
                                                        }}</span
                                                    ></template
                                                >
                                            </v-list-item>

                                            <v-list-item>
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-calendar-range</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                    >Tanggal
                                                    Gabung</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{
                                                            formatDateTime(
                                                                member.joined_at,
                                                            )
                                                        }}</span
                                                    ></template
                                                >
                                            </v-list-item>
                                        </v-list>
                                    </v-card>
                                </div>

                                <div>
                                    <h3
                                        class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3"
                                    >
                                        <v-icon size="22" color="primary"
                                            >mdi-source-branch</v-icon
                                        >
                                        Informasi Jaringan
                                    </h3>
                                    <v-card
                                        variant="flat"
                                        class="rounded-lg border border-opacity-25"
                                    >
                                        <v-list
                                            density="compact"
                                            class="bg-transparent pa-2"
                                        >
                                            <v-list-item>
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-account-arrow-up-outline</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                >
                                                    Upline</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{
                                                            member.parent
                                                                ?.name ||
                                                            "Pusat"
                                                        }}
                                                        ({{
                                                            member.parent
                                                                ?.code || "-"
                                                        }})</span
                                                    ></template
                                                >
                                            </v-list-item>
                                            <v-list-item v-if="member.network">
                                                <template #prepend
                                                    ><v-icon
                                                        color="grey-darken-1"
                                                        size="20"
                                                        class="mr-3"
                                                        >mdi-account-group-outline</v-icon
                                                    ></template
                                                >
                                                <v-list-item-title
                                                    class="text-body-2 text-medium-emphasis"
                                                    >Total
                                                    Downline</v-list-item-title
                                                >
                                                <template #append
                                                    ><span
                                                        class="font-weight-bold text-body-2"
                                                        >{{
                                                            member.network
                                                                .total_direct_downlines
                                                        }}
                                                        Mitra</span
                                                    ></template
                                                >
                                            </v-list-item>
                                        </v-list>
                                    </v-card>
                                </div>
                            </div>
                        </v-col>

                        <v-col cols="12" md="6">
                            <div class="d-flex flex-column ga-4">
                                <div
                                    v-if="
                                        member.addresses &&
                                        member.addresses.length > 0
                                    "
                                >
                                    <h3
                                        class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3"
                                    >
                                        <v-icon size="22" color="primary"
                                            >mdi-map-marker-outline</v-icon
                                        >
                                        Data Alamat
                                    </h3>
                                    <v-card
                                        v-for="(
                                            address, idx
                                        ) in member.addresses"
                                        :key="address.id || idx"
                                        variant="flat"
                                        class="rounded-lg border border-opacity-25 mb-3"
                                    >
                                        <v-card-text class="pa-4 text-body-2">
                                            <div
                                                class="d-flex align-center justify-space-between mb-2"
                                            >
                                                <span
                                                    class="font-weight-bold text-primary"
                                                    >{{
                                                        address.label ||
                                                        "Alamat " + (idx + 1)
                                                    }}</span
                                                >
                                                <v-chip
                                                    v-if="address.is_default"
                                                    color="success"
                                                    size="x-small"
                                                    label
                                                    class="font-weight-bold"
                                                    >UTAMA</v-chip
                                                >
                                            </div>
                                            <p class="font-weight-bold mb-1">
                                                {{ address.recipient }}
                                                <span
                                                    class="text-medium-emphasis font-weight-regular"
                                                    >({{ address.phone }})</span
                                                >
                                            </p>
                                            <p
                                                class="text-grey-darken-2"
                                                style="line-height: 1.5"
                                            >
                                                {{ address.full_address }}<br />
                                                {{
                                                    formatLocation(
                                                        address.region,
                                                    )
                                                }}
                                            </p>
                                        </v-card-text>
                                    </v-card>
                                </div>

                                <div
                                    v-if="
                                        member.bank_accounts &&
                                        member.bank_accounts.length > 0
                                    "
                                >
                                    <h3
                                        class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2 text-grey-darken-3 mt-4"
                                    >
                                        <v-icon size="22" color="primary"
                                            >mdi-bank-outline</v-icon
                                        >
                                        Data Rekening
                                    </h3>
                                    <v-card
                                        v-for="(
                                            bank, idx
                                        ) in member.bank_accounts"
                                        :key="bank.id || idx"
                                        variant="flat"
                                        class="rounded-lg border border-opacity-25 mb-3"
                                    >
                                        <v-card-text class="pa-4 text-body-2">
                                            <div
                                                class="d-flex justify-space-between align-start mb-2"
                                            >
                                                <p
                                                    class="font-weight-bold mb-1"
                                                >
                                                    {{
                                                        bank.bank_name ||
                                                        "Bank Tidak Diketahui"
                                                    }}
                                                </p>
                                                <div class="d-flex ga-1">
                                                    <v-chip
                                                        v-if="bank.is_default"
                                                        color="primary"
                                                        size="x-small"
                                                        label
                                                        class="font-weight-bold"
                                                        >UTAMA</v-chip
                                                    >
                                                    <v-chip
                                                        v-if="!bank.is_active"
                                                        color="error"
                                                        size="x-small"
                                                        label
                                                        class="font-weight-bold"
                                                        >TIDAK AKTIF</v-chip
                                                    >
                                                </div>
                                            </div>
                                            <p
                                                class="text-h6 font-weight-bold mb-2"
                                                style="letter-spacing: 1px"
                                            >
                                                {{
                                                    bank.account_number || "---"
                                                }}
                                            </p>
                                            <p
                                                class="text-medium-emphasis mb-0"
                                            >
                                                a.n.
                                                <span
                                                    class="font-weight-medium"
                                                    >{{
                                                        bank.account_name ||
                                                        "---"
                                                    }}</span
                                                >
                                            </p>

                                            <template
                                                v-if="bank.branch || bank.city"
                                            >
                                                <v-divider
                                                    class="my-3 border-opacity-50"
                                                ></v-divider>
                                                <div
                                                    class="d-flex flex-column ga-2 text-medium-emphasis"
                                                >
                                                    <div
                                                        v-if="bank.branch"
                                                        class="d-flex justify-space-between"
                                                    >
                                                        <span>Cabang:</span>
                                                        <span
                                                            class="font-weight-medium"
                                                            >{{
                                                                bank.branch
                                                            }}</span
                                                        >
                                                    </div>
                                                    <div
                                                        v-if="bank.city"
                                                        class="d-flex justify-space-between"
                                                    >
                                                        <span>Kota:</span>
                                                        <span
                                                            class="font-weight-medium"
                                                            >{{
                                                                bank.city
                                                            }}</span
                                                        >
                                                    </div>
                                                </div>
                                            </template>
                                        </v-card-text>
                                    </v-card>
                                </div>
                            </div>
                        </v-col>
                    </v-row>
                </div>
            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions class="pa-6 pt-4 border-t">
                <v-spacer />
                <v-btn
                    variant="flat"
                    color="primary"
                    class="text-none px-8"
                    @click="close"
                    >Tutup</v-btn
                >
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import type { Member } from "@/admin/types/member";
import memberService from "@/admin/services/member.service";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDate, formatDateTime, formatLocation } = useFormatter();

const dialog = ref(false);
const loading = ref(false);
const member = ref<Member | null>(null);

const open = async (id: number) => {
    dialog.value = true;
    loading.value = true;
    try {
        member.value = await memberService.getMember(id);
    } catch (error) {
        console.error("Gagal mengambil detail mitra", error);
        alert("Gagal mengambil detail mitra");
        dialog.value = false;
    } finally {
        loading.value = false;
    }
};

const close = () => {
    dialog.value = false;
    setTimeout(() => {
        member.value = null;
    }, 300);
};

defineExpose({ open, close });
</script>
