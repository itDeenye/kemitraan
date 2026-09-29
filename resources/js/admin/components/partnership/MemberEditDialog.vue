<template>
    <v-dialog v-model="dialog" max-width="900" persistent scrollable>
        <v-card class="rounded-xl">
            <v-card-title
                class="pa-6 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary">mdi-account-edit</v-icon>
                    <span class="text-h6 font-weight-bold">Edit Mitra</span>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    @click="close"
                    :disabled="submitting"
                ></v-btn>
            </v-card-title>

            <v-card-text class="pa-0 bg-grey-lighten-4">
                <div v-if="loading" class="d-flex justify-center py-12">
                    <v-progress-circular
                        indeterminate
                        color="primary"
                        size="48"
                    ></v-progress-circular>
                </div>

                <v-form
                    v-else
                    ref="formRef"
                    @submit.prevent="submit"
                    :disabled="submitting"
                >
                    <div class="pa-6">
                        <!-- Informasi Utama -->
                        <v-card
                            class="mb-6 rounded-lg elevation-1"
                            variant="flat"
                        >
                            <v-card-title class="pa-4 border-b">
                                <span class="text-subtitle-1 font-weight-bold"
                                    >Informasi Utama</span
                                >
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <v-row>
                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model="form.username"
                                            label="Username"
                                            variant="outlined"
                                            density="comfortable"
                                            disabled
                                        ></v-text-field>
                                    </v-col>
                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model="form.name"
                                            label="Nama Mitra"
                                            variant="outlined"
                                            density="comfortable"
                                            :rules="[
                                                (v) =>
                                                    !!v || 'Nama wajib diisi',
                                            ]"
                                        ></v-text-field>
                                    </v-col>
                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model="form.email"
                                            label="Email"
                                            variant="outlined"
                                            density="comfortable"
                                            type="email"
                                            :rules="[
                                                (v) =>
                                                    !!v || 'Email wajib diisi',
                                                (v) =>
                                                    /.+@.+\..+/.test(v) ||
                                                    'Format email tidak valid',
                                            ]"
                                        ></v-text-field>
                                    </v-col>
                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model="form.mobile_phone"
                                            label="Nomor Telepon"
                                            variant="outlined"
                                            density="comfortable"
                                            prefix="+62"
                                            :rules="[
                                                (v) =>
                                                    !!v ||
                                                    'Nomor Telepon wajib diisi',
                                            ]"
                                        ></v-text-field>
                                    </v-col>
                                    <v-col cols="12" md="6">
                                        <v-select
                                            v-model="form.gender"
                                            label="Jenis Kelamin"
                                            :items="['Laki-laki', 'Perempuan']"
                                            variant="outlined"
                                            density="comfortable"
                                            :rules="[
                                                (v) =>
                                                    !!v ||
                                                    'Jenis Kelamin wajib diisi',
                                            ]"
                                        ></v-select>
                                    </v-col>
                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model="form.birth_date"
                                            label="Tanggal Lahir"
                                            type="date"
                                            variant="outlined"
                                            density="comfortable"
                                            :max="
                                                new Date()
                                                    .toISOString()
                                                    .split('T')[0]
                                            "
                                            :rules="[
                                                (v) =>
                                                    !!v ||
                                                    'Tanggal lahir wajib diisi',
                                                (v) =>
                                                    !v ||
                                                    new Date(v) <= new Date() ||
                                                    'Tanggal lahir tidak boleh lebih dari hari ini',
                                            ]"
                                        ></v-text-field>
                                    </v-col>

                                    <v-col cols="12" md="6">
                                        <v-select
                                            v-model="form.status"
                                            label="Status Mitra"
                                            :items="[
                                                { title: 'Aktif', value: 1 },
                                                { title: 'Suspend', value: 2 },
                                                {
                                                    title: 'Tidak Aktif',
                                                    value: 0,
                                                },
                                            ]"
                                            variant="outlined"
                                            density="comfortable"
                                            :rules="[
                                                (v) =>
                                                    (v !== null &&
                                                        v !== undefined) ||
                                                    'Status wajib diisi',
                                            ]"
                                        ></v-select>
                                    </v-col>
                                </v-row>
                            </v-card-text>
                        </v-card>

                        <!-- Data Alamat -->
                        <v-card
                            class="mb-6 rounded-lg elevation-1"
                            variant="flat"
                        >
                            <v-card-title
                                class="pa-4 border-b d-flex align-center justify-space-between"
                            >
                                <span class="text-subtitle-1 font-weight-bold"
                                    >Daftar Alamat</span
                                >
                                <v-btn
                                    color="primary"
                                    variant="tonal"
                                    prepend-icon="mdi-plus"
                                    @click="addAddress"
                                    size="small"
                                    >Tambah Alamat</v-btn
                                >
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <v-expand-transition group>
                                    <v-card
                                        v-for="(
                                            address, index
                                        ) in form.addresses"
                                        :key="`addr-${index}`"
                                        class="mb-4 border rounded"
                                        variant="flat"
                                    >
                                        <v-card-title
                                            class="d-flex justify-space-between align-center bg-grey-lighten-4 pa-3"
                                        >
                                            <span
                                                class="text-subtitle-2 font-weight-bold"
                                                >Alamat {{ index + 1 }}</span
                                            >
                                            <div
                                                class="d-flex align-center ga-2"
                                            >
                                                <v-switch
                                                    v-model="address.is_default"
                                                    label="Utama"
                                                    color="primary"
                                                    density="compact"
                                                    hide-details
                                                    @change="
                                                        handleDefaultAddress(
                                                            index,
                                                        )
                                                    "
                                                ></v-switch>
                                                <v-btn
                                                    icon="mdi-delete"
                                                    color="error"
                                                    variant="text"
                                                    size="small"
                                                    @click="
                                                        removeAddress(index)
                                                    "
                                                ></v-btn>
                                            </div>
                                        </v-card-title>
                                        <v-card-text class="pa-4">
                                            <v-row>
                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="address.label"
                                                        label="Label (Cth: Rumah)"
                                                        variant="outlined"
                                                        density="compact"
                                                    ></v-text-field>
                                                </v-col>
                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            address.recipient
                                                        "
                                                        label="Penerima"
                                                        variant="outlined"
                                                        density="compact"
                                                        :rules="[
                                                            (v) =>
                                                                !!v ||
                                                                'Nama penerima wajib diisi',
                                                        ]"
                                                    ></v-text-field>
                                                </v-col>
                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="address.phone"
                                                        label="Telepon Penerima"
                                                        variant="outlined"
                                                        density="compact"
                                                        :rules="[
                                                            (v) =>
                                                                !!v ||
                                                                'Nomor telepon wajib diisi',
                                                        ]"
                                                    ></v-text-field>
                                                </v-col>
                                                <v-col cols="12" md="6">
                                                    <v-autocomplete
                                                        v-model="
                                                            address.province_id
                                                        "
                                                        :items="provinces"
                                                        item-title="name"
                                                        item-value="id"
                                                        label="Provinsi"
                                                        variant="outlined"
                                                        density="compact"
                                                        :rules="[
                                                            (v) =>
                                                                !!v ||
                                                                'Provinsi wajib diisi',
                                                        ]"
                                                        @update:modelValue="
                                                            loadCities(index)
                                                        "
                                                    ></v-autocomplete>
                                                </v-col>
                                                <v-col cols="12" md="6">
                                                    <v-autocomplete
                                                        v-model="
                                                            address.city_id
                                                        "
                                                        :items="
                                                            address.cityOptions
                                                        "
                                                        item-title="name"
                                                        item-value="id"
                                                        label="Kota / Kabupaten"
                                                        variant="outlined"
                                                        density="compact"
                                                        :rules="[
                                                            (v) =>
                                                                !!v ||
                                                                'Kota/Kabupaten wajib diisi',
                                                        ]"
                                                        :disabled="
                                                            !address.province_id
                                                        "
                                                        @update:modelValue="
                                                            loadDistricts(index)
                                                        "
                                                    ></v-autocomplete>
                                                </v-col>
                                                <v-col cols="12" md="6">
                                                    <v-autocomplete
                                                        v-model="
                                                            address.district_id
                                                        "
                                                        :items="
                                                            address.districtOptions
                                                        "
                                                        item-title="name"
                                                        item-value="id"
                                                        label="Kecamatan"
                                                        variant="outlined"
                                                        density="compact"
                                                        :rules="[
                                                            (v) =>
                                                                !!v ||
                                                                'Kecamatan wajib diisi',
                                                        ]"
                                                        :disabled="
                                                            !address.city_id
                                                        "
                                                        @update:modelValue="
                                                            loadSubdistricts(
                                                                index,
                                                            )
                                                        "
                                                    ></v-autocomplete>
                                                </v-col>
                                                <v-col cols="12" md="6">
                                                    <v-autocomplete
                                                        v-model="
                                                            address.subdistrict_id
                                                        "
                                                        :items="
                                                            address.subdistrictOptions
                                                        "
                                                        item-title="name"
                                                        item-value="id"
                                                        label="Kelurahan / Desa"
                                                        variant="outlined"
                                                        density="compact"
                                                        :rules="[
                                                            (v) =>
                                                                !!v ||
                                                                'Kelurahan wajib diisi',
                                                        ]"
                                                        :disabled="
                                                            !address.district_id
                                                        "
                                                    ></v-autocomplete>
                                                </v-col>
                                                <v-col cols="12">
                                                    <v-textarea
                                                        v-model="
                                                            address.full_address
                                                        "
                                                        label="Alamat Lengkap"
                                                        variant="outlined"
                                                        density="compact"
                                                        rows="3"
                                                        :rules="[
                                                            (v) =>
                                                                !!v ||
                                                                'Alamat lengkap wajib diisi',
                                                        ]"
                                                    ></v-textarea>
                                                </v-col>
                                            </v-row>
                                        </v-card-text>
                                    </v-card>
                                </v-expand-transition>
                                <div
                                    v-if="form.addresses.length === 0"
                                    class="text-center py-4 text-medium-emphasis"
                                >
                                    Belum ada data alamat.
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- Data Rekening -->
                        <v-card class="rounded-lg elevation-1" variant="flat">
                            <v-card-title
                                class="pa-4 border-b d-flex align-center justify-space-between"
                            >
                                <span class="text-subtitle-1 font-weight-bold"
                                    >Daftar Rekening</span
                                >
                                <v-btn
                                    color="primary"
                                    variant="tonal"
                                    prepend-icon="mdi-plus"
                                    @click="addBankAccount"
                                    size="small"
                                    >Tambah Rekening</v-btn
                                >
                            </v-card-title>
                            <v-card-text class="pa-4">
                                <v-expand-transition group>
                                    <v-card
                                        v-for="(
                                            bank, index
                                        ) in form.bank_accounts"
                                        :key="`bank-${index}`"
                                        class="mb-4 border rounded"
                                        variant="flat"
                                    >
                                        <v-card-title
                                            class="d-flex justify-space-between align-center bg-grey-lighten-4 pa-3"
                                        >
                                            <span
                                                class="text-subtitle-2 font-weight-bold"
                                                >Rekening {{ index + 1 }}</span
                                            >
                                            <div
                                                class="d-flex align-center ga-2"
                                            >
                                                <v-switch
                                                    v-model="bank.is_active"
                                                    label="Aktif"
                                                    color="success"
                                                    density="compact"
                                                    hide-details
                                                    class="mr-2"
                                                ></v-switch>
                                                <v-switch
                                                    v-model="bank.is_default"
                                                    label="Default"
                                                    color="primary"
                                                    density="compact"
                                                    hide-details
                                                    @change="
                                                        handleDefaultBank(index)
                                                    "
                                                ></v-switch>
                                                <v-btn
                                                    icon="mdi-delete"
                                                    color="error"
                                                    variant="text"
                                                    size="small"
                                                    @click="
                                                        removeBankAccount(index)
                                                    "
                                                ></v-btn>
                                            </div>
                                        </v-card-title>
                                        <v-card-text class="pa-4">
                                            <v-row>
                                                <v-col cols="12" md="6">
                                                    <v-autocomplete
                                                        v-model="bank.bank_id"
                                                        :items="banks"
                                                        item-title="name"
                                                        item-value="id"
                                                        label="Bank"
                                                        variant="outlined"
                                                        density="compact"
                                                        :rules="[
                                                            (v) =>
                                                                !!v ||
                                                                'Bank wajib dipilih',
                                                        ]"
                                                    >
                                                        <template
                                                            #item="{
                                                                props,
                                                                item,
                                                            }"
                                                        >
                                                            <v-list-item
                                                                v-bind="props"
                                                                :title="
                                                                    item.raw
                                                                        .name
                                                                "
                                                            >
                                                                <template
                                                                    #prepend
                                                                    v-if="
                                                                        item.raw
                                                                            .logo
                                                                    "
                                                                >
                                                                    <v-avatar
                                                                        size="32"
                                                                        rounded
                                                                        class="mr-3"
                                                                    >
                                                                        <v-img
                                                                            :src="
                                                                                item
                                                                                    .raw
                                                                                    .logo
                                                                            "
                                                                        />
                                                                    </v-avatar>
                                                                </template>
                                                            </v-list-item>
                                                        </template>
                                                    </v-autocomplete>
                                                </v-col>
                                                <v-col cols="12" md="6">
                                                    <v-text-field
                                                        v-model="
                                                            bank.account_number
                                                        "
                                                        label="Nomor Rekening"
                                                        variant="outlined"
                                                        density="compact"
                                                        :rules="[
                                                            (v) =>
                                                                !!v ||
                                                                'Nomor rekening wajib diisi',
                                                        ]"
                                                    ></v-text-field>
                                                </v-col>
                                                <v-col cols="12" md="6">
                                                    <v-text-field
                                                        v-model="
                                                            bank.account_name
                                                        "
                                                        label="Nama Pemilik"
                                                        variant="outlined"
                                                        density="compact"
                                                        :rules="[
                                                            (v) =>
                                                                !!v ||
                                                                'Nama pemilik wajib diisi',
                                                        ]"
                                                    ></v-text-field>
                                                </v-col>
                                                <v-col cols="12" md="3">
                                                    <v-text-field
                                                        v-model="bank.city"
                                                        label="Kota Cabang"
                                                        variant="outlined"
                                                        density="compact"
                                                    ></v-text-field>
                                                </v-col>
                                                <v-col cols="12" md="3">
                                                    <v-text-field
                                                        v-model="bank.branch"
                                                        label="Cabang Bank"
                                                        variant="outlined"
                                                        density="compact"
                                                    ></v-text-field>
                                                </v-col>
                                            </v-row>
                                        </v-card-text>
                                    </v-card>
                                </v-expand-transition>
                                <div
                                    v-if="form.bank_accounts.length === 0"
                                    class="text-center py-4 text-medium-emphasis"
                                >
                                    Belum ada data rekening.
                                </div>
                            </v-card-text>
                        </v-card>
                    </div>
                </v-form>
            </v-card-text>

            <v-card-actions class="pa-6 border-t d-flex justify-end ga-3">
                <v-btn
                    variant="outlined"
                    color="medium-emphasis"
                    @click="close"
                    :disabled="submitting"
                    >Batal</v-btn
                >
                <v-btn
                    color="primary"
                    variant="flat"
                    @click="submit"
                    :loading="submitting"
                    >Simpan Perubahan</v-btn
                >
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref } from "vue";
import memberService from "@/admin/services/member.service";
import referenceService from "@/shared/services/reference.service";
import { useSnackbarStore } from "@/shared/stores/snackbar";

