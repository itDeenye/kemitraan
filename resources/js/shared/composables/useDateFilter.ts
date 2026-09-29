import { ref } from "vue";

export function useDateFilter() {
    const currentYear = new Date().getFullYear();
    const currentMonth = new Date().getMonth() + 1;

    const selectedYear = ref(currentYear);
    const selectedMonth = ref(currentMonth);

    const selectedYearFrom = ref(currentYear);
    const selectedMonthFrom = ref(1);

    const selectedYearTo = ref(currentYear);
    const selectedMonthTo = ref(currentMonth);

    const years = Array.from({ length: 5 }, (_, i) => currentYear - i);
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
