<template>
    <v-dialog v-model="dialog" max-width="1000" persistent scrollable>
        <v-card class="rounded-xl">
            <v-card-title class="pa-6 border-b d-flex align-center justify-space-between">
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary">mdi-account-edit-outline</v-icon>
                    <span class="text-h6 font-weight-bold">Edit Pengajuan Registrasi</span>
                </div>
                <v-btn icon="mdi-close" variant="text" :disabled="busy" @click="close" />
            </v-card-title>
            <v-card-text class="pa-0 bg-grey-lighten-4">
                <div v-if="loading" class="text-center py-12">
                    <v-progress-circular indeterminate color="primary" />
                </div>
                <v-form v-else ref="formRef" class="pa-6" :disabled="submitting" @submit.prevent="submit">
                    <v-alert type="info" variant="tonal" class="mb-6">
                        Koreksi data sebelum persetujuan. Menyimpan perubahan tidak menyetujui pengajuan
                        atau membuat customer di Aplikasi Internal.
                    </v-alert>
                    <v-card variant="flat" class="registration-section mb-6 rounded-lg">
                        <v-card-title class="pa-4 border-b text-subtitle-1 font-weight-bold">Data Calon Mitra</v-card-title>
                        <v-card-text class="pa-4">
                            <v-row>
                                <v-col cols="12" md="6">
                                    <v-select v-model="form.level_id" :items="levels" item-title="name" item-value="id"
                                        label="Level" variant="outlined" :rules="[required]" :error-messages="errors.level_id"
                                        @update:model-value="changeLevel" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-autocomplete v-model="form.upline_member_id" :items="availableUplines" item-title="label" item-value="id"
                                        label="Upline" variant="outlined" :disabled="selectedLevelCode === 'DST'"
                                        :hint="selectedLevelCode === 'DST' ? 'Distributor tidak memiliki upline.' : 'Pilih upline yang sesuai dengan level tujuan.'"
                                        persistent-hint :rules="selectedLevelCode === 'DST' ? [] : [required]" :error-messages="errors.upline_member_id" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field v-model="form.name" label="Nama Lengkap" variant="outlined"
                                        maxlength="100" :rules="[required]" :error-messages="errors.name" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field v-model="form.mobile_phone" label="Nomor WhatsApp" variant="outlined"
                                        type="tel" hint="Contoh: +6281234567890" persistent-hint :rules="[required]"
                                        :error-messages="errors.mobile_phone" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field v-model="form.email" label="Email" type="email" variant="outlined"
                                        maxlength="100" :error-messages="errors.email" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-select v-model="form.gender" label="Jenis Kelamin" variant="outlined"
                                        :items="['Laki-laki', 'Perempuan']" :rules="[required]" :error-messages="errors.gender" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field v-model="form.birth_date" label="Tanggal Lahir" type="date" variant="outlined"
                                        :max="maxBirthDate" :rules="[required]" :error-messages="errors.birth_date" />
                                </v-col>
                            </v-row>
                            <v-alert v-if="networkChanged && hadDistributorApproval" type="warning" variant="tonal" class="mb-4">
                                Persetujuan Distributor lama tidak berlaku untuk perubahan jaringan. Jika tetap berada
                                di bawah Agen Utama, pengajuan perlu persetujuan Distributor kembali.
                            </v-alert>
                        </v-card-text>
                    </v-card>
                    <v-card variant="flat" class="registration-section mb-6 rounded-lg">
                        <v-card-title class="pa-4 border-b text-subtitle-1 font-weight-bold">Identitas & Dokumen</v-card-title>
                        <v-card-text class="pa-4">
                            <v-row>
                                <v-col cols="12" md="6">
                                    <v-select v-model="form.identity_type" label="Jenis Identitas" variant="outlined"
                                        :items="identityOptions" item-title="title" item-value="value"
                                        :rules="[required]" :error-messages="errors.identity_type" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field v-model="form.identity_no" label="Nomor Identitas" variant="outlined"
                                        :maxlength="identitySettings.maxLength" :hint="identitySettings.hint" persistent-hint
                                        :inputmode="form.identity_type === 'PASPOR' ? 'text' : 'numeric'"
                                        :rules="[required, validateIdentityNumber]" :error-messages="errors.identity_no" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field v-model="form.nib" label="NIB" variant="outlined" maxlength="20" :error-messages="errors.nib" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field v-model="form.npwp" label="NPWP" variant="outlined" maxlength="30" :error-messages="errors.npwp" />
                                </v-col>
                            </v-row>
                            <h4 class="text-subtitle-2 font-weight-bold mb-4">Foto & Dokumen</h4>
                            <v-row :inert="submitting || undefined">
                                <v-col v-for="media in mediaFields" :key="media.field" cols="12" sm="6" md="4">
                                    <div class="registration-media-item d-flex align-center ga-3">
                                        <MediaUpload v-model="form[media.field]" :collection="media.collection" :size="92"
                                            class="flex-shrink-0" :rounded="true" :fallback-icon="media.icon" accept="image/jpeg,image/png,image/webp"
                                            @uploading="mediaUploads[media.field] = $event" />
                                        <div class="registration-media-copy">
                                            <div class="text-body-2 font-weight-medium">{{ media.label }}</div>
                                            <div class="text-caption text-medium-emphasis">JPG, PNG, atau WEBP</div>
                                            <div v-if="errors[media.field]" class="text-error text-caption mt-2">{{ errors[media.field]?.join(' ') }}</div>
                                        </div>
                                    </div>
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>
                    <v-card variant="flat" class="registration-section mb-6 rounded-lg">
                        <v-card-title class="pa-4 border-b text-subtitle-1 font-weight-bold">Alamat Pengajuan</v-card-title>
                        <v-card-text class="pa-4">
                            <v-textarea v-model="form.address" label="Alamat Lengkap" variant="outlined" rows="2"
                                maxlength="255" :rules="[required]" :error-messages="errors.address" />
                            <v-row>
                                <v-col cols="12" md="6">
                                    <v-autocomplete v-model="form.province_id" label="Provinsi" variant="outlined"
                                        :items="provinces" item-title="name" item-value="id" :loading="loadingProvinces"
                                        :rules="[required]" :error-messages="errors.province_id" @update:model-value="changeProvince" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-autocomplete v-model="form.city_id" label="Kota / Kabupaten" variant="outlined"
                                        :items="cities" item-title="name" item-value="id" :loading="loadingCities" :disabled="!form.province_id"
                                        :rules="[required]" :error-messages="errors.city_id" @update:model-value="changeCity" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-autocomplete v-model="form.district_id" label="Kecamatan" variant="outlined"
                                        :items="districts" item-title="name" item-value="id" :loading="loadingDistricts" :disabled="!form.city_id"
                                        :rules="[required]" :error-messages="errors.district_id" @update:model-value="changeDistrict" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-autocomplete v-model="form.subdistrict_id" label="Kelurahan / Desa" variant="outlined"
                                        :items="subdistricts" item-title="name" item-value="id" :loading="loadingSubdistricts" :disabled="!form.district_id"
                                        :rules="[required]" :error-messages="errors.subdistrict_id" />
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>
                    <v-card variant="flat" class="registration-section rounded-lg">
                        <div class="d-flex justify-space-between align-center pa-4 border-b ga-3 flex-wrap">
                            <h3 class="text-subtitle-1 font-weight-bold">Rekening Bank</h3>
                            <v-btn variant="outlined" color="primary" prepend-icon="mdi-plus" size="small"
                                :disabled="submitting || form.bank_accounts.length >= 10" @click="addBank">Tambah Rekening</v-btn>
                        </div>
                        <v-card-text class="pa-4">
                            <v-alert v-if="errors.bank_accounts" type="error" variant="tonal" class="mb-4">{{ errors.bank_accounts.join(' ') }}</v-alert>
                            <p v-if="!form.bank_accounts.length" class="text-body-2 text-medium-emphasis">Belum ada rekening.</p>
                            <v-card v-for="(bank, index) in form.bank_accounts" :key="index" variant="outlined" class="pa-4 mb-4 rounded-lg">
                                <div class="d-flex justify-space-between align-center mb-3">
                                    <span class="text-body-2 font-weight-bold">Rekening {{ index + 1 }}</span>
                                    <v-btn icon="mdi-delete-outline" color="error" variant="text" size="small" :disabled="submitting" @click="removeBank(index)" />
                                </div>
                                <v-row>
                                    <v-col cols="12" md="4">
                                        <v-autocomplete v-model="bank.bank_id" label="Bank" variant="outlined" :items="banks"
                                            item-title="name" item-value="id" :rules="[required]" :error-messages="errors[`bank_accounts.${index}.bank_id`]" />
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <v-text-field v-model="bank.account_name" label="Nama Pemilik Rekening" variant="outlined"
                                            maxlength="50" :rules="[required]" :error-messages="errors[`bank_accounts.${index}.account_name`]" />
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <v-text-field v-model="bank.account_number" label="Nomor Rekening" variant="outlined" inputmode="numeric"
                                            maxlength="50" :rules="[required]" :error-messages="errors[`bank_accounts.${index}.account_number`]" />
                                    </v-col>
                                    <v-col cols="12" md="6">
                                        <v-text-field v-model="bank.city" label="Kota Bank" variant="outlined" maxlength="50" :error-messages="errors[`bank_accounts.${index}.city`]" />
                                    </v-col>
                                    <v-col cols="12" md="6">
                                        <v-text-field v-model="bank.branch" label="Cabang Bank" variant="outlined" maxlength="50" :error-messages="errors[`bank_accounts.${index}.branch`]" />
                                    </v-col>
                                </v-row>
                                <div class="d-flex ga-6 flex-wrap">
                                    <v-checkbox v-model="bank.is_active" label="Aktif" hide-details @update:model-value="normalizeDefaultBank" />
                                    <v-checkbox :model-value="bank.is_default" label="Rekening Utama" hide-details :disabled="!bank.is_active"
                                        :error-messages="errors[`bank_accounts.${index}.is_default`]" @update:model-value="setDefaultBank(index)" />
                                </div>
                            </v-card>
                        </v-card-text>
                    </v-card>
                </v-form>
            </v-card-text>
            <v-card-actions class="pa-6 border-t justify-end">
                <v-btn variant="outlined" :disabled="busy" @click="close">Batal</v-btn>
                <v-btn color="primary" variant="flat" :loading="submitting" :disabled="loading || busy || !currentId"
                    @click="submit">Simpan Perubahan</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue";
