export function useFormatter() {
    function formatPrice(price?: number | string | null): string {
        if (price === null || price === undefined || price === "") return "0";
        const num = Number(price);
        if (isNaN(num)) return "0";
        return num.toLocaleString("id-ID");
    }

    function formatDate(dateStr?: string | null): string {
        if (!dateStr) return "-";
        const date = new Date(dateStr);
        return date.toLocaleDateString("id-ID", {
            day: "2-digit",
            month: "long",
            year: "numeric",
        });
    }

    function formatDateTime(dateStr?: string | null | Date): string {
        if (!dateStr) return "-";
        const date = dateStr instanceof Date ? dateStr : new Date(dateStr);
        return date.toLocaleDateString("id-ID", {
            day: "2-digit",
            month: "long",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit"
        }).replace(/\./g, ':').replace(/\s*pukul\s*/gi, ' ');
    }

    function formatShortDateTime(dateStr?: string | null | Date): string {
        if (!dateStr) return "-";
        const date = dateStr instanceof Date ? dateStr : new Date(dateStr);
        return date.toLocaleDateString("id-ID", {
            day: "2-digit",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit"
        }).replace(/\./g, ':').replace(/\s*pukul\s*/gi, ' ');
    }

    function formatFullDateTime(dateInput?: string | null | Date): string {
        if (!dateInput) return "-";
        const date = dateInput instanceof Date ? dateInput : new Date(dateInput);
        
        const dateStr = new Intl.DateTimeFormat("id-ID", {
            weekday: "long",
            day: "2-digit",
            month: "long",
            year: "numeric",
        }).format(date);

        const timeStr = date.toLocaleTimeString("id-ID", {
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
        }).replace(/\./g, ":");

        return `${dateStr} • ${timeStr}`;
    }

    function formatGender(genderStr?: string | null): string {
        if (!genderStr) return "-";
        const g = genderStr.toLowerCase();
        if (g === "male" || g === "laki-laki" || g === 'l') return "Laki-laki";
        if (g === "female" || g === "perempuan" || g === 'p') return "Perempuan";
        return genderStr;
    }

    function getMonthName(monthNumber: number): string {
        const months = [
            "Januari",
            "Februari",
            "Maret",
            "April",
            "Mei",
            "Juni",
            "Juli",
            "Agustus",
            "September",
            "Oktober",
            "November",
            "Desember",
        ];
        return months[monthNumber - 1] || "Bulan Tidak Diketahui";
    }

    function generateYears(count: number = 5): number[] {
        const currentYear = new Date().getFullYear();
        const years = [];
        for (let i = 0; i < count; i++) {
            years.push(currentYear - i);
        }
        return years;
    }

    function formatLocation(location: any): string {
        const parts = [
            location?.address,
            location?.subdistrict?.name || location?.subdistrict_name,
            location?.district?.name || location?.district_name,
            location?.city?.name || location?.city_name,
            location?.province?.name || location?.province_name,
        ].filter(
            (value) => value !== null && value !== undefined && value !== "",
        );

        let formatted = parts.join(", ");
        const postalCode = location?.postal_code || location?.zipcode;

        if (postalCode) {
            formatted = formatted
                ? `${formatted} ${postalCode}`
                : String(postalCode);
        }

        return formatted || "-";
    }

    function formatYearMonth(period?: number | string | null): string {
        if (!period) return "-";
        const p = String(period);
        if (p.length !== 4) return p;
        const year = 2000 + parseInt(p.substring(0, 2), 10);
        const month = parseInt(p.substring(2, 4), 10);
        return `${getMonthName(month)} ${year}`;
    }

    function formatMonthYear(month?: number | null, year?: number | null): string {
        if (!month || !year) return "-";
        return `${getMonthName(month)} ${year}`;
    }

    return {
        formatPrice,
        formatDate,
        formatDateTime,
        formatShortDateTime,
        formatFullDateTime,
        formatGender,
        getMonthName,
        generateYears,
        formatLocation,
        formatYearMonth,
        formatMonthYear,
    };
}