const emit = defineEmits(["updated"]);

const dialog = ref(false);
const formRef = ref<any>(null);
const snackbar = useSnackbarStore();

const loading = ref(true);
const submitting = ref(false);
const currentId = ref<number | null>(null);

const provinces = ref<any[]>([]);
const banks = ref<any[]>([]);

const form = ref({
    username: "",
    name: "",
    email: "",
    mobile_phone: "",
    gender: "Laki-laki",
    birth_date: "",
    status: 1,
    addresses: [] as any[],
    bank_accounts: [] as any[],
});

const open = async (id: number) => {
    currentId.value = id;
    dialog.value = true;
    await loadInitialData(id);
};

const close = () => {
    dialog.value = false;
    currentId.value = null;
    form.value = {
        username: "",
        name: "",
        email: "",
        mobile_phone: "",
        gender: "Laki-laki",
        birth_date: "",
        status: 1,
        addresses: [],
        bank_accounts: [],
    };
    if (formRef.value) formRef.value.resetValidation();
};

const loadInitialData = async (id: number) => {
    try {
        loading.value = true;
        const [provincesData, banksData, memberData] = await Promise.all([
            referenceService.getProvinces(),
            referenceService.getBanks(),
            memberService.getMember(id),
        ]);

        provinces.value = provincesData;
        banks.value = banksData;

        // Populate Form
        form.value.username = memberData.username || memberData.code || "";
        form.value.name = memberData.name || "";
        form.value.email = memberData.email || "";

        let phone = memberData.mobile_phone || "";
        if (phone.startsWith("+62")) phone = phone.replace("+62", "");
        else if (phone.startsWith("62")) phone = phone.substring(2);
        else if (phone.startsWith("0")) phone = phone.substring(1);
        form.value.mobile_phone = phone;

        // @ts-ignore
        form.value.gender = memberData.gender || "Laki-laki";
        // @ts-ignore
        form.value.birth_date = memberData.birth_date
            ? memberData.birth_date.substring(0, 10)
            : "";
        form.value.status = memberData.status?.code ?? 1;

        // Addresses
        if (memberData.addresses && memberData.addresses.length > 0) {
            form.value.addresses = memberData.addresses.map((a: any) => ({
                id: a.id,
                label: a.label,
                recipient: a.recipient,
                phone: a.phone,
                full_address: a.full_address,
                province_id: a.region?.province_id,
                city_id: a.region?.city_id,
                district_id: a.region?.district_id,
                subdistrict_id: a.region?.subdistrict_id,
                country_id: a.region?.country_id || 1,
                is_default: a.is_default,
                cityOptions: [],
                districtOptions: [],
                subdistrictOptions: [],
            }));

            for (let i = 0; i < form.value.addresses.length; i++) {
                if (form.value.addresses[i].province_id)
                    await loadCities(i, false);
                if (form.value.addresses[i].city_id)
                    await loadDistricts(i, false);
                if (form.value.addresses[i].district_id)
                    await loadSubdistricts(i, false);
            }
        }

        // Banks
        if (memberData.bank_accounts && memberData.bank_accounts.length > 0) {
            form.value.bank_accounts = memberData.bank_accounts.map(
                (b: any) => ({
                    id: b.id,
                    bank_id: b.bank_id,
                    account_name: b.account_name,
                    account_number: b.account_number,
                    city: b.city,
                    branch: b.branch,
                    is_active: b.is_active,
                    is_default: b.is_default,
                }),
            );
        }
    } catch (error) {
        console.error("Failed to load member data", error);
        snackbar.showMessage("Gagal memuat data mitra.", "error");
        close();
    } finally {
        loading.value = false;
    }
};