import api from "@/shared/services/api";
import referenceService, { type ReferenceBank } from "@/shared/services/reference.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";
import { useRegionCascade } from "@/shared/composables/useRegionCascade";
import { identityNumberRule, identityNumberSettings, memberIdentityOptions } from "@/shared/utils/member-identity";
import MediaUpload from "@/shared/components/MediaUpload.vue";

type BankAccount = {
    bank_id: number | null; account_name: string; account_number: string;
    city: string; branch: string; is_active: boolean; is_default: boolean;
};
type RegistrationForm = {
    level_id: number | null; upline_member_id: number | null;
    name: string; email: string; mobile_phone: string; gender: string; birth_date: string;
    identity_type: string; identity_no: string; nib: string; npwp: string;
    image_url: string; identity_image_url: string; payment_proof_image_url: string;
    address: string; province_id: string | null; city_id: string | null;
    district_id: string | null; subdistrict_id: number | null; country_id: number;
    bank_accounts: BankAccount[];
};
const emptyForm = (): RegistrationForm => ({
    level_id: null, upline_member_id: null,
    name: "", email: "", mobile_phone: "", gender: "", birth_date: "",
    identity_type: "KTP", identity_no: "", nib: "", npwp: "",
    image_url: "", identity_image_url: "", payment_proof_image_url: "",
    address: "", province_id: null, city_id: null, district_id: null, subdistrict_id: null,
    country_id: 1, bank_accounts: [],
});
const dialog = ref(false);
const loading = ref(false);
const submitting = ref(false);
const currentId = ref<number | null>(null);
const form = ref<RegistrationForm>(emptyForm());
const formRef = ref();
const errors = ref<Record<string, string[]>>({});
const levels = ref<{ id: number; code: string; name: string }[]>([]);
const uplines = ref<{ id: number; label: string; level_code: string }[]>([]);
const originalNetwork = ref({ level_id: 0, upline_member_id: 0 });
const hadDistributorApproval = ref(false);
const selectedLevelCode = computed(() => levels.value.find(level => level.id === form.value.level_id)?.code);
const availableUplines = computed(() => uplines.value.filter(upline =>
    selectedLevelCode.value === 'AGT' ? upline.level_code === 'DST'
        : selectedLevelCode.value === 'RSL' && ['DST', 'AGT'].includes(upline.level_code)));
