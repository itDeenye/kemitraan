<template>
    <div class="screen-body">
        <CategorySlider
            :categories="networkSectionFilters"
            :model-value="activeSection"
            :show-all="false"
            aria-label="Menu jaringan mitra"
            item-label="menu jaringan"
            @update:model-value="handleSectionChange"
        />

        <div
            v-if="
                activeSection === 'downline' &&
                context &&
                context.breadcrumbs.length > 1
            "
            class="breadcrumbs-row"
        >
            <button
                v-for="(crumb, idx) in context.breadcrumbs"
                :key="crumb.id"
                class="breadcrumb-chip"
                :class="{ active: idx === context.breadcrumbs.length - 1 }"
                @click="navigateToBreadcrumb(crumb, idx)"
            >
                {{ crumb.name }}
            </button>
        </div>

        <div v-if="activeSection !== 'form'" class="search-box mb-3">
            <v-icon icon="mdi-magnify" size="small" />
            <input
                v-model="searchQuery"
                type="text"
                :placeholder="searchPlaceholder"
                style="
                    background: transparent;
                    border: none;
                    outline: none;
                    width: 100%;
                    margin-left: 8px;
                "
            />
        </div>

        <div
            v-if="activeSection === 'downline' && loading"
            class="empty-state"
        >
            <v-progress-circular indeterminate color="var(--wine)" size="32" />
            <span class="soft-label mt-2">Memuat jaringan...</span>
        </div>

        <template v-else-if="activeSection === 'downline'">
            <div class="section-heading mt-6">
                <h3>Daftar Downline</h3>
                <!-- <span>{{ members.length }} mitra</span> -->
            </div>

            <div v-if="members.length === 0" class="card empty-state">
                <v-icon
                    icon="mdi-account-group-outline"
                    size="40"
                    color="var(--muted)"
                />
                <span class="soft-label mt-2"
                    >Belum ada downline terdaftar</span
                >
            </div>

            <div v-else class="card list-card">
                <div
                    v-for="member in members"
                    :key="member.id"
                    class="tree-group"
                >
                    <button
                        class="tree-toggle"
                        type="button"
                        :aria-expanded="
                            member.has_downlines
                                ? expandedIds.includes(member.id)
                                : undefined
                        "
                        @click="toggleMember(member)"
                    >
                        <div
                            class="row-icon"
                            style="padding: 0; overflow: hidden"
                        >
                            <v-img
                                v-if="member.image || member.image_url"
                                :src="member.image || member.image_url"
                                cover
                                style="width: 100%; height: 100%"
                            >
                                <template v-slot:error>
                                    <div
                                        class="d-flex align-center justify-center fill-height w-100"
                                        style="
                                            background: rgba(
                                                var(--v-theme-primary),
                                                0.05
                                            );
                                            color: var(--wine);
                                        "
                                    >
                                        <span
                                            class="text-caption font-weight-bold"
                                            >{{
                                                member.name
                                                    .charAt(0)
                                                    .toUpperCase()
                                            }}</span
                                        >
                                    </div>
                                </template>
                            </v-img>
                            <div
                                v-else
                                class="d-flex align-center justify-center fill-height w-100 h-100"
                                style="
                                    background: rgba(
                                        var(--v-theme-primary),
                                        0.05
                                    );
                                    color: var(--wine);
                                "
                            >
                                <span class="text-caption font-weight-bold">{{
                                    member.name.charAt(0).toUpperCase()
                                }}</span>
                            </div>
                        </div>
                        <div class="row-main">
                            <strong>{{ member.name }}</strong>
                            <div class="flex items-center gap-1 mt-0.5">
                                <span class="text-gray-500">{{
                                    member.code
                                }}</span>
                                <BaseBadge
                                    type="level"
                                    :value="member.level.code"
                                    chip-class="!h-auto !py-[2px] !px-2 !text-[10px]"
                                    inline
                                />
                            </div>
                        </div>
                        <span v-if="member.has_downlines" class="badge green">
                            {{ member.total_direct_downlines }} Downline
                        </span>
                        <v-icon
                            v-if="member.has_downlines"
                            class="navigation-chevron tree-chevron"
                            icon="mdi-chevron-right"
                            size="24"
                        />
                    </button>

                    <div
                        class="tree-children"
                        v-show="expandedIds.includes(member.id)"
                    >
                        <div
                            v-if="childrenLoading[member.id]"
                            style="padding: 12px; text-align: center"
                        >
                            <v-progress-circular
                                indeterminate
                                color="var(--wine)"
                                size="20"
                                width="2"
                            />
                        </div>
                        <template v-else-if="childrenMap[member.id]?.length">
                            <div
                                v-for="child in childrenMap[member.id]"
                                :key="child.id"
                                class="list-row flow-link"
                                @click="drillDown(child)"
                            >
                                <div
                                    class="row-icon"
                                    style="padding: 0; overflow: hidden"
                                >
                                    <v-img
                                        v-if="child.image || child.image_url"
                                        :src="child.image || child.image_url"
                                        cover
                                        style="width: 100%; height: 100%"
                                    >
                                        <template v-slot:error>
                                            <div
                                                class="d-flex align-center justify-center fill-height w-100"
                                                style="
                                                    background: rgba(
                                                        var(--v-theme-primary),
                                                        0.05
                                                    );
                                                    color: var(--wine);
                                                "
                                            >
                                                <span
                                                    class="text-caption font-weight-bold"
                                                    >{{
                                                        child.name
                                                            .charAt(0)
                                                            .toUpperCase()
                                                    }}</span
                                                >
                                            </div>
                                        </template>
                                    </v-img>
                                    <div
                                        v-else
                                        class="d-flex align-center justify-center fill-height w-100 h-100"
                                        style="
                                            background: rgba(
                                                var(--v-theme-primary),
                                                0.05
                                            );
                                            color: var(--wine);
                                        "
                                    >
                                        <span
                                            class="text-caption font-weight-bold"
                                            >{{
                                                child.name
                                                    .charAt(0)
                                                    .toUpperCase()
                                            }}</span
                                        >
                                    </div>
                                </div>
                                <div class="row-main">
                                    <strong>{{ child.name }}</strong>
                                    <div class="flex items-center gap-1 mt-0.5">
                                        <span class="text-gray-500">{{
                                            child.code
                                        }}</span>
                                        <BaseBadge
                                            type="level"
                                            :value="child.level.code"
                                            chip-class="!h-auto !py-[2px] !px-2 !text-[10px]"
                                            inline
                                        />
                                    </div>
                                </div>
                                <div
                                    v-if="child.has_downlines"
                                    class="row-side network-row-side"
                                >
                                    <span
                                        class="badge green"
                                    >
                                        {{ child.total_direct_downlines }}
                                    </span>
                                    <v-icon
                                        class="navigation-chevron"
                                        icon="mdi-chevron-right"
                                        size="24"
                                    />
                                </div>
                            </div>
                        </template>
                        <div
                            v-else
                            class="tree-more"
                            style="text-align: center; color: var(--muted)"
                        >
                            Tidak ada downline
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="pagination && pagination.next !== 0"
                class="pagination-row mt-4"
            >
                <button
                    class="pagination-btn !w-full"
                    :disabled="loadingMore"
                    @click="loadMore"
                >
                    <v-progress-circular
                        v-if="loadingMore"
                        indeterminate
                        size="16"
                        color="var(--wine)"
                        class="mr-2"
                    />
                    {{ loadingMore ? "Memuat..." : "Muat lainnya" }}
                </button>
            </div>
        </template>

        <RegistrationModule
            v-else
            :embedded="true"
            :tab="registrationTab"
            :search-query="searchQuery"
        />
    </div>