const addAddress = () => {
    form.value.addresses.push({
        label: "",
        recipient: "",
        phone: "",
        full_address: "",
        province_id: null,
        city_id: null,
        district_id: null,
        subdistrict_id: null,
        country_id: 1,
        is_default: form.value.addresses.length === 0,
        cityOptions: [],
        districtOptions: [],
        subdistrictOptions: [],
    });
};

const removeAddress = (index: number) => {
    form.value.addresses.splice(index, 1);
};

const handleDefaultAddress = (index: number) => {
    if (form.value.addresses[index].is_default) {
        form.value.addresses.forEach((addr, i) => {
            if (i !== index) addr.is_default = false;
        });
    }
};

const loadCities = async (index: number, resetChildren = true) => {
    const addr = form.value.addresses[index];
    if (!addr.province_id) return;
    if (resetChildren) {
        addr.city_id = null;
        addr.district_id = null;
        addr.subdistrict_id = null;
        addr.districtOptions = [];
        addr.subdistrictOptions = [];
    }
    addr.cityOptions = await referenceService.getCities(addr.province_id);
};

const loadDistricts = async (index: number, resetChildren = true) => {
    const addr = form.value.addresses[index];
    if (!addr.city_id) return;
    if (resetChildren) {
        addr.district_id = null;
        addr.subdistrict_id = null;
        addr.subdistrictOptions = [];
    }
    addr.districtOptions = await referenceService.getDistricts(addr.city_id);
};