const networkChanged = computed(() => form.value.level_id !== originalNetwork.value.level_id
    || (form.value.upline_member_id ?? 0) !== originalNetwork.value.upline_member_id);
const banks = ref<ReferenceBank[]>([]);
const previousIdentity = ref({ type: "KTP", number: "" });
const mediaUploads = ref<Record<string, boolean>>({});
const busy = computed(() => submitting.value || Object.values(mediaUploads.value).some(Boolean));
const mediaFields = [
    { field: "image_url", label: "Foto Profil", collection: "profile", icon: "mdi-account-outline" },
    { field: "identity_image_url", label: "Dokumen Identitas", collection: "general", icon: "mdi-card-account-details-outline" },
    { field: "payment_proof_image_url", label: "Bukti Transfer Pendaftaran", collection: "payment_proof", icon: "mdi-receipt-text-check-outline" },
] as const;
const identityOptions = memberIdentityOptions();
const identitySettings = computed(() => identityNumberSettings(form.value.identity_type));
const validateIdentityNumber = (value: unknown) => identityNumberRule(form.value.identity_type, value, previousIdentity.value);
const required = (value: unknown) => (value !== null && value !== undefined && String(value).trim() !== "") || "Wajib diisi";
const yesterday = new Date();
yesterday.setDate(yesterday.getDate() - 1);
const maxBirthDate = [yesterday.getFullYear(), String(yesterday.getMonth() + 1).padStart(2, "0"), String(yesterday.getDate()).padStart(2, "0")].join("-");
const emit = defineEmits<{ updated: [] }>();
const snackbar = useSnackbarStore();
const {
    provinces, cities, districts, subdistricts,
    loadingProvinces, loadingCities, loadingDistricts, loadingSubdistricts,
    fetchProvinces, initFromExisting, reset: resetRegions,
    onProvinceChange, onCityChange, onDistrictChange,
} = useRegionCascade();
let requestVersion = 0;