</template>

<script setup lang="ts">
import { computed, ref, reactive, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/shared/stores/auth";
import networkService from "@/member/services/network.service";
import BaseBadge from "@/shared/components/BaseBadge.vue";
import CategorySlider from "@/member/components/transactions/CategorySlider.vue";
import RegistrationModule from "@/member/pages/network/registrations/index.vue";
import type {
    GenealogyMember,
    GenealogyContext,
    GenealogyContextMember,
    DataTablePagination,
} from "@/member/types/network";

type NetworkSection = "downline" | "form" | "history" | "approval";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const isDistributor = computed(
    () => authStore.user?.member?.level?.code === "DST",
);

function resolveSectionFromQuery(): NetworkSection {
    const requestedSection = Array.isArray(route.query.tab)
        ? route.query.tab[0]
        : route.query.tab;

    if (requestedSection === "form" || requestedSection === "history") {
        return requestedSection;
    }

    if (requestedSection === "approval" && isDistributor.value) {
        return "approval";
    }

    return "downline";
}

const activeSection = ref<NetworkSection>(resolveSectionFromQuery());
const registrationTab = computed<"form" | "list" | "approval">(() =>
    activeSection.value === "history"
        ? "list"
        : activeSection.value === "approval"
          ? "approval"
          : "form",
);
const registrationCounts = ref<{
    registrations: number | null;
    approvals: number | null;
}>({ registrations: null, approvals: null });
const networkSectionFilters = computed(() => {
    const filters: Array<{ id: NetworkSection; name: string }> = [
        { id: "downline", name: "Downline" },
    ];

    if (!context.value || context.value.can_register) {
        filters.push({ id: "form", name: "Form Pendaftaran" });
    }

    filters.push({
        id: "history",
        name: `Riwayat Pendaftaran (${registrationCounts.value.registrations ?? "…"})`,
    });

    if (isDistributor.value) {
        filters.push({
            id: "approval",
            name: `Approval Pendaftaran (${registrationCounts.value.approvals ?? "…"})`,
        });
    }

    return filters;
});
const searchPlaceholder = computed(() => {
    if (activeSection.value === "history") {
        return "Cari nama atau data pendaftar...";
    }
    if (activeSection.value === "approval") {
        return "Cari pendaftaran yang perlu diapproval...";
    }

    return "Cari kode atau nama mitra...";
});

const loading = ref(false);
const searchQuery = ref("");
const members = ref<GenealogyMember[]>([]);
const context = ref<GenealogyContext | null>(null);
const pagination = ref<DataTablePagination | null>(null);
const loadingMore = ref(false);
const expandedIds = ref<number[]>([]);
const childrenMap = reactive<Record<number, GenealogyMember[]>>({});
const childrenLoading = reactive<Record<number, boolean>>({});
const currentParentId = ref<number | undefined>(undefined);

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(searchQuery, (newVal, oldVal) => {
    if (newVal === oldVal) return;
    if (activeSection.value !== "downline") return;
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        loadGenealogy(1);
    }, 500);
});

watch([() => route.query.tab, isDistributor], () => {
    const nextSection = resolveSectionFromQuery();
    if (activeSection.value !== nextSection) {
        activeSection.value = nextSection;
        searchQuery.value = "";
    }
});

watch(isDistributor, (hasDistributorAccess, hadDistributorAccess) => {
    if (hasDistributorAccess && !hadDistributorAccess) {
        void loadRegistrationCounts();
    }
});

function selectSection(section: NetworkSection) {
    if (section === "approval" && !isDistributor.value) {
        return;
    }

    activeSection.value = section;
    searchQuery.value = "";
    void router.replace({
        query: {
            ...route.query,
            tab: section === "downline" ? undefined : section,
        },
    });
}

function handleSectionChange(section: NetworkSection | null) {
    if (section) {
        selectSection(section);
    }
}

async function loadRegistrationCounts() {
    const registrationCountRequest = networkService
        .getRegistrations({ page: 1, limit: 1 })
        .then((data) => data.pagination.total_data)
        .catch((error) => {
            console.error("Failed to load registration count:", error);
            return null;
        });
    const approvalCountRequest = isDistributor.value
        ? networkService
              .getRegistrationApprovals({
                  page: 1,
                  limit: 1,
                  "filter[distributor_approval_status]": "pending",
              })
              .then((data) => data.pagination.total_data)
              .catch((error) => {
                  console.error("Failed to load approval count:", error);
                  return null;
              })
        : Promise.resolve(null);

    const [registrations, approvals] = await Promise.all([
        registrationCountRequest,
        approvalCountRequest,
    ]);

    registrationCounts.value = {
        registrations,
        approvals: isDistributor.value ? approvals : 0,
    };
}

async function loadGenealogy(page = 1, append = false) {
    if (append) loadingMore.value = true;
    else loading.value = true;
    try {
        const data = await networkService.getGenealogy({
            parent_id: currentParentId.value,
            page,
            limit: 10,
            search: searchQuery.value || undefined,
        });
        context.value = data.context;
        if (append) {
            members.value = [...members.value, ...data.results];
        } else {
            members.value = data.results;
        }
        pagination.value = data.pagination;
        if (!append) expandedIds.value = [];
    } catch (e: any) {
        console.error("Failed to load genealogy:", e);
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

async function toggleMember(member: GenealogyMember) {
    if (!member.has_downlines) return;

    if (expandedIds.value.includes(member.id)) {
        expandedIds.value = expandedIds.value.filter((id) => id !== member.id);
        return;
    }

    expandedIds.value.push(member.id);

    if (!childrenMap[member.id]) {
        childrenLoading[member.id] = true;
        try {
            const data = await networkService.getGenealogy({
                parent_id: member.id,
            });
            childrenMap[member.id] = data.results;
        } catch (e: any) {
            console.error("Failed to load children:", e);
            childrenMap[member.id] = [];
        } finally {
            childrenLoading[member.id] = false;
        }
    }
}

function drillDown(member: GenealogyMember) {
    if (!member.has_downlines) return;
    currentParentId.value = member.id;
    Object.keys(childrenMap).forEach((k) => delete childrenMap[Number(k)]);
    loadGenealogy();
}

function navigateToBreadcrumb(crumb: GenealogyContextMember, idx: number) {
    if (!context.value) return;
    if (idx === context.value.breadcrumbs.length - 1) return;

    if (idx === 0) {
        currentParentId.value = undefined;
    } else {
        currentParentId.value = crumb.id;
    }
    Object.keys(childrenMap).forEach((k) => delete childrenMap[Number(k)]);
    loadGenealogy();
}

function loadMore() {
    if (pagination.value && pagination.value.next !== 0) {
        loadGenealogy(pagination.value.next, true);
    }
}

onMounted(() => {
    loadGenealogy();
    void loadRegistrationCounts();
});
</script>

<style scoped>
.breadcrumbs-row {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.breadcrumb-chip {
    background: var(--rose);
    color: var(--wine);
    border: 1px solid var(--rose-2);
    border-radius: 16px;
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.network-row-side {
    display: flex;
    align-items: center;
    gap: 6px;
}

.breadcrumb-chip.active {
    background: var(--wine);
    color: white;
    border-color: var(--wine);
    cursor: default;
}

.breadcrumb-chip:not(.active):hover {
    background: var(--rose-2);
}

.list-row.flow-link {
    cursor: pointer;
}
</style>
