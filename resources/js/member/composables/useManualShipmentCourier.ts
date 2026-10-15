import { computed, reactive, watch } from "vue";
import type { SaleShipPayload } from "../types/sale";

export interface ManualShipmentQuote {
    courier_code: string;
    courier_name: string;
    service_type: string;
    cost: number;
    display_name?: string;
}

export function useManualShipmentCourier() {
    const maximumCost = 4_294_967_295;
    const courierForm = reactive({
        mode: "automatic" as "automatic" | "manual",
        selected_courier: null as ManualShipmentQuote | null,
        name: "",
        service: "",
        service_type: "MANUAL",
        cost: null as number | string | null,
    });

    watch(
        [() => courierForm.mode, () => courierForm.selected_courier],
        () => {
            if (courierForm.mode === "automatic" && courierForm.selected_courier) {
                courierForm.cost = courierForm.selected_courier.cost;
            }
        },
    );

    const courierError = computed(() => {
        if (courierForm.mode === "automatic" && !courierForm.selected_courier) {
            return "Kurir dan layanan wajib dipilih.";
        }

        const name = courierForm.mode === "manual"
            ? courierForm.name.trim()
            : courierForm.selected_courier?.courier_code.trim();
        const service = courierForm.mode === "manual"
            ? courierForm.service.trim()
            : courierForm.selected_courier?.courier_name.trim();
        const serviceType = courierForm.mode === "manual"
            ? courierForm.service_type.trim()
            : courierForm.selected_courier?.service_type.trim();

        if (!name || name.length > 30) {
            return "Nama kurir wajib diisi, maksimal 30 karakter.";
        }
        if (!service || service.length > 100) {
            return "Layanan kurir wajib diisi, maksimal 100 karakter.";
        }
        if (!serviceType || serviceType.length > 30) {
            return "Jenis layanan kurir tidak valid.";
        }

        const cost = courierForm.cost;
        if (cost === null || String(cost).trim() === ""
            || !Number.isSafeInteger(Number(cost)) || Number(cost) < 0) {
            return "Ongkos kirim wajib diisi dengan rupiah bulat, minimal 0.";
        }
        if (Number(cost) > maximumCost) {
            return "Ongkos kirim melebihi batas yang dapat disimpan.";
        }

        return null;
    });

    const courierPayload = computed<SaleShipPayload["courier"] | null>(() => {
        if (courierError.value) return null;

        return courierForm.mode === "manual"
            ? {
                name: courierForm.name.trim(),
                service: courierForm.service.trim(),
                service_type: courierForm.service_type.trim(),
                cost: Number(courierForm.cost),
            }
            : {
                name: courierForm.selected_courier!.courier_code,
                service: courierForm.selected_courier!.courier_name,
                service_type: courierForm.selected_courier!.service_type,
                cost: Number(courierForm.cost),
            };
    });

    return { courierForm, courierPayload, courierError, maximumCost };
}
