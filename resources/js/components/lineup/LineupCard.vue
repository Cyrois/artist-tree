<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { getLineupStats } from '@/data/lineups';
import { getArtistsByIds } from '@/data/artists';
import { formatCurrency } from '@/data/constants';
import type { Lineup } from '@/data/types';
import { cn } from '@/lib/utils';
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
    const headliners = getArtistsByIds(props.lineup.artists.headliner.slice(0, 3));
    return headliners;
});
</script>

<template>
    <Card
        class="cursor-pointer transition-all duration-200 hover:shadow-md hover:border-border/80"
        data-slot="lineup-card"
        @click="emit('click', lineup)"
    >
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between">
                <div>
                    <CardTitle class="text-lg">{{ lineup.name }}</CardTitle>
                    <p class="text-sm text-muted-foreground mt-1">{{ lineup.description }}</p>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <!-- Artist preview avatars -->
            <div v-if="previewArtists.length > 0" class="flex -space-x-2">
                <div
                    v-for="artist in previewArtists"
                    :key="artist.id"
                    class="w-10 h-10 rounded-full border-2 border-background overflow-hidden"
                >
                    <img :src="artist.image" :alt="artist.name" class="w-full h-full object-cover" />
                </div>
                <div
                    v-if="stats.artistCount > 3"
                    class="w-10 h-10 rounded-full border-2 border-background bg-muted flex items-center justify-center text-xs font-medium text-muted-foreground"
                >
                    +{{ stats.artistCount - 3 }}
                </div>
            </div>

            <!-- Stats grid -->
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <Users class="w-4 h-4" />
                    <span>{{ stats.artistCount }} artists</span>
                </div>
                <div class="flex items-center gap-2 text-muted-foreground">
                    <span class="font-medium text-foreground">Avg {{ stats.avgScore }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <Check class="w-4 h-4 text-[hsl(var(--status-confirmed))]" />
                    <span class="text-[hsl(var(--status-confirmed))]">{{ stats.confirmedCount }} confirmed</span>
                </div>
                <div class="flex items-center gap-2">
                    <Clock class="w-4 h-4 text-[hsl(var(--status-negotiating))]" />
                    <span class="text-[hsl(var(--status-negotiating))]">{{ stats.pendingCount }} pending</span>
                </div>
            </div>

            <!-- Budget -->
            <div v-if="stats.totalBudget > 0" class="flex items-center gap-2 pt-2 border-t text-sm">
                <DollarSign class="w-4 h-4 text-muted-foreground" />
                <span class="text-muted-foreground">Budget:</span>
                <span class="font-medium">{{ formatCurrency(stats.totalBudget) }}</span>
            </div>

            <!-- Last updated -->
            <div class="flex items-center gap-2 text-xs text-muted-foreground pt-2 border-t">
                <Calendar class="w-3 h-3" />
                <span>Updated {{ lineup.updatedAt }}</span>
            </div>
        </CardContent>
    </Card>
</template>
