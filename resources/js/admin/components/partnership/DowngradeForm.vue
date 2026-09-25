<template>
    <v-dialog v-model="dialog" max-width="600" persistent>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="error" size="28"
                        >mdi-arrow-down-bold-circle-outline</v-icon
                    >
                    <span class="text-h6 font-weight-bold"
                        >Jadwalkan Downgrade</span
                    >
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    color="medium-emphasis"
                    @click="close"
                    density="comfortable"
                ></v-btn>
            </v-card-title>

            <v-card-text class="pa-6">
                <v-alert
                    type="warning"
                    variant="tonal"
                    class="mb-6 text-body-2"
                    icon="mdi-alert"
                >
                    Pastikan telah melakukan konfirmasi kepada mitra
                    bersangkutan sebelum menjadwalkan downgrade.
                </v-alert>

                <v-alert
                    type="info"
                    variant="tonal"
                    class="mb-6 text-body-2"
                    icon="mdi-account-switch-outline"
                >
                    Jaringan bawahan akan disesuaikan otomatis tanpa mengubah
                    susunan Agent dan Reseller yang masih valid.
                </v-alert>

                <v-form
                    ref="formRef"
                    v-model="isFormValid"
                    @submit.prevent="submit"
                >
                    <v-autocomplete
                        v-model="selectedMember"
                        :items="members"
                        item-title="name"
                        item-value="id"
                        label="Mitra yang Akan Di-downgrade"
                        variant="outlined"
                        density="comfortable"
                        class="mb-4"
                        :loading="loadingMembers"
                        @update:search="searchMembers"
                        @update:modelValue="onMemberChange"
                        placeholder="Ketik nama atau kode mitra..."
                        hide-details="auto"
                        :rules="[rules.requiredSelect('Mitra')]"
                        return-object
                        :custom-filter="customFilter"
                    >
                        <template v-slot:item="{ props, item }">
                            <v-list-item
                                v-bind="props"
                                :title="item.raw.name"
                                :subtitle="item.raw.code"
                            >
                            </v-list-item>
                        </template>
                    </v-autocomplete>

                    <v-row>
                        <v-col cols="12" md="6">
                            <v-select
                                v-model="form.to_level_id"
                                :items="filteredLevelOptions"
                                item-title="label"
                                item-value="value"
                                label="Level Tujuan"
                                variant="outlined"
                                density="comfortable"
                                class="mb-4"
                                hide-details="auto"
                                :rules="[rules.requiredSelect('Level Tujuan')]"
                                :disabled="!selectedMember"
                                @update:modelValue="onLevelChange"
                            ></v-select>
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.effective_date"
                                type="date"
                                label="Tanggal Efektif"
                                variant="outlined"
                                density="comfortable"
                                class="mb-4"
                                hide-details="auto"
                                :rules="[rules.required('Tanggal Efektif')]"
                                disabled
                            ></v-text-field>
                        </v-col>
                    </v-row>

                    <v-autocomplete
                        v-if="form.member_id && form.to_level_id"
                        v-model="form.to_parent_member_id"
                        :items="parents"
                        item-title="name"
                        item-value="id"
                        label="Sponsor / Parent Baru"
                        variant="outlined"
                        density="comfortable"
                        class="mb-4"
                        :loading="loadingParents"
                        @update:search="searchParents"
                        placeholder="Ketik nama atau kode parent baru..."
                        hide-details="auto"
                        :rules="[rules.requiredSelect('Sponsor / Parent Baru')]"
                        :custom-filter="customFilter"
                    >
                        <template v-slot:item="{ props, item }">
                            <v-list-item
                                v-bind="props"
                                :title="item.raw.name"
                                :subtitle="item.raw.code"
                            >
                            </v-list-item>
                        </template>
                    </v-autocomplete>

                    <v-textarea
                        v-model="form.note"
                        label="Alasan Downgrade"
                        variant="outlined"
                        density="comfortable"
                        rows="3"
                        hide-details="auto"
                        placeholder="Hasil evaluasi dan negosiasi manajemen..."
                        :rules="[rules.required('Alasan Downgrade')]"
                    ></v-textarea>
                </v-form>
            </v-card-text>

            <v-card-actions class="pa-6 pt-0">
                <v-spacer></v-spacer>
                <v-btn
                    variant="outlined"
                    color="medium-emphasis"
                    class="text-none px-6"
                    @click="close"
                    :disabled="loading"
                >
                    Batal
                </v-btn>
                <v-btn
                    color="error"
                    variant="flat"
                    class="text-none px-6 ml-3"
                    @click="submit"
                    :loading="loading"
                    :disabled="!isFormValid"
                >
                    Jadwalkan Downgrade
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from "vue";
import api from "@/shared/services/api";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const emit = defineEmits(["saved"]);
const snackbar = useSnackbarStore();

const dialog = ref(false);
const loading = ref(false);
const formRef = ref();
const isFormValid = ref(false);

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
    requiredSelect: (field: string) => (v: any) =>
        !!v || `${field} wajib dipilih`,
};