const loadSubdistricts = async (index: number, resetChildren = true) => {
    const addr = form.value.addresses[index];
    if (!addr.district_id) return;
    if (resetChildren) {
        addr.subdistrict_id = null;
    }
    addr.subdistrictOptions = await referenceService.getSubdistricts(
        addr.district_id,
    );
};

const addBankAccount = () => {
    form.value.bank_accounts.push({
        bank_id: null,
        account_name: "",
        account_number: "",
        city: "",
        branch: "",
        is_active: true,
        is_default: form.value.bank_accounts.length === 0,
    });
};

const removeBankAccount = (index: number) => {
    form.value.bank_accounts.splice(index, 1);
};

const handleDefaultBank = (index: number) => {
    if (form.value.bank_accounts[index].is_default) {
        form.value.bank_accounts.forEach((bank, i) => {
            if (i !== index) bank.is_default = false;
        });
        form.value.bank_accounts[index].is_active = true;
    }
};

const submit = async () => {
    if (!formRef.value) return;
    const { valid } = await formRef.value.validate();
    if (!valid) {
        snackbar.showMessage(
            "Mohon lengkapi semua field yang wajib diisi dengan benar.",
            "error",
        );
        return;
    }

    if (!currentId.value) return;

    submitting.value = true;
    try {
        const payload = {
            ...form.value,
            mobile_phone: "+62" + form.value.mobile_phone,
            addresses: form.value.addresses.map((a) => ({
                id: a.id,
                label: a.label,
                recipient: a.recipient,
                phone: a.phone,
                full_address: a.full_address,
                province_id: String(a.province_id),
                city_id: String(a.city_id),
                district_id: String(a.district_id),
                subdistrict_id: Number(a.subdistrict_id),
                country_id: 1,
                is_default: a.is_default,
            })),
            bank_accounts: form.value.bank_accounts.map((b) => ({
                id: b.id,
                bank_id: b.bank_id,
                account_name: b.account_name,
                account_number: b.account_number,
                city: b.city,
                branch: b.branch,
                is_active: b.is_active,
                is_default: b.is_default,
            })),
        };

        await memberService.updateMember(currentId.value, payload);
        snackbar.showMessage("Data mitra berhasil diperbarui", "success");
        emit("updated");
        close();
    } catch (error: any) {
        console.error("Update failed", error);
        snackbar.showMessage(
            error.response?.data?.message || "Gagal menyimpan perubahan",
            "error",
        );
    } finally {
        submitting.value = false;
    }
};

defineExpose({ open, close });
</script>
