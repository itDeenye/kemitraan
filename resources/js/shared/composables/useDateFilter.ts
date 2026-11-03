import { ref } from "vue";

export const PROJECT_START_YEAR = 2026;

/**
 * Mendapatkan daftar tahun proyek dari tahun berjalan hingga PROJECT_START_YEAR (2026).
 * Diurutkan secara descending (tahun terbaru di paling atas).
 * Contoh:
 * - Tahun 2026 -> [2026]
 * - Tahun 2027 -> [2027, 2026]
 */
export function getProjectYears(startYear: number = PROJECT_START_YEAR): number[] {
    const currentYear = new Date().getFullYear();
    const maxYear = Math.max(currentYear, startYear);
    const count = maxYear - startYear + 1;

    return Array.from({ length: count }, (_, i) => maxYear - i);
}

/**
 * Composable terpisah jika hanya membutuhkan daftar tahun proyek
 */
export function useProjectYears(startYear: number = PROJECT_START_YEAR) {
    const currentYear = new Date().getFullYear();
    const years = getProjectYears(startYear);

    return {
        startYear,
        currentYear,
        years,
    };
}

export interface UseDateFilterOptions {
    startYear?: number;
    initialYear?: number;
    initialMonth?: number;
}

export function useDateFilter(options: UseDateFilterOptions = {}) {
    const startYear = options.startYear ?? PROJECT_START_YEAR;
    const currentYear = new Date().getFullYear();
    const currentMonth = new Date().getMonth() + 1;

    const defaultYear = options.initialYear ?? Math.max(currentYear, startYear);
    const defaultMonth = options.initialMonth ?? currentMonth;

    const selectedYear = ref(defaultYear);
    const selectedMonth = ref(defaultMonth);

    const selectedYearFrom = ref(defaultYear);
    const selectedMonthFrom = ref(1);

    const selectedYearTo = ref(defaultYear);
    const selectedMonthTo = ref(defaultMonth);

    const years = getProjectYears(startYear);
    const months = [
        { value: 1, label: "Januari" },
        { value: 2, label: "Februari" },
        { value: 3, label: "Maret" },
        { value: 4, label: "April" },
        { value: 5, label: "Mei" },
        { value: 6, label: "Juni" },
        { value: 7, label: "Juli" },
        { value: 8, label: "Agustus" },
        { value: 9, label: "September" },
        { value: 10, label: "Oktober" },
        { value: 11, label: "November" },
        { value: 12, label: "Desember" },
    ];

    const getMonthName = (month: number) => {
        return months.find((m) => m.value === month)?.label || "";
    };

    return {
        startYear,
        currentYear,
        currentMonth,
        selectedYear,
        selectedMonth,
        selectedYearFrom,
        selectedMonthFrom,
        selectedYearTo,
        selectedMonthTo,
        years,
        months,
        getMonthName,
    };
}
