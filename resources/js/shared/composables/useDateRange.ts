import { ref } from "vue";

export function useDateRange() {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const today = new Date();

    const formatDateForInput = (date: Date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const dateFrom = ref(formatDateForInput(firstDay));
    const dateTo = ref(formatDateForInput(today));

    return {
        dateFrom,
        dateTo,
    };
}
