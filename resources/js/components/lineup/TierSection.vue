<script setup lang="ts">
import TierArtistRow from '@/components/lineup/TierArtistRow.vue';
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { tierConfig } from '@/data/constants';
import type { Artist, TierType } from '@/data/types';
import type { GroupedArtist, StackGroup } from '@/types/lineup';
import { trans } from 'laravel-vue-i18n';
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    tier: TierType;
    artists: Artist[];
    compareMode?: boolean;
    stackMode?: boolean;
    selectedArtistIds?: number[];
    isAddingAlternativesTo?: string | null; // stack_id we are currently adding to
    stackingTier?: TierType | null;
}

const props = withDefaults(defineProps<Props>(), {
    compareMode: false,
    stackMode: false,
    selectedArtistIds: () => [],
    isAddingAlternativesTo: null,
    stackingTier: null,
});

const emit = defineEmits<{
    'select-artist': [artist: Artist];
    'view-artist': [artist: Artist];
    'remove-artist': [artist: Artist];
    'promote-artist': [artist: Artist];
    'remove-from-stack': [artist: Artist];
    'dissolve-stack': [stackId: string];
    'start-stack': [artist: Artist];
    'deselect-stack': [];
}>();

const isOpen = ref(true);
const config = computed(() => tierConfig[props.tier]);

const averageScore = computed(() => {
    if (!props.artists.length) return 0;
    const sum = props.artists.reduce(
        (acc, artist) => acc + (artist.score || 0),
        0,
    );
    return Math.round(sum / props.artists.length);
});

const groupedArtists = computed<GroupedArtist[]>(() => {
    const groups: Record<string, StackGroup> = {};
    const independent: Artist[] = [];

    props.artists.forEach((artist) => {
        if (artist.stack_id) {
            if (!groups[artist.stack_id]) {
                groups[artist.stack_id] = {
                    id: artist.stack_id,
                    primary: artist,
                    alternatives: [],
                };
            }

            if (artist.is_stack_primary) {
                groups[artist.stack_id].primary = artist;
            } else {
                groups[artist.stack_id].alternatives.push(artist);
            }
        } else {
            independent.push(artist);
        }
    });

    const result: GroupedArtist[] = [];

    // Add stacks first
    Object.values(groups).forEach((stack) => {
        result.push({ type: 'stack', stack });
    });

    // Add independent
    independent.forEach((artist) => {
        result.push({ type: 'independent', artist });
    });

    return result;
});
</script>

<template>
    <Collapsible
        v-model:open="isOpen"
        class="overflow-hidden rounded-xl border transition-all"
        :class="{
            'pointer-events-none opacity-40 grayscale-[0.5]':
                stackingTier && stackingTier !== tier,
        }"
    >
        <CollapsibleTrigger class="w-full">
            <div
                class="flex items-center justify-between p-4 transition-colors hover:bg-muted/50"
                :style="{ backgroundColor: config.bgColor }"
            >
                <div class="flex items-center gap-3">
                    <component
                        :is="isOpen ? ChevronDown : ChevronRight"
                        class="h-5 w-5 text-muted-foreground"
                    />
                    <span
                        class="font-bold tracking-wide"
                        :style="{ color: config.color }"
                    >
                        {{ config.label }}
                    </span>
                    <ScoreBadge
                        v-if="artists.length > 0"
                        :score="averageScore"
                        size="sm"
                    />
                </div>
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Badge variant="secondary">
                                {{ artists.length }}
                            </Badge>
                        </TooltipTrigger>
                        <TooltipContent>
                            {{ trans('lineups.tier_artist_count_tooltip') }}
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
        </CollapsibleTrigger>

        <CollapsibleContent>
            <div class="divide-y">
                <TierArtistRow
                    v-for="(group, idx) in groupedArtists"
                    :key="idx"
                    :group="group"
                    :compare-mode="compareMode"
                    :stack-mode="stackMode"
                    :selected-artist-ids="selectedArtistIds"
                    :is-adding-alternatives-to="isAddingAlternativesTo"
                    @select-artist="emit('select-artist', $event)"
                    @view-artist="emit('view-artist', $event)"
                    @remove-artist="emit('remove-artist', $event)"
                    @promote-artist="emit('promote-artist', $event)"
                    @remove-from-stack="emit('remove-from-stack', $event)"
                    @dissolve-stack="emit('dissolve-stack', $event)"
                    @start-stack="emit('start-stack', $event)"
                    @deselect-stack="emit('deselect-stack')"
                />

                <!-- Empty state -->
                <div
                    v-if="artists.length === 0"
                    class="p-8 text-center text-muted-foreground"
                >
                    {{ trans('lineups.tier_empty_state') }}
                </div>
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>