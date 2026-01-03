<script setup lang="ts">
import ArtistAvatar from '@/components/artist/ArtistAvatar.vue';
import StackActionButton from '@/components/lineup/StackActionButton.vue';
import StackAlternativeActions from '@/components/lineup/StackAlternativeActions.vue';
import StackPrimaryActionButton from '@/components/lineup/StackPrimaryActionButton.vue';
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { tierConfig } from '@/data/constants';
import type { Artist, TierType } from '@/data/types';
import { cn } from '@/lib/utils';
import { trans } from 'laravel-vue-i18n';
import {
    ArrowUpCircle,
    ChevronDown,
    ChevronRight,
    ExternalLink,
    Layers,
    MoreHorizontal,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface StackGroup {
    id: string;
    primary: Artist;
    alternatives: Artist[];
}

type GroupedArtist =
    | { type: 'independent'; artist: Artist }
    | { type: 'stack'; stack: StackGroup };

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

function isSelected(artistId: number) {
    return props.selectedArtistIds.includes(artistId);
}
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
                <template v-for="(group, idx) in groupedArtists" :key="idx">
                    <!-- Independent Artist -->
                    <div
                        v-if="group.type === 'independent'"
                        :class="
                            cn(
                                'group flex items-center gap-4 p-4 transition-colors',
                                compareMode || stackMode
                                    ? 'cursor-pointer hover:bg-muted/30'
                                    : '',
                                compareMode &&
                                    isSelected(group.artist.id) &&
                                    'border-l-4 border-[hsl(var(--compare-coral))] bg-[hsl(var(--compare-coral-bg))]',
                                stackMode &&
                                    isAddingAlternativesTo &&
                                    'hover:bg-primary/5',
                            )
                        "
                        @click="
                            (compareMode || stackMode) &&
                            emit('select-artist', group.artist)
                        "
                    >
                        <div v-if="compareMode" class="flex-shrink-0">
                            <div
                                :class="
                                    cn(
                                        'flex h-5 w-5 items-center justify-center rounded border-2 transition-colors',
                                        isSelected(group.artist.id)
                                            ? 'border-[hsl(var(--compare-coral))] bg-[hsl(var(--compare-coral))]'
                                            : 'border-muted-foreground/30',
                                    )
                                "
                            >
                                <svg
                                    v-if="isSelected(group.artist.id)"
                                    class="h-3 w-3 text-white"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="3"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </div>
                        </div>

                        <ArtistAvatar :artist="group.artist" size="sm" />

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-medium">{{
                                    group.artist.name
                                }}</span>
                            </div>
                            <div class="mt-1 flex items-center gap-2">
                                <div
                                    v-if="
                                        group.artist.genre &&
                                        group.artist.genre.length > 0
                                    "
                                    class="flex flex-wrap gap-1"
                                >
                                    <Badge
                                        v-for="genre in group.artist.genre.slice(
                                            0,
                                            2,
                                        )"
                                        :key="genre"
                                        variant="secondary"
                                        class="h-5 px-1.5 py-0 text-[10px] font-normal"
                                    >
                                        {{ genre }}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <ScoreBadge :score="group.artist.score" size="md" />

                        <DropdownMenu v-if="!compareMode && !stackMode">
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    @click.stop
                                >
                                    <MoreHorizontal class="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" @click.stop>
                                <DropdownMenuItem
                                    @click="emit('view-artist', group.artist)"
                                >
                                    <ExternalLink class="mr-2 h-4 w-4" />
                                    {{ trans('lineups.tier_view_artist') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    @click="emit('start-stack', group.artist)"
                                >
                                    <Layers class="mr-2 h-4 w-4" />
                                    {{ trans('lineups.tier_create_stack') }}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    class="text-destructive"
                                    @click="emit('remove-artist', group.artist)"
                                >
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    {{
                                        trans('lineups.tier_remove_from_lineup')
                                    }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <!-- Inline Stack Action for Stack Mode -->
                        <div v-if="stackMode">
                            <StackActionButton
                                :is-adding-to-stack="!!isAddingAlternativesTo"
                                @click="
                                    isAddingAlternativesTo
                                        ? emit('select-artist', group.artist)
                                        : emit('start-stack', group.artist)
                                "
                            />
                        </div>
                    </div>

                    <!-- Stack Group -->
                    <div v-else-if="group.type === 'stack'" class="bg-muted/5">
                        <!-- Primary Artist -->
                        <div
                            :class="
                                cn(
                                    'flex items-center gap-4 border-l-4 border-[hsl(var(--stack-purple))] bg-[hsl(var(--stack-purple-bg))] p-4',
                                    compareMode || stackMode
                                        ? 'cursor-pointer hover:bg-[hsl(var(--stack-purple))]/10'
                                        : '',
                                )
                            "
                            @click="
                                (compareMode || stackMode) &&
                                emit('select-artist', group.stack.primary)
                            "
                        >
                            <ArtistAvatar
                                :artist="group.stack.primary"
                                size="sm"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="truncate font-bold">{{
                                        group.stack.primary.name
                                    }}</span>
                                </div>
                            </div>
                            <ScoreBadge
                                :score="group.stack.primary.score"
                                size="md"
                            />

                            <!-- Inline Stack Action for Stack Mode -->
                            <div v-if="stackMode">
                                <StackPrimaryActionButton
                                    :is-current-stack="
                                        isAddingAlternativesTo ===
                                        group.stack.id
                                    "
                                    @click="
                                        emit('start-stack', group.stack.primary)
                                    "
                                    @deselect="emit('deselect-stack')"
                                />
                            </div>

                            <DropdownMenu v-if="!compareMode && !stackMode">
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        @click.stop
                                    >
                                        <MoreHorizontal class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" @click.stop>
                                    <DropdownMenuItem
                                        @click="
                                            emit(
                                                'dissolve-stack',
                                                group.stack.id,
                                            )
                                        "
                                    >
                                        <X class="mr-2 h-4 w-4" />
                                        {{ $t('lineups.show_stack_dissolve') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        class="text-destructive"
                                        @click="
                                            emit(
                                                'remove-artist',
                                                group.stack.primary,
                                            )
                                        "
                                    >
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        {{
                                            trans(
                                                'lineups.tier_remove_from_lineup',
                                            )
                                        }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <!-- Alternatives -->
                        <div
                            v-for="alt in group.stack.alternatives"
                            :key="alt.id"
                            class="flex items-center gap-4 border-l-4 border-[hsl(var(--stack-purple))]/30 py-3 pr-4 pl-12 transition-colors hover:bg-[hsl(var(--stack-purple))]/5"
                        >
                            <ArtistAvatar :artist="alt" size="xs" />
                            <div class="min-w-0 flex-1">
                                <span class="text-sm font-medium">{{
                                    alt.name
                                }}</span>
                            </div>
                            <ScoreBadge :score="alt.score" size="sm" />

                            <!-- Inline Actions for Stack Mode -->
                            <StackAlternativeActions
                                v-if="stackMode"
                                @promote="emit('promote-artist', alt)"
                                @remove="emit('remove-from-stack', alt)"
                            />

                            <DropdownMenu v-if="!compareMode && !stackMode">
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-7 w-7"
                                        @click.stop
                                    >
                                        <MoreHorizontal class="h-3 w-3" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" @click.stop>
                                    <DropdownMenuItem
                                        @click="emit('promote-artist', alt)"
                                    >
                                        <ArrowUpCircle class="mr-2 h-4 w-4" />
                                        {{ $t('lineups.show_stack_promote') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="emit('remove-from-stack', alt)"
                                    >
                                        <X class="mr-2 h-4 w-4" />
                                        {{
                                            $t('lineups.show_stack_remove_alt')
                                        }}
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        class="text-destructive"
                                        @click="emit('remove-artist', alt)"
                                    >
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        {{
                                            trans(
                                                'lineups.tier_remove_from_lineup',
                                            )
                                        }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </template>

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
