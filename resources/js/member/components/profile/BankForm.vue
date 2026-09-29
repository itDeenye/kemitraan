<template>
    <div class="card" style="padding: 16px">
        <div v-if="initialLoading" class="text-center pa-6">
            <v-progress-circular indeterminate color="primary" />
            <p class="soft-label mt-3">Memuat formulir...</p>
        </div>

        <v-form
            v-else
            ref="formRef"
            @submit.prevent="submit"
            :disabled="loading"
        >
            <v-autocomplete
                v-model="formData.bank_id"
                :items="referenceBanks"
                item-title="name"
                item-value="id"
                label="Bank *"
                variant="outlined"
                density="comfortable"
                :rules="[(v) => !!v || 'Bank wajib dipilih']"
                class="mb-2"
            />
            <v-text-field
                v-model="formData.account_number"
                label="Nomor Rekening *"
                variant="outlined"
                density="comfortable"
                :rules="[(v) => !!v || 'Nomor rekening wajib diisi']"
                class="mb-2"
            />
            <v-text-field
                v-model="formData.account_name"
                label="Nama Pemilik Rekening *"
                variant="outlined"
                density="comfortable"
                :rules="[(v) => !!v || 'Nama pemilik rekening wajib diisi']"
                class="mb-2"
            />
            <v-text-field
                v-model="formData.city"
                label="Kota Bank (Opsional)"
                variant="outlined"
                density="comfortable"
                class="mb-2"
            />
            <v-text-field
                v-model="formData.branch"
                label="Cabang Bank (Opsional)"
                variant="outlined"
                density="comfortable"
                class="mb-2"
            />
            <v-switch
                v-model="formData.is_active"
                label="Rekening aktif"
                color="green"
                inset
                :disabled="originalIsDefault"
                class="mb-1"
            />
            <div class="d-flex ga-3 mt-4">
                <button
                    type="submit"
                    class="primary-button block"
                    :disabled="loading"
                >
                    {{
                        loading
                            ? "Menyimpan..."
                            : isEdit
                              ? "Perbarui Rekening"
                              : "Tambah Rekening"
                    }}
                </button>
            </div>
        </v-form>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import profileService from "@/member/services/profile.service";
import referenceService from "@/member/services/reference.service";
import type { ReferenceBank } from "@/member/types/profile";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const props = defineProps<{ bankId?: number }>();
const router = useRouter();
const snackbar = useSnackbarStore();
const isEdit = computed(() => !!props.bankId);
const loading = ref(false);
const initialLoading = ref(true);
const formRef = ref<any>(null);
const originalIsDefault = ref(false);
const referenceBanks = ref<ReferenceBank[]>([]);

const formData = reactive({
    bank_id: null as number | null,
    account_number: "",
    account_name: "",
    city: "",
    branch: "",
    is_active: true,
});

async function loadBank() {
    try {
        const references = await referenceService.getBanks();
        if (references.success) referenceBanks.value = references.data;
        if (!props.bankId) return;

        const response = await profileService.getBank(props.bankId);
        if (!response.success) return;
        const bank = response.data;
        formData.bank_id = bank.bank_id;
        formData.account_number = bank.account_number;
        formData.account_name = bank.account_name;
        formData.city = bank.city || "";
        formData.branch = bank.branch || "";
        formData.is_active = bank.is_active;
        originalIsDefault.value = bank.is_default;
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Rekening belum dapat dimuat.",
            "error",
        );
        await router.push("/member/profile/bank");
    } finally {
        initialLoading.value = false;
    }
}

async function submit() {
    if (!formRef.value) return;
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    loading.value = true;
    try {
        const payload = { ...formData };
        const response = props.bankId
            ? await profileService.updateBank(props.bankId, payload)
            : await profileService.createBank(payload);
        if (response.success) {
            snackbar.showMessage(
                props.bankId
                    ? "Rekening berhasil diperbarui"
                    : "Rekening berhasil ditambahkan",
                "success",
            );
            await router.push("/member/profile/bank");
        }
    } catch (error: any) {
        snackbar.showMessage(
            error.response?.data?.message || "Rekening belum dapat disimpan.",
            "error",
        );
    } finally {
        loading.value = false;
    }
}

onMounted(loadBank);
</script>
