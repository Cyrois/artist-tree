<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { getArtistsByIds } from '@/data/artists';
import { formatCurrency } from '@/data/constants';
import { getLineupStats } from '@/data/lineups';
import type { Lineup } from '@/data/types';
import { trans } from 'laravel-vue-i18n';
import { Calendar, Check, Clock, DollarSign, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    lineup: Lineup;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    click: [lineup: Lineup];
}>();

const stats = computed(() => getLineupStats(props.lineup));

// Get first few artists for preview
const previewArtists = computed(() => {
    const headliners = getArtistsByIds(
        props.lineup.artists.headliner.slice(0, 3),
    );
    return headliners;
});
</script>

<template>
    <Card
        class="cursor-pointer transition-all duration-200 hover:border-border/80 hover:shadow-md"
        data-slot="lineup-card"
        @click="emit('click', lineup)"
    >
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between">
                <div>
                    <CardTitle class="text-lg">{{ lineup.name }}</CardTitle>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ lineup.description }}
                    </p>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <!-- Artist preview avatars -->
            <div v-if="previewArtists.length > 0" class="flex -space-x-2">
                <div
                    v-for="artist in previewArtists"
                    :key="artist.id"
                    class="h-10 w-10 overflow-hidden rounded-full border-2 border-background"
                >
                    <img
                        :src="artist.image"
                        :alt="artist.name"
                        class="h-full w-full object-cover"
                    />
                </div>
                <div
                    v-if="stats.artistCount > 3"
                    class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-background bg-muted text-xs font-medium text-muted-foreground"
                >
                    +{{ stats.artistCount - 3 }}
                </div>
            </div>

            <!-- Stats grid -->
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <Users class="h-4 w-4" />
                    <span
                        >{{ stats.artistCount }}
                        {{ trans('lineups.card_artists') }}</span
                    >
                </div>
                <div class="flex items-center gap-2 text-muted-foreground">
                    <span class="font-medium text-foreground"
                        >{{ trans('lineups.card_avg') }}
                        {{ stats.avgScore }}</span
                    >
                </div>
                <div class="flex items-center gap-2">
                    <Check
                        class="h-4 w-4 text-[hsl(var(--status-confirmed))]"
                    />
                    <span class="text-[hsl(var(--status-confirmed))]"
                        >{{ stats.confirmedCount }}
                        {{ trans('lineups.card_confirmed') }}</span
                    >
                </div>
                <div class="flex items-center gap-2">
                    <Clock
                        class="h-4 w-4 text-[hsl(var(--status-negotiating))]"
                    />
                    <span class="text-[hsl(var(--status-negotiating))]"
                        >{{ stats.pendingCount }}
                        {{ trans('lineups.card_pending') }}</span
                    >
                </div>
            </div>

            <!-- Budget -->
            <div
                v-if="stats.totalBudget > 0"
                class="flex items-center gap-2 border-t pt-2 text-sm"
            >
                <DollarSign class="h-4 w-4 text-muted-foreground" />
                <span class="text-muted-foreground">{{
                    trans('lineups.card_budget')
                }}</span>
                <span class="font-medium">{{
                    formatCurrency(stats.totalBudget)
                }}</span>
            </div>

            <!-- Last updated -->
            <div
                class="flex items-center gap-2 border-t pt-2 text-xs text-muted-foreground"
            >
                <Calendar class="h-3 w-3" />
                <span
                    >{{ trans('lineups.card_updated') }}
                    {{ lineup.updatedAt }}</span
                >
            </div>
        </CardContent>
    </Card>
</template>