const reset = () => {
    requestVersion++;
    resetRegions();
    form.value = emptyForm();
    currentId.value = null;
    errors.value = {};
    mediaUploads.value = {};
    loading.value = false;
};
const close = () => {
    if (busy.value) return;
    dialog.value = false;
    reset();
};
const open = async (id: number) => {
    if (busy.value) return;
    reset();
    const version = requestVersion;
    currentId.value = id;
    dialog.value = true;
    loading.value = true;
    try {
        const { data } = await api.get(`/admin/partnership/registrations/${id}`);
        if (version !== requestVersion) return;
        const registration = data.data;
        if (data.success !== true || registration?.can_edit !== true || registration.status?.code !== "requested") {
            throw new Error(data.message || "Pengajuan ini tidak dapat diedit lagi.");
        }
        const applicant = registration.applicant ?? {};
        const address = registration.address ?? {};
        const identity = registration.identity ?? {};
        previousIdentity.value = { type: identity.type || "KTP", number: identity.number || "" };
        originalNetwork.value = { level_id: Number(registration.level?.id), upline_member_id: Number(registration.sponsor?.id) || 0 };
        hadDistributorApproval.value = registration.distributor_approval?.status?.code === 'approved';
        form.value = {
            level_id: originalNetwork.value.level_id, upline_member_id: originalNetwork.value.upline_member_id || null,
            name: applicant.name || "", email: applicant.email || "", mobile_phone: applicant.mobile_phone || "",
            gender: applicant.gender || "", birth_date: applicant.birth_date?.slice(0, 10) || "",
            identity_type: previousIdentity.value.type, identity_no: previousIdentity.value.number,
            nib: identity.nib ?? registration.nib ?? "", npwp: identity.npwp ?? registration.npwp ?? "",
            image_url: registration.image_url || "", identity_image_url: identity.image_url || "",
            payment_proof_image_url: registration.payment_proof_image_url || "",
            address: address.address || "", province_id: address.province_id ? String(address.province_id) : null,
            city_id: address.city_id ? String(address.city_id) : null, district_id: address.district_id ? String(address.district_id) : null,
            subdistrict_id: address.subdistrict_id ? Number(address.subdistrict_id) : null,
            country_id: Number(address.country_id) || 1,
            bank_accounts: (registration.bank_accounts ?? []).map((bank: BankAccount) => ({
                bank_id: bank.bank_id ? Number(bank.bank_id) : null, account_name: bank.account_name || "",
                account_number: bank.account_number || "", city: bank.city || "", branch: bank.branch || "",
                is_active: Boolean(bank.is_active), is_default: Boolean(bank.is_default),
            })),
        };
        const [, bankOptions, , options] = await Promise.all([
            fetchProvinces(), referenceService.getBanks(), initFromExisting(form.value), api.get('/admin/partnership/registrations/options'),
        ]);
        if (version === requestVersion) {
            if (options.data.success !== true) throw new Error(options.data.message || 'Gagal memuat pilihan level/upline.');
            banks.value = bankOptions;
            levels.value = options.data.data.edit_levels;
            uplines.value = options.data.data.edit_uplines;
        }
    } catch (error: any) {
        if (version !== requestVersion) return;
        snackbar.showMessage(error.response?.data?.message || error.message || "Gagal memuat pengajuan.", "error");
        close();
    } finally {
        if (version === requestVersion) loading.value = false;
    }
};
const changeLevel = () => {
    if (!availableUplines.value.some(upline => upline.id === form.value.upline_member_id)) form.value.upline_member_id = null;
};
const changeProvince = async (value: string | null) => {
    form.value.city_id = form.value.district_id = null;
    form.value.subdistrict_id = null;
    await onProvinceChange(value);
};
const changeCity = async (value: string | null) => {
    form.value.district_id = null;
    form.value.subdistrict_id = null;
    await onCityChange(value);
};
const changeDistrict = async (value: string | null) => {
    form.value.subdistrict_id = null;
    await onDistrictChange(value);
};
const normalizeDefaultBank = () => {
    const active = form.value.bank_accounts.filter(bank => bank.is_active);
    const primary = active.find(bank => bank.is_default) ?? active[0];
    form.value.bank_accounts.forEach(bank => { bank.is_default = bank === primary; });
};
const addBank = () => {
    if (submitting.value || form.value.bank_accounts.length >= 10) return;
    form.value.bank_accounts.push({ bank_id: null, account_name: "", account_number: "", city: "", branch: "", is_active: true, is_default: false });
    normalizeDefaultBank();
};
const removeBank = (index: number) => {
    if (submitting.value) return;
    form.value.bank_accounts.splice(index, 1);
    normalizeDefaultBank();
};
const setDefaultBank = (index: number) => {
    if (submitting.value || !form.value.bank_accounts[index]?.is_active) return;
    form.value.bank_accounts.forEach((bank, bankIndex) => { bank.is_default = bankIndex === index; });
};
const submit = async () => {
    if (busy.value || loading.value || !dialog.value || !currentId.value) return;
    submitting.value = true;
    errors.value = {};
    try {
        const { valid } = await formRef.value?.validate() ?? { valid: false };
        if (!valid) return;
        const value = form.value;
        const payload = {
            level_id: value.level_id, upline_member_id: selectedLevelCode.value === 'DST' ? null : value.upline_member_id,
            name: value.name, email: value.email || null, mobile_phone: value.mobile_phone,
            gender: value.gender, birth_date: value.birth_date, identity_type: value.identity_type,
            identity_no: value.identity_no, nib: value.nib || null, npwp: value.npwp || null,
            image_url: value.image_url, identity_image_url: value.identity_image_url,
            payment_proof_image_url: value.payment_proof_image_url, address: value.address,
            province_id: value.province_id === null ? null : String(value.province_id),
            city_id: value.city_id === null ? null : String(value.city_id),
            district_id: value.district_id === null ? null : String(value.district_id),
            subdistrict_id: value.subdistrict_id, country_id: value.country_id,
            bank_accounts: value.bank_accounts.map(bank => ({
                bank_id: bank.bank_id, account_name: bank.account_name, account_number: bank.account_number,
                city: bank.city || null, branch: bank.branch || null,
                is_active: bank.is_active, is_default: bank.is_default,
            })),
        };
        const { data } = await api.patch(`/admin/partnership/registrations/${currentId.value}`, payload);
        if (data.success !== true) throw new Error(data.message || "Gagal menyimpan pengajuan.");
        snackbar.showMessage(data.message || "Data pengajuan mitra berhasil diperbarui.", "success");
        emit("updated");
        submitting.value = false;
        close();
    } catch (error: any) {
        errors.value = error.response?.data?.errors ?? {};
        snackbar.showMessage(error.response?.data?.message || error.message || "Gagal menyimpan pengajuan.", "error");
    } finally {
        submitting.value = false;
    }
};

onBeforeUnmount(() => { requestVersion++; resetRegions(); });
defineExpose({ open, close });
</script>

<style scoped>
.registration-media-item {
    min-height: 96px;
}

.registration-media-copy {
    min-width: 0;
}
</style>
