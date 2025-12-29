<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import ArtistAvatar from '@/components/artist/ArtistAvatar.vue';
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { tierConfig } from '@/data/constants';
import type { Artist, TierType } from '@/data/types';
import { cn } from '@/lib/utils';
import { ChevronDown, ChevronRight, Sparkles, MoreHorizontal, Layers, Scale, Trash2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { trans } from 'laravel-vue-i18n';

interface Props {
    tier: TierType;
    artists: Artist[];
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

const averageScore = computed(() => {
    if (!props.artists.length) return 0;
    const sum = props.artists.reduce((acc, artist) => acc + (artist.score || 0), 0);
    return Math.round(sum / props.artists.length);
});

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
                <ScoreBadge 
                    v-if="artists.length > 0" 
                    :score="averageScore" 
                    size="sm" 
                    class="mr-2"
                />
            </div>
        </CollapsibleTrigger>

        <CollapsibleContent>
            <div class="divide-y">
                <div
                    v-for="artist in artists"
                    :key="artist.id"
                    :class="cn(
                        'flex items-center gap-4 p-4 hover:bg-muted/30 transition-colors cursor-pointer',
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
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <div v-if="artist.genre && artist.genre.length > 0" class="flex flex-wrap gap-1">
                                <Badge 
                                    v-for="genre in artist.genre.slice(0, 3)" 
                                    :key="genre"
                                    variant="secondary"
                                    class="text-[10px] px-1.5 py-0 h-5 font-normal"
                                >
                                    {{ genre }}
                                </Badge>
                                <span v-if="artist.genre.length > 3" class="text-xs text-muted-foreground">+{{ artist.genre.length - 3 }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Score -->
                    <ScoreBadge :score="artist.score" size="md" />

                    <!-- Actions Menu -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="icon" class="h-8 w-8" @click.stop>
                                <MoreHorizontal class="w-4 h-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" @click.stop>
                            <DropdownMenuItem @click="emit('select-artist', artist)">
                                <Layers class="w-4 h-4 mr-2" />
                                {{ trans('lineups.tier_add_to_stack') }}
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="emit('select-artist', artist)">
                                <Scale class="w-4 h-4 mr-2" />
                                {{ trans('lineups.tier_compare_artist') }}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem class="text-destructive" @click="emit('remove-artist', artist)">
                                <Trash2 class="w-4 h-4 mr-2" />
                                {{ trans('lineups.tier_remove_from_lineup') }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <!-- Empty state -->
                <div v-if="artists.length === 0" class="p-8 text-center text-muted-foreground">
                    {{ trans('lineups.tier_empty_state') }}
                </div>
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>

