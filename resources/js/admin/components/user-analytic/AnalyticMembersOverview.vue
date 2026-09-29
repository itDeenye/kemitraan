<template>
    <v-card variant="outlined" class="pa-4 h-100 rounded-lg">
        <h2 class="text-h6 font-weight-bold mb-4">Distribusi & Mitra Aktif</h2>

        <!-- Level Distribution -->
        <div class="mb-6 d-flex flex-wrap gap-2">
            <v-chip
                v-for="level in levels"
                :key="level.id"
                color="primary"
                variant="outlined"
            >
                <span class="mr-2">{{ level.name }}</span>
                <span class="font-weight-bold">{{ level.total_members }}</span>
            </v-chip>
        </div>

        <div class="text-subtitle-2 font-weight-bold mb-2">Top Mitra</div>

        <v-list
            density="compact"
            class="pa-0 overflow-y-auto pr-2 bg-transparent"
            style="max-height: 350px"
        >
            <v-list-item v-if="members.length === 0" class="px-0">
                <div class="text-center text-medium-emphasis py-4">
                    Belum ada mitra aktif.
                </div>
            </v-list-item>
            <v-list-item
                v-for="member in members"
                :key="member.id"
                class="px-0 mb-2 border-bottom"
            >
                <div
                    class="d-flex align-center justify-space-between w-100 pb-2"
                >
                    <div>
                        <div class="text-body-2 font-weight-bold">
                            {{ member.name }}
                        </div>
                        <div class="flex text-caption text-medium-emphasis">
                            {{ member.code }} &bull;
                            <BaseBadge
                                type="level"
                                :value="member.level.code"
                                :text="member.level.name"
                                inline
                                size="x-small"
                                class="ml-1"
                            />
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-caption text-medium-emphasis">
                            Downline
                        </div>
                        <div class="text-body-2 font-weight-bold text-success">
                            {{ member.total_direct_downlines }}
                        </div>
                    </div>
                </div>
            </v-list-item>
        </v-list>
    </v-card>
</template>

<script setup lang="ts">
import type {
    LevelDistributionAnalytic,
    HighlightedMemberAnalytic,
} from "@/admin/types/analytics";
import BaseBadge from "@/shared/components/BaseBadge.vue";

defineProps<{
    levels: LevelDistributionAnalytic[];
    members: HighlightedMemberAnalytic[];
}>();
</script>

<style scoped>
.border-bottom {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}
.border-bottom:last-child {
    border-bottom: none;
}
</style>
