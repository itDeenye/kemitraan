<template>
    <v-dialog v-model="dialog" max-width="680" persistent scrollable>
        <v-card class="rounded-xl">
            <v-card-title class="pa-6 border-b d-flex align-center justify-space-between">
                <span class="text-h6 font-weight-bold">Ubah Level Mitra</span>
                <v-btn icon="mdi-close" variant="text" :disabled="submitting" @click="close" />
            </v-card-title>
            <v-card-text class="pa-6">
                <div v-if="loading" class="text-center py-8"><v-progress-circular indeterminate color="primary" /></div>
                <v-form v-else-if="member && options" ref="formRef" :disabled="submitting" @submit.prevent="submit">
                    <p class="font-weight-bold mb-1">{{ member.name }}</p>
                    <p class="text-body-2 text-medium-emphasis mb-5">{{ member.code }} · {{ member.level?.name }}</p>
                    <v-alert v-if="!options.can_change_level" type="warning" variant="tonal" class="mb-5">
                        {{ options.message }}
                    </v-alert>
                    <template v-if="options.can_change_level">
                        <v-select v-model="form.level_id" :items="targetLevels" item-title="name" item-value="id"
                            label="Level Tujuan" variant="outlined" :rules="[required]" @update:model-value="changeLevel" />
                        <v-autocomplete v-model="form.upline_member_id" :items="availableUplines" item-title="label" item-value="id"
                            label="Upline Tujuan" variant="outlined" :disabled="selectedLevelCode === 'DST'"
                            :rules="selectedLevelCode === 'DST' ? [] : [required]" />
                    </template>
                </v-form>
            </v-card-text>
            <v-card-actions class="pa-6 border-t justify-end">
                <v-btn variant="outlined" :disabled="submitting" @click="close">Batal</v-btn>
                <v-btn color="primary" variant="flat" :loading="submitting"
                    :disabled="loading || submitting || !options?.can_change_level" @click="submit">Simpan Level</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import api from '@/shared/services/api';
import { useSnackbarStore } from '@/shared/stores/snackbar';
import type { Member } from '@/admin/types/member';

type PlacementOptions = {
    can_change_level: boolean; message: string;
    levels: { id: number; code: string; name: string }[];
    uplines: { id: number; label: string; level_code: string }[];
};
const dialog = ref(false);
const loading = ref(false);
const submitting = ref(false);
const member = ref<Member | null>(null);
const options = ref<PlacementOptions | null>(null);
const formRef = ref();
const form = ref({ level_id: null as number | null, upline_member_id: null as number | null });
const targetLevels = computed(() => options.value?.levels.filter(level => level.id !== member.value?.level?.id) ?? []);
const selectedLevelCode = computed(() => options.value?.levels.find(level => level.id === form.value.level_id)?.code);
const availableUplines = computed(() => options.value?.uplines.filter(upline => selectedLevelCode.value === 'AGT'
    ? upline.level_code === 'DST' : selectedLevelCode.value === 'RSL' && ['DST', 'AGT'].includes(upline.level_code)) ?? []);
const required = (value: unknown) => !!String(value ?? '').trim() || 'Wajib diisi';
const snackbar = useSnackbarStore();
const emit = defineEmits<{ updated: [] }>();
let version = 0;
const close = () => {
    if (submitting.value) return;
    version++;
    dialog.value = false;
    loading.value = false;
    member.value = null;
    options.value = null;
};
const open = async (id: number) => {
    if (submitting.value) return;
    close();
    const requestVersion = version;
    dialog.value = loading.value = true;
    form.value = { level_id: null, upline_member_id: null };
    try {
        const [detail, choices] = await Promise.all([
            api.get(`/admin/partnership/members/${id}`), api.get(`/admin/partnership/members/${id}/level-change-options`),
        ]);
        if (version !== requestVersion) return;
        if (detail.data.success !== true || choices.data.success !== true) throw new Error('Gagal memuat pilihan perubahan level.');
        member.value = detail.data.data;
        options.value = choices.data.data;
    } catch (error: any) {
        if (version !== requestVersion) return;
        snackbar.showMessage(error.response?.data?.message || error.message || 'Gagal memuat data mitra.', 'error');
        close();
    } finally {
        if (version === requestVersion) loading.value = false;
    }
};
const changeLevel = () => {
    if (!availableUplines.value.some(item => item.id === form.value.upline_member_id)) form.value.upline_member_id = null;
};
const submit = async () => {
    if (submitting.value || loading.value || !member.value || !options.value?.can_change_level) return;
    submitting.value = true;
    try {
        const { valid } = await formRef.value?.validate() ?? { valid: false };
        if (!valid) return;
        const { data } = await api.post(`/admin/partnership/members/${member.value.id}/change-level`, {
            level_id: form.value.level_id, upline_member_id: selectedLevelCode.value === 'DST' ? null : form.value.upline_member_id,
        });
        if (data.success !== true) throw new Error(data.message || 'Gagal mengubah level mitra.');
        snackbar.showMessage(data.message || 'Level mitra berhasil diubah.', 'success');
        emit('updated');
        submitting.value = false;
        close();
    } catch (error: any) {
        snackbar.showMessage(error.response?.data?.message || error.message || 'Gagal mengubah level mitra.', 'error');
    } finally {
        submitting.value = false;
    }
};
onBeforeUnmount(() => { version++; });
defineExpose({ open });
</script>
