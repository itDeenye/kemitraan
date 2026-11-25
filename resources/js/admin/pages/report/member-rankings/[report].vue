<template>
    <template v-if="report">
        <div class="mb-5">
            <h1 class="text-h4 font-weight-bold mb-1">{{ report.title }}</h1>
            <p class="text-body-2 text-medium-emphasis">
                {{ report.description }}
            </p>
        </div>

        <BaseDataTable
            :key="reportKey"
            :title="report.title"
            url="/admin/reports/member-rankings"
            :headers="headers"
            :query-params="params"
            :export-file-name="exportFileName"
            :export-all-without-filters="false"
            show-export
        >
            <template #header-actions>
                <div class="d-flex align-center ga-3">
                    <v-select
                        v-if="report.monthly"
                        v-model="month"
                        :items="months"
                        item-title="title"
                        item-value="value"
                        label="Bulan"
                        variant="outlined"
                        density="compact"
                        hide-details
                        class="bg-surface rounded-lg"
                        style="width: 140px"
                    />
                    <v-select
                        v-model="year"
                        :items="years"
                        label="Tahun"
                        variant="outlined"
                        density="compact"
                        hide-details
                        class="bg-surface rounded-lg"
                        style="width: 110px"
                    />
                </div>
            </template>

            <template #item.level="{ item }">
                <BaseBadge
                    type="level"
                    :value="item.level"
                    size="x-small"
                    inline
                />
            </template>

            <template #item.name="{ item }">
                <div class="d-flex flex-column py-2">
                    <div class="font-weight-medium text-body-2">
                        {{ item.name || "-" }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                        {{ item.code || "-" }}
                    </div>
                </div>
            </template>

            <template
                v-for="metric in report.metrics"
                :key="metric.key"
                #[`item.${metric.key}`]="{ item }"
            >
                <span class="font-weight-medium tabular-numbers">{{
                    formatPrice(item[metric.key])
                }}</span>
            </template>
        </BaseDataTable>
    </template>
    <v-alert v-else type="warning" variant="tonal"
        >Laporan tidak ditemukan.</v-alert
    >
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import BaseDataTable from "@/shared/components/BaseDataTable.vue";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import { useFormatter } from "@/shared/composables/useFormatter";
import { PROJECT_START_YEAR } from "@/shared/composables/useDateFilter";

const { formatPrice } = useFormatter();
const route = useRoute<"/admin/report/member-rankings/[report]">();
const reports = {
    purchases: {
        title: "Top Pembelanjaan",
        description:
            "Total belanja reguler dan PO setelah persetujuan pembayaran, diurutkan per level mitra.",
        monthly: true,
        metrics: [{ key: "purchase_amount", title: "Pembelanjaan (Rp)" }],
    },
    recruitment: {
        title: "Top Rekrutan",
        description:
            "Registrasi disetujui pada periode terpilih, dihitung untuk mitra yang mendaftarkan.",
        monthly: true,
        metrics: [{ key: "recruit_count", title: "Jumlah Rekrutan" }],
    },
    "monthly-rewards": {
        title: "Top Reward Bulanan",
        description:
            "Poin dan nominal reward distributor dari hasil closing. Nominal ini bukan status pembayaran.",
        monthly: true,
        metrics: [
            { key: "monthly_points", title: "Poin Bulanan" },
            { key: "reward_amount", title: "Reward (Rp)" },
        ],
    },
    "annual-points": {
        title: "Poin Tahunan",
        description:
            "Saldo poin tahunan distributor yang tersimpan untuk tahun terpilih.",
        monthly: false,
        metrics: [{ key: "annual_points", title: "Poin Tahunan" }],
    },
} as const;

const reportKey = computed(() => String(route.params.report));
const report = computed(() =>
    Object.hasOwn(reports, reportKey.value)
        ? reports[reportKey.value as keyof typeof reports]
        : null,
);
const periodParts = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Jakarta",
    year: "numeric",
    month: "2-digit",
}).formatToParts();
const currentYear = Number(
    periodParts.find((part) => part.type === "year")?.value,
);
const currentMonth = Number(
    periodParts.find((part) => part.type === "month")?.value,
);
const startYear =
    typeof PROJECT_START_YEAR !== "undefined" ? PROJECT_START_YEAR : 2026;
const maxYear = Math.max(currentYear, startYear);
const year = ref(maxYear);
const month = ref(currentMonth);
const years = Array.from(
    { length: maxYear - startYear + 1 },
    (_, index) => maxYear - index,
);
const months = Array.from({ length: 12 }, (_, index) => ({
    title: new Intl.DateTimeFormat("id-ID", {
        month: "long",
        timeZone: "UTC",
    }).format(new Date(Date.UTC(2020, index, 1))),
    value: index + 1,
}));
const params = computed(() => ({
    report: reportKey.value,
    year: year.value,
    ...(report.value?.monthly ? { month: month.value } : {}),
}));
const headers = computed(() => [
    {
        key: "name",
        title: "Mitra",
        type: "text",
        align: "start",
        sortable: false,
        filter: true,
        filterLabel: "Nama Mitra",
        placeholder: "Masukkan Nama Mitra",
        export: true,
        exportValue: (item: any) => item.name || "-",
    },
    {
        key: "level",
        title: "Level",
        filterKey: "level_code",
        align: "center",
        type: "select",
        sortable: false,
        filter: true,
        options: [
            { value: "DST", label: "Distributor" },
            { value: "AGT", label: "Agen Utama" },
            { value: "RSL", label: "Reseller" },
        ],
        placeholder: "Pilih Level",
        export: true,
        exportValue: (item: any) => item.level || "-",
    },
    {
        key: "code",
        title: "Kode Mitra",
        type: "text",
        align: "start",
        visible: false,
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kode Mitra",
        export: true,
        exportValue: (item: any) => item.code || "-",
    },
    {
        key: "area",
        title: "Area",
        filterKey: "city_name",
        type: "text",
        align: "start",
        sortable: false,
        filter: true,
        placeholder: "Masukkan Kota/Area",
        export: true,
        exportValue: (item: any) => item.area || "-",
    },
    ...(report.value?.metrics.map((metric) => ({
        ...metric,
        align: "end",
        sortable: false,
        export: true,
        exportValue: (item: any) => item[metric.key] ?? 0,
    })) ?? []),
]);
const exportFileName = (scope: "current" | "all") => {
    const period = report.value?.monthly
        ? `${year.value}-${String(month.value).padStart(2, "0")}`
        : year.value;
    return `dny-${reportKey.value}-${period}${scope === "current" ? "-halaman" : ""}.xlsx`;
};
</script>

<style scoped>
.tabular-numbers {
    font-variant-numeric: tabular-nums;
}
</style>
