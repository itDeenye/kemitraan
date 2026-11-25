import { computed, onBeforeUnmount, onMounted, ref } from "vue";

export function usePaymentUploadCountdown() {
    const remainingSeconds = ref(0);
    const hasDeadline = ref(false);
    let deadline = 0;
    let serverOffset = 0;
    let timer: ReturnType<typeof setInterval> | undefined;

    const tick = () => {
        remainingSeconds.value = hasDeadline.value
            ? Math.max(0, Math.ceil((deadline - Date.now() - serverOffset) / 1000))
            : 0;
    };
    const setDeadline = (expiresAt?: string | null, serverTime?: string | null) => {
        deadline = Date.parse(expiresAt || "");
        const server = Date.parse(serverTime || "");
        serverOffset = Number.isFinite(server) ? server - Date.now() : 0;
        hasDeadline.value = Number.isFinite(deadline);
        tick();
    };
    const expired = computed(() => hasDeadline.value && remainingSeconds.value === 0);
    const countdown = computed(() => {
        const seconds = remainingSeconds.value;
        return [Math.floor(seconds / 3600), Math.floor(seconds / 60) % 60, seconds % 60]
            .map(value => String(value).padStart(2, "0")).join(":");
    });

    onMounted(() => { timer = setInterval(tick, 1000); });
    onBeforeUnmount(() => { if (timer !== undefined) clearInterval(timer); });

    return { countdown, expired, hasDeadline, remainingSeconds, setDeadline, tick };
}
