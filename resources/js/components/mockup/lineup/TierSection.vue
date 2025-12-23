<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import ArtistAvatar from '@/components/mockup/artist/ArtistAvatar.vue';
import ScoreBadge from '@/components/mockup/score/ScoreBadge.vue';
import { tierConfig, statusConfig, formatCurrency } from '@/data/constants';
import type { Artist, TierType, ArtistStatus } from '@/data/types';
import { cn } from '@/lib/utils';
import { ChevronDown, ChevronRight, Sparkles, MoreHorizontal, Layers, Scale, X } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Props {
    tier: TierType;
    artists: Artist[];
    statuses: Record<number, ArtistStatus>;
    compareMode?: boolean;
    selectedArtistIds?: number[];
}

const props = withDefaults(defineProps<Props>(), {
    compareMode: false,
    selectedArtistIds: () => [],
});

const emit = defineEmits<{
    'select-artist': [artist: Artist];
    'view-artist': [artist: Artist];
    'remove-artist': [artist: Artist];
}>();

const isOpen = ref(true);
const config = computed(() => tierConfig[props.tier]);

function getStatusIcon(status: string) {
    const config = statusConfig[status as keyof typeof statusConfig];
    return config?.label ?? status;
}

function getStatusColor(status: string) {
    const config = statusConfig[status as keyof typeof statusConfig];
    return config?.color ?? '#666';
}

function isSelected(artistId: number) {
    return props.selectedArtistIds.includes(artistId);
}
</script>

<template>
    <Collapsible v-model:open="isOpen" class="border rounded-xl overflow-hidden">
        <CollapsibleTrigger class="w-full">
            <div
                class="flex items-center justify-between p-4 hover:bg-muted/50 transition-colors"
                :style="{ backgroundColor: config.bgColor }"
            >
                <div class="flex items-center gap-3">
                    <component :is="isOpen ? ChevronDown : ChevronRight" class="w-5 h-5 text-muted-foreground" />
                    <span class="font-bold tracking-wide" :style="{ color: config.color }">
                        {{ config.label }}
                    </span>
                    <Badge variant="secondary">{{ artists.length }}</Badge>
                </div>
            </div>
        </CollapsibleTrigger>

        <CollapsibleContent>
            <div class="divide-y">
                <div
                    v-for="artist in artists"
                    :key="artist.id"
                    :class="cn(
                        'group flex items-center gap-4 p-4 hover:bg-muted/30 transition-colors cursor-pointer',
                        compareMode && isSelected(artist.id) && 'bg-[hsl(var(--compare-coral-bg))] border-l-4 border-[hsl(var(--compare-coral))]'
                    )"
                    @click="compareMode ? emit('select-artist', artist) : emit('view-artist', artist)"
                >
                    <!-- Checkbox for compare mode -->
                    <div v-if="compareMode" class="flex-shrink-0">
                        <div
                            :class="cn(
                                'w-5 h-5 rounded border-2 flex items-center justify-center transition-colors',
                                isSelected(artist.id) ? 'bg-[hsl(var(--compare-coral))] border-[hsl(var(--compare-coral))]' : 'border-muted-foreground/30'
                            )"
                        >
                            <svg v-if="isSelected(artist.id)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>

                    <!-- Avatar -->
                    <ArtistAvatar :artist="artist" size="sm" />

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium truncate">{{ artist.name }}</span>
                            <!-- Status indicator -->
                            <span
                                v-if="statuses[artist.id]"
                                class="text-xs px-2 py-0.5 rounded-full"
                                :style="{
                                    backgroundColor: statusConfig[statuses[artist.id].status]?.bgColor,
                                    color: statusConfig[statuses[artist.id].status]?.color
                                }"
                            >
                                {{ statusConfig[statuses[artist.id].status]?.label }}
                            </span>
                            <!-- Suggested tier indicator -->
                            <Sparkles
                                v-if="artist.tierSuggestion !== tier"
                                class="w-4 h-4 text-amber-500"
                                v-tooltip="'Suggested: ' + tierConfig[artist.tierSuggestion].label"
                            />
                        </div>
                        <div class="flex items-center gap-2 text-sm text-muted-foreground">
                            <span>{{ artist.genre.slice(0, 2).join(', ') }}</span>
                            <span v-if="statuses[artist.id]?.fee">
                                · {{ formatCurrency(statuses[artist.id].fee!) }}
                            </span>
                        </div>
                    </div>

                    <!-- Score -->
                    <ScoreBadge :score="artist.score" size="md" />

                    <!-- Actions -->
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <Button variant="ghost" size="icon" class="h-8 w-8" @click.stop="emit('select-artist', artist)">
                            <Layers class="w-4 h-4" />
                        </Button>
                        <Button variant="ghost" size="icon" class="h-8 w-8" @click.stop="emit('select-artist', artist)">
                            <Scale class="w-4 h-4" />
                        </Button>
                        <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" @click.stop="emit('remove-artist', artist)">
                            <X class="w-4 h-4" />
                        </Button>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="artists.length === 0" class="p-8 text-center text-muted-foreground">
                    No artists in this tier yet.
                </div>
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>