const customFilter = (value: string, query: string, item: any) => {
    if (!query) return true;
    const q = query.toLowerCase();
    const name = item?.raw?.name?.toLowerCase() || "";
    const code = item?.raw?.code?.toLowerCase() || "";
    return name.includes(q) || code.includes(q);
};

const form = reactive({
    member_id: null as number | null,
    to_level_id: null as number | null,
    to_parent_member_id: null as number | null,
    effective_date: "",
    note: "",
});

const levelOptions = [
    { value: 1, label: "Distributor" },
    { value: 2, label: "Agent" },
    { value: 3, label: "Reseller" },
];

const members = ref<any[]>([]);
const loadingMembers = ref(false);

const parents = ref<any[]>([]);
const loadingParents = ref(false);

let searchMembersTimeout: any = null;
let searchParentsTimeout: any = null;

const searchMembers = (query: string) => {
    if (query === null || query === undefined) query = "";
    clearTimeout(searchMembersTimeout);
    searchMembersTimeout = setTimeout(async () => {
        loadingMembers.value = true;
        try {
            const { data } = await api.get("/admin/partnership/members", {
                params: {
                    search: query,
                    limit: 20,
                },
            });
            const results = data?.data?.results;
            const items = data?.data;
            members.value = Array.isArray(results)
                ? results
                : Array.isArray(items)
                  ? items
                  : [];
        } catch (e: any) {
            console.error("Failed to fetch members", e);
        } finally {
            loadingMembers.value = false;
        }
    }, 500);
};

const selectedMember = ref<any>(null);

const filteredLevelOptions = computed(() => {
    if (!selectedMember.value || !selectedMember.value.level) return [];
    return levelOptions.filter(
        (opt) => opt.value === selectedMember.value.level.id + 1,
    );
});

const onMemberChange = () => {
    form.member_id = selectedMember.value?.id || null;
    form.to_level_id = null;
    form.to_parent_member_id = null;
    parents.value = [];
};

const onLevelChange = () => {
    form.to_parent_member_id = null;
    parents.value = [];
    if (form.member_id && form.to_level_id) {
        searchParents("");
    }
};

const searchParents = (query: string) => {
    if (!form.member_id || !form.to_level_id) {
        snackbar.showMessage(
            "Pilih Mitra dan Level Tujuan terlebih dahulu",
            "warning",
        );
        return;
    }
    const memberId = form.member_id;

    if (query === null || query === undefined) query = "";
    clearTimeout(searchParentsTimeout);
    searchParentsTimeout = setTimeout(async () => {
        loadingParents.value = true;
        try {
            const { data } = await api.get(
                "/admin/partnership/downgrades/sponsor-options",
                {
                    params: {
                        member_id: memberId,
                        to_level_id: form.to_level_id,
                        search: query,
                        limit: 20,
                    },
                },
            );
            const responseData = data?.data;
            parents.value = Array.isArray(responseData?.results)
                ? responseData.results
                : Array.isArray(responseData)
                  ? responseData
                  : [];
        } catch (e: any) {
            console.error("Failed to fetch parents", e);
            snackbar.showMessage(
                e.response?.data?.message || "Gagal memuat data sponsor",
                "error",
            );
        } finally {
            loadingParents.value = false;
        }
    }, 500);
};

const getNextMonthFirstDay = () => {
    const today = new Date();
    const nextMonth = new Date(today.getFullYear(), today.getMonth() + 1, 1);
    const yyyy = nextMonth.getFullYear();
    const mm = String(nextMonth.getMonth() + 1).padStart(2, "0");
    const dd = String(nextMonth.getDate()).padStart(2, "0");
    return `${yyyy}-${mm}-${dd}`;
};

const open = async () => {
    dialog.value = true;
    loading.value = true;
    members.value = [];
    parents.value = [];
    selectedMember.value = null;
    form.member_id = null;
    form.to_level_id = null;
    form.to_parent_member_id = null;
    form.effective_date = getNextMonthFirstDay();
    form.note = "";

    searchMembers("");

    loading.value = false;
};

const close = () => {
    dialog.value = false;
    formRef.value?.reset();
};

const submit = async () => {
    const { valid } = await formRef.value.validate();
    if (!valid) return;

    loading.value = true;
    try {
        const memberId = form.member_id;

        const payload = {
            to_level_id: form.to_level_id,
            to_parent_member_id: form.to_parent_member_id,
            effective_date: form.effective_date,
            note: form.note,
        };

        await api.post(
            `/admin/partnership/members/${memberId}/downgrade`,
            payload,
        );

        snackbar.showMessage("Downgrade berhasil dijadwalkan.", "success");
        emit("saved");
        close();
    } catch (e: any) {
        snackbar.showMessage(
            e.response?.data?.message ||
                "Terjadi kesalahan saat menyimpan data.",
            "error",
        );
    } finally {
        loading.value = false;
    }
};

defineExpose({
    open,
    close,
});
</script>
