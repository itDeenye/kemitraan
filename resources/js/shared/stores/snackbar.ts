import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useSnackbarStore = defineStore('snackbar', () => {
    const show = ref(false);
    const text = ref('');
    const color = ref('success');
    const timeout = ref(3000);

    const showMessage = (msg: string, msgColor: string = 'success', msgTimeout: number = 3000) => {
        text.value = msg;
        color.value = msgColor;
        timeout.value = msgTimeout;
        show.value = true;
    };

    const showError = (msg: string, msgTimeout: number = 3000) => {
        showMessage(msg, 'error', msgTimeout);
    };

    return {
        show,
        text,
        color,
        timeout,
        showMessage,
        showError,
    };
});
