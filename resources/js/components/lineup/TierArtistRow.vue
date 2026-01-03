<script setup lang="ts">
import ArtistAvatar from '@/components/artist/ArtistAvatar.vue';
import StackActionButton from '@/components/lineup/StackActionButton.vue';
import StackAlternativeActions from '@/components/lineup/StackAlternativeActions.vue';
import StackPrimaryActionButton from '@/components/lineup/StackPrimaryActionButton.vue';
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Artist } from '@/data/types';
import type { GroupedArtist } from '@/types/lineup';
import { cn } from '@/lib/utils';
import { trans } from 'laravel-vue-i18n';
import {
    ArrowUpCircle,
    ExternalLink,
    Layers,
    MoreHorizontal,
    Trash2,
    X,
} from 'lucide-vue-next';

interface Props {
    group: GroupedArtist;
    compareMode?: boolean;
    stackMode?: boolean;
    selectedArtistIds?: number[];
    isAddingAlternativesTo?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    compareMode: false,
    stackMode: false,
    selectedArtistIds: () => [],
    isAddingAlternativesTo: null,
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

function isSelected(artistId: number) {
    return props.selectedArtistIds.includes(artistId);
}
</script>

<template>
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
            (compareMode || stackMode) && emit('select-artist', group.artist)
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

        <div class="min-w-0 flex-1 flex flex-col justify-between h-12">
            <div class="flex items-center gap-2">
                <span class="truncate font-medium leading-none">{{
                    group.artist.name
                }}</span>
            </div>
            <div>
                <ScoreBadge :score="group.artist.score" size="sm" />
            </div>
        </div>

        <DropdownMenu v-if="!compareMode && !stackMode">
            <DropdownMenuTrigger as-child>
                <Button variant="ghost" size="icon" class="h-8 w-8" @click.stop>
                    <MoreHorizontal class="h-4 w-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" @click.stop>
                <DropdownMenuItem @click="emit('view-artist', group.artist)">
                    <ExternalLink class="mr-2 h-4 w-4" />
                    {{ trans('lineups.tier_view_artist') }}
                </DropdownMenuItem>
                <DropdownMenuItem @click="emit('start-stack', group.artist)">
                    <Layers class="mr-2 h-4 w-4" />
                    {{ trans('lineups.tier_create_stack') }}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    class="text-destructive"
                    @click="emit('remove-artist', group.artist)"
                >
                    <Trash2 class="mr-2 h-4 w-4" />
                    {{ trans('lineups.tier_remove_from_lineup') }}
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
            <ArtistAvatar :artist="group.stack.primary" size="sm" />
            <div class="min-w-0 flex-1 flex flex-col justify-between h-12">
                <div class="flex items-center gap-2">
                    <span class="truncate font-bold leading-none">{{
                        group.stack.primary.name
                    }}</span>
                </div>
                <div>
                    <ScoreBadge :score="group.stack.primary.score" size="sm" />
                </div>
            </div>

            <!-- Inline Stack Action for Stack Mode -->
            <div v-if="stackMode">
                <StackPrimaryActionButton
                    :is-current-stack="isAddingAlternativesTo === group.stack.id"
                    @click="emit('start-stack', group.stack.primary)"
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
                        @click="emit('dissolve-stack', group.stack.id)"
                    >
                        <X class="mr-2 h-4 w-4" />
                        {{ $t('lineups.show_stack_dissolve') }}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        class="text-destructive"
                        @click="emit('remove-artist', group.stack.primary)"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        {{ trans('lineups.tier_remove_from_lineup') }}
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
            <div class="min-w-0 flex-1 flex items-center gap-2">
                <span class="truncate text-sm font-medium">{{ alt.name }}</span>
                <ScoreBadge :score="alt.score" size="sm" />
            </div>

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
                    <DropdownMenuItem @click="emit('promote-artist', alt)">
                        <ArrowUpCircle class="mr-2 h-4 w-4" />
                        {{ $t('lineups.show_stack_promote') }}
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="emit('remove-from-stack', alt)">
                        <X class="mr-2 h-4 w-4" />
                        {{ $t('lineups.show_stack_remove_alt') }}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        class="text-destructive"
                        @click="emit('remove-artist', alt)"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        {{ trans('lineups.tier_remove_from_lineup') }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </div>
</template>
