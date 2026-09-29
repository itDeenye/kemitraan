<template>
    <div class="mb-6">
        <h1 class="text-h4 font-weight-bold mb-1">Saldo STC</h1>
        <p class="text-body-2 text-medium-emphasis">
            Halaman untuk melihat saldo dan riwayat mutasi saldo STC perusahaan.
        </p>
    </div>

    <v-row class="mb-4">
        <v-col cols="12" md="4">
            <v-card class="rounded-xl bg-primary" elevation="0">
                <v-card-text class="pa-6">
                    <div class="text-white text-opacity-80 text-body-2 mb-2">
                        Saldo STC Saat Ini
                    </div>
                    <div
                        v-if="loadingBalance"
                        class="d-flex align-center gap-2"
                    >
                        <v-progress-circular
                            indeterminate
                            size="24"
                            color="white"
                        ></v-progress-circular>
                    </div>
                    <div v-else class="text-3xl font-weight-bold text-white">
                        Rp{{ formattedBalance }}
                    </div>
                </v-card-text>
            </v-card>
        </v-col>
    </v-row>

    <BaseDataTable
        ref="mutationTable"
        url="/admin/stc/mutations"
        :headers="headers"
        v-model:search="search"
    >
        <template #item.datetime="{ item }">
            {{ formatDateTime(item.datetime) }}
        </template>

        <template #item.type="{ item }">
            <BaseBadge type="mutation" :value="item.type" />
        </template>

        <template #item.bank_name="{ item }">
            {{ item.bank_name || "-" }}
        </template>

        <template #item.amount="{ item }">
            <span class="font-weight-bold"
                >{{ formatPrice(item.amount) }}
            </span>
        </template>

        <!-- <template #item.service_fee="{ item }">
            {{ item.service_fee != null ? formatPrice(item.service_fee) : "0" }}
        </template>

        <template #item.recorded_balance="{ item }">
            {{
                item.recorded_balance != null
                    ? formatPrice(item.recorded_balance)
                    : "0"
            }}
        </template>

        <template #item.ending_balance="{ item }">
            <span
                v-if="item.ending_balance != null"
                class="font-weight-bold text-primary"
                >{{ formatPrice(item.ending_balance) }}</span
            >
            <span v-else>0</span>
        </template> -->

        <template #item.note="{ item }">
            <span
                class="text-truncate d-inline-block"
                style="max-width: 400px"
                :title="item.note"
            >
                {{ item.note || "-" }}
            </span>
        </template>

        <!-- <template #item.actions="{ item }">
            <div class="d-flex ga-2 justify-center">
                <v-btn
                    v-tooltip:top="'Detail Mutasi'"
                    icon="mdi-eye-outline"
                    variant="outlined"
                    size="small"
                    class="rounded"
                    color="primary"
                    @click="viewDetail(item)"
                />
            </div>
        </template> -->
    </BaseDataTable>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import stcService from "@/admin/services/stc.service";
import type { StcBalance, StcMutation } from "@/admin/types/stc";
import { useFormatter } from "@/shared/composables/useFormatter";

const { formatDateTime, formatPrice } = useFormatter();

const balanceData = ref<StcBalance>({ balance: 0 });
const loadingBalance = ref(true);
const search = ref("");
const mutationTable = ref<InstanceType<typeof BaseDataTable> | null>(null);

const formattedBalance = computed(() => {
    if (balanceData.value.formatted_balance) {
        return balanceData.value.formatted_balance;
    }
    return formatPrice(balanceData.value.balance);
});

const headers = [
    {
        title: "Tanggal Mutasi",
        key: "datetime",
        align: "start",
        type: "date",
        sortable: true,
        filter: true,
    },
    {
        title: "Tipe",
        key: "type",
        type: "select",
        align: "center",
        sortable: false,
        filter: true,
        options: [
            { value: "in", label: "Masuk" },
            { value: "out", label: "Keluar" },
        ],
        placeholder: "Pilih Tipe",
    },
    {
        title: "Bank",
        key: "bank_name",
        type: "text",
        align: "start",
        sortable: false,
    },
    {
        title: "Nominal (Rp)",
        key: "amount",
        sortable: false,
        align: "right",
    },
    // {
    //     title: "Biaya Layanan (Rp)",
    //     key: "service_fee",
    //     sortable: false,
    //     align: "right",
    // },
    // {
    //     title: "Saldo Tercatat (Rp)",
    //     key: "recorded_balance",
    //     sortable: false,
    //     align: "right",
    // },
    // {
    //     title: "Saldo Akhir (Rp)",
    //     key: "ending_balance",
    //     sortable: false,
    //     align: "right",
    // },
    {
        title: "Catatan",
        key: "note",
        type: "text",
        align: "start",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Catatan",
    },
    // {
    //     title: "Aksi",
    //     key: "actions",
    //     sortable: false,
    //     align: "center",
    // },
];

const fetchBalance = async () => {
    loadingBalance.value = true;
    try {
        balanceData.value = await stcService.getBalance();
    } catch (e) {
        console.error("Failed to fetch balance", e);
    } finally {
        loadingBalance.value = false;
    }
};

const viewDetail = (item: StcMutation) => {
    // jika udah ada api detail
    console.log("View mutation detail:", item);
};

onMounted(() => {
    fetchBalance();
});
</script>
