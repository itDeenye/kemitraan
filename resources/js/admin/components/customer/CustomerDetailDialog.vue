<template>
    <v-dialog v-model="dialog" max-width="600" scrollable persistent>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-avatar color="primary" variant="tonal" size="48">
                        <v-icon>mdi-account-outline</v-icon>
                    </v-avatar>
                    <div>
                        <div class="text-h6 font-weight-bold">
                            Detail Pelanggan
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ customer?.name || "Memuat..." }}
                        </div>
                    </div>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    @click="close"
                />
            </v-card-title>

            <v-card-text class="pa-6">
                <div v-if="loading" class="d-flex justify-center py-12">
                    <v-progress-circular indeterminate color="primary" />
                </div>

                <div v-else-if="customer" class="d-flex flex-column ga-6">
                    <div>
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="primary"
                                >mdi-card-account-details-outline</v-icon
                            >
                            Informasi Pribadi
                        </h3>
                        <v-card variant="outlined" class="rounded-lg pa-4">
                            <v-row dense>
                                <v-col cols="12" sm="6">
                                    <div
                                        class="text-caption text-medium-emphasis mb-1"
                                    >
                                        Nama Lengkap
                                    </div>
                                    <div class="text-body-2 font-weight-medium">
                                        {{ customer.name }}
                                    </div>
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <div
                                        class="text-caption text-medium-emphasis mb-1"
                                    >
                                        Jenis Kelamin
                                    </div>
                                    <div class="text-body-2 font-weight-medium">
                                        {{ formatGender(customer.gender) }}
                                    </div>
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <div
                                        class="text-caption text-medium-emphasis mb-1"
                                    >
                                        Nomor WhatsApp
                                    </div>
                                    <div class="text-body-2 font-weight-medium">
                                        {{ customer.whatsapp || "-" }}
                                    </div>
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <div
                                        class="text-caption text-medium-emphasis mb-1"
                                    >
                                        Nomor Telepon (Alternatif)
                                    </div>
                                    <div class="text-body-2 font-weight-medium">
                                        {{ customer.phone || "-" }}
                                    </div>
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <div
                                        class="text-caption text-medium-emphasis mb-1"
                                    >
                                        Tanggal Lahir
                                    </div>
                                    <div class="text-body-2 font-weight-medium">
                                        {{
                                            formatDate(customer.birth_date) ||
                                            "-"
                                        }}
                                    </div>
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <div
                                        class="text-caption text-medium-emphasis mb-1"
                                    >
                                        Bergabung Pada
                                    </div>
                                    <div class="text-body-2 font-weight-medium">
                                        {{
                                            formatDateTime(customer.created_at)
                                        }}
                                    </div>
                                </v-col>
                                <v-col cols="12">
                                    <div
                                        class="text-caption text-medium-emphasis mb-1"
                                    >
                                        Alamat
                                    </div>
                                    <div class="text-body-2 font-weight-medium">
                                        {{ formatLocation(customer.region) }}
                                    </div>
                                </v-col>
                            </v-row>
                        </v-card>
                    </div>

                    <div>
                        <h3
                            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center ga-2"
                        >
                            <v-icon size="small" color="primary"
                                >mdi-handshake-outline</v-icon
                            >
                            Mitra Pencatat
                        </h3>
                        <v-card
                            v-if="customer.member?.id"
                            variant="outlined"
                            class="rounded-lg"
                        >
                            <v-list lines="two" bg-color="transparent">
                                <v-list-item>
                                    <template v-slot:prepend>
                                        <v-avatar
                                            color="primary"
                                            variant="tonal"
                                        >
                                            {{
                                                customer.member?.name?.charAt(
                                                    0,
                                                ) || "-"
                                            }}
                                        </v-avatar>
                                    </template>
                                    <v-list-item-title
                                        class="font-weight-bold"
                                        >{{
                                            customer.member.name
                                        }}</v-list-item-title
                                    >
                                    <v-list-item-subtitle
                                        class="d-flex align-center ga-2 mt-1"
                                    >
                                        <span>{{ customer.member.code }}</span>
                                        <BaseBadge
                                            type="level"
                                            :value="customer.member.level.name"
                                            size="small"
                                        />
                                    </v-list-item-subtitle>
                                </v-list-item>
                            </v-list>
                        </v-card>
                        <v-alert
                            v-else
                            type="info"
                            variant="tonal"
                            density="compact"
                        >
                            Tidak memiliki mitra referensi
                        </v-alert>
                    </div>
                </div>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import customerService from "@/admin/services/customer.service";
import type { Customer } from "@/admin/types/customer";
import { useFormatter } from "@/shared/composables/useFormatter";
import BaseBadge from "@/shared/components/BaseBadge.vue";

const { formatGender, formatDate, formatDateTime, formatLocation } =
    useFormatter();

const dialog = ref(false);
const loading = ref(false);
const customer = ref<Customer | null>(null);

const open = async (id: number | string) => {
    dialog.value = true;
    await fetchDetail(id);
};

const close = () => {
    dialog.value = false;
    customer.value = null;
};

const fetchDetail = async (id: number | string) => {
    try {
        loading.value = true;
        customer.value = await customerService.getCustomer(id);
    } catch (error) {
        console.error("Failed to fetch detail", error);
    } finally {
        loading.value = false;
    }
};

defineExpose({
    open,
    close,
});
</script>
