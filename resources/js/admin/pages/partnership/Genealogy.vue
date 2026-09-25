<template>
    <div>
        <div class="d-flex align-center justify-space-between mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Jaringan Mitra</h1>
                <p class="text-body-2 text-medium-emphasis">
                    Pemantauan visual relasi upline dan downline antar mitra.
                </p>
            </div>
        </div>

        <v-card class="rounded-xl border">
            <!-- Table Header -->
            <v-table class="genealogy-table">
                <thead>
                    <tr>
                        <th
                            class="text-left font-weight-bold"
                            style="width: 300px"
                        >
                            Mitra
                        </th>
                        <th class="text-left font-weight-bold">Kode</th>
                        <th class="text-center font-weight-bold">Level</th>
                        <th class="text-center font-weight-bold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading" class="text-center">
                        <td colspan="5" class="py-12">
                            <v-progress-circular
                                indeterminate
                                color="primary"
                            ></v-progress-circular>
                        </td>
                    </tr>
                    <tr
                        v-else-if="flattenedTree.length === 0"
                        class="text-center"
                    >
                        <td colspan="5" class="py-12 text-medium-emphasis">
                            Tidak ada data jaringan yang ditemukan.
                        </td>
                    </tr>
                    <tr
                        v-for="node in flattenedTree"
                        :key="node.id"
                        class="tree-row"
                        :class="{ 'bg-grey-lighten-5': node.indentLevel === 0 }"
                    >
                        <td class="py-3">
                            <div
                                class="d-flex align-center"
                                :style="{
                                    paddingLeft: `${node.indentLevel * 32}px`,
                                }"
                            >
                                <v-btn
                                    v-if="
                                        node.downlines &&
                                        node.downlines.length > 0
                                    "
                                    icon
                                    variant="text"
                                    size="small"
                                    color="grey-darken-1"
                                    class="mr-2"
                                    @click="toggleExpand(node.id)"
                                >
                                    <v-icon>{{
                                        expandedNodes.has(node.id)
                                            ? "mdi-minus-box-outline"
                                            : "mdi-plus-box-outline"
                                    }}</v-icon>
                                </v-btn>
                                <div
                                    v-else
                                    class="mr-2"
                                    style="width: 28px; height: 28px"
                                ></div>

                                <v-avatar
                                    color="primary"
                                    variant="tonal"
                                    size="36"
                                    class="mr-3"
                                >
                                    <v-img
                                        v-if="node.image || node.image_url"
                                        :src="node.image || node.image_url"
                                        :alt="node.name"
                                        cover
                                    >
                                        <template v-slot:error>
                                            <div
                                                class="d-flex align-center justify-center fill-height"
                                            >
                                                <span
                                                    class="text-caption font-weight-bold"
                                                    >{{
                                                        node.name
                                                            .charAt(0)
                                                            .toUpperCase()
                                                    }}</span
                                                >
                                            </div>
                                        </template>
                                    </v-img>
                                    <span
                                        v-else
                                        class="text-caption font-weight-bold"
                                        >{{
                                            node.name.charAt(0).toUpperCase()
                                        }}</span
                                    >
                                </v-avatar>
                                <div>
                                    <div class="font-weight-medium">
                                        {{ node.name }}
                                    </div>
                                    <div
                                        class="text-caption text-medium-emphasis"
                                        v-if="node.total_direct_downlines > 0"
                                    >
                                        {{ node.total_direct_downlines }}
                                        Downline
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="font-weight-medium text-body-2 text-left">
                            {{ node.code }}
                        </td>
                        <td class="text-center">
                            <BaseBadge
                                inline
                                type="level"
                                :align="'center'"
                                :value="node.level?.name || '-'"
                            />
                        </td>
                        <td class="text-center">
                            <BaseBadge
                                inline
                                type="status"
                                :align="'center'"
                                :value="node.status === 1"
                            />
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import memberService from "@/admin/services/member.service";
import type { GenealogyNode } from "@/admin/types/member";
import BaseBadge from "@/shared/components/BaseBadge.vue";

const loading = ref(false);
const treeData = ref<GenealogyNode[]>([]);
const expandedNodes = ref<Set<number>>(new Set());

const fetchGenealogy = async () => {
    loading.value = true;
    try {
        treeData.value = await memberService.getGenealogy();
        // Expand root nodes by default
        treeData.value.forEach((node) => {
            if (node.downlines && node.downlines.length > 0) {
                expandedNodes.value.add(node.id);
            }
        });
    } catch (error) {
        console.error("Failed to load genealogy", error);
    } finally {
        loading.value = false;
    }
};

const toggleExpand = (id: number) => {
    const newSet = new Set(expandedNodes.value);
    if (newSet.has(id)) {
        newSet.delete(id);
    } else {
        newSet.add(id);
    }
    expandedNodes.value = newSet;
};

// Flatten tree for table rendering
type FlatNode = GenealogyNode & { indentLevel: number };
const flattenTree = (nodes: GenealogyNode[], level: number = 0): FlatNode[] => {
    let result: FlatNode[] = [];
    for (const node of nodes) {
        result.push({ ...node, indentLevel: level });
        if (
            expandedNodes.value.has(node.id) &&
            node.downlines &&
            node.downlines.length > 0
        ) {
            result = result.concat(flattenTree(node.downlines, level + 1));
        }
    }
    return result;
};

const flattenedTree = computed(() => {
    return flattenTree(treeData.value, 0);
});

onMounted(() => {
    fetchGenealogy();
});
</script>

<style scoped>
.genealogy-table {
    border-collapse: collapse;
}
.genealogy-table th {
    background-color: rgb(var(--v-theme-grey-lighten-4));
    border-bottom: 1px solid rgba(0, 0, 0, 0.12);
}
.genealogy-table td {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}
.tree-row:hover {
    background-color: rgba(var(--v-theme-primary), 0.02) !important;
}
</style>
