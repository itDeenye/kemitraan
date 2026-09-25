<template>
    <v-dialog
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
        max-width="600px"
        persistent
    >
        <v-card>
            <v-card-title
                class="pa-6 pb-4 border-b d-flex align-center justify-space-between"
            >
                <div class="d-flex align-center ga-3">
                    <v-icon color="primary" size="28">mdi-menu</v-icon>
                    <span class="text-h6">{{
                        isEditMode
                            ? "Edit Menu"
                            : isSubmenu
                              ? "Tambah Submenu"
                              : "Tambah Parent Menu"
                    }}</span>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    density="comfortable"
                    @click="$emit('update:modelValue', false)"
                ></v-btn>
            </v-card-title>

            <v-divider></v-divider>

            <v-form @submit.prevent="submit" v-model="isFormValid">
                <v-card-text class="pa-4">
                    <v-row>
                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="form.title"
                                label="Nama Menu"
                                variant="outlined"
                                density="comfortable"
                                :rules="[rules.required('Nama menu')]"
                                required
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="form.link"
                                label="Path URL (Link)"
                                variant="outlined"
                                density="comfortable"
                                hint="Gunakan '#' jika menu ini hanya parent tanpa link"
                                :rules="[rules.required('Link')]"
                                required
                            ></v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <v-text-field
                                v-model="form.icon"
                                label="Icon (MDI)"
                                variant="outlined"
                                density="comfortable"
                                hint="Contoh: mdi-view-dashboard"
                            >
                                <template #prepend-inner>
                                    <v-icon
                                        v-if="form.icon"
                                        :icon="form.icon"
                                    ></v-icon>
                                </template>
                            </v-text-field>
                        </v-col>

                        <v-col cols="12" sm="6">
                            <v-switch
                                v-model="form.is_active"
                                color="success"
                                hide-details
                                inset
                                :class="{ 'inactive-switch': !form.is_active }"
                            >
                                <template v-slot:label>
                                    <span
                                        :class="
                                            form.is_active
                                                ? 'text-success font-weight-medium'
                                                : 'text-error font-weight-medium'
                                        "
                                    >
                                        Status:
                                        {{
                                            form.is_active
                                                ? "Aktif"
                                                : "Nonaktif"
                                        }}
                                    </span>
                                </template>
                            </v-switch>
                        </v-col>

                        <v-col cols="12">
                            <v-textarea
                                v-model="form.description"
                                label="Deskripsi (Opsional)"
                                variant="outlined"
                                density="comfortable"
                                rows="3"
                            ></v-textarea>
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-divider></v-divider>

                <v-card-actions class="pa-6 pt-4 border-t">
                    <v-spacer></v-spacer>
                    <v-btn
                        variant="outlined"
                        color="medium-emphasis"
                        class="text-none px-6"
                        @click="$emit('update:modelValue', false)"
                        :disabled="isLoading"
                    >
                        Batal
                    </v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        class="text-none px-6 ml-3"
                        @click="submit"
                        :loading="isLoading"
                        :disabled="!isFormValid"
                    >
                        Simpan
                    </v-btn>
                </v-card-actions>
            </v-form>
        </v-card>
    </v-dialog>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import type { AdminMenuPayload, AdminMenu } from "@/admin/types/menu";

const props = defineProps<{
    modelValue: boolean;
    isLoading: boolean;
    isEditMode: boolean;
    isSubmenu: boolean;
    initialData: Partial<AdminMenu> | null;
    parentId: number | null;
}>();

const emit = defineEmits(["update:modelValue", "save"]);

const isFormValid = ref(false);

const rules = {
    required: (field: string) => (v: any) => !!v || `${field} wajib diisi`,
};

const form = ref<AdminMenuPayload>({
    parent_id: null,
    title: "",
    description: "",
    link: "",
    icon: "",
    css_class: "",
    order: 0,
    is_active: true,
});

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            if (props.isEditMode && props.initialData) {
                form.value = {
                    parent_id: props.initialData.parent_id || null,
                    title: props.initialData.title || "",
                    description: props.initialData.description || "",
                    link: props.initialData.link || "",
                    icon: props.initialData.icon || "",
                    css_class: props.initialData.class || "",
                    order: props.initialData.order || 0,
                    is_active: props.initialData.is_active ?? true,
                };
            } else {
                form.value = {
                    parent_id: props.parentId,
                    title: "",
                    description: "",
                    link: props.isSubmenu ? "" : "#",
                    icon: props.isSubmenu ? "" : "mdi-circle-medium",
                    css_class: "",
                    order: 0,
                    is_active: true,
                };
            }
        }
    },
);

const submit = () => {
    if (isFormValid.value) {
        emit("save", { ...form.value });
    }
};
</script>

<style scoped>
.inactive-switch :deep(.v-label) {
    opacity: 1 !important;
}
.inactive-switch :deep(.v-switch__track) {
    background-color: rgb(var(--v-theme-error)) !important;
    opacity: 0.5 !important;
}
.inactive-switch :deep(.v-switch__thumb) {
    background-color: rgb(var(--v-theme-error)) !important;
    color: rgb(var(--v-theme-error)) !important;
}
</style>
