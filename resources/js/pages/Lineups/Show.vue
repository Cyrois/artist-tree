<script setup lang="ts">
import ArtistAvatar from '@/components/artist/ArtistAvatar.vue';
import AddToLineupModal from '@/components/lineup/AddToLineupModal.vue';
import TierSection from '@/components/lineup/TierSection.vue';
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { tierOrder } from '@/data/constants';
import type { TierType } from '@/data/types';
import MainLayout from '@/layouts/MainLayout.vue';
import { search as artistSearchRoute } from '@/routes/api/artists';
import { Head, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import { trans } from 'laravel-vue-i18n';
import {
    Check,
    ChevronRight,
    Download,
    Layers,
    Loader2,
    MoreHorizontal,
    Pencil,
    Plus,
    Scale,
    Search,
    Trash2,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ApiArtist {
    id: number;
    name: string;
    genres: string[];
    image_url: string | null;
    score: number;
    lineup_tier: TierType;
    // Map properties for compatibility with TierSection if needed
    genre?: string[];
}

interface SearchResultArtist {
    id: number | null;
    spotify_id: string;
    name: string;
    image_url: string | null;
    genres: string[];
    score: number;
    exists_in_database: boolean;
    source: 'local' | 'spotify';
    spotify_popularity: number;
}

interface Props {
    id: number;
    lineup: {
        id: number;
        name: string;
        updatedAt: string;
        artists: Record<TierType, ApiArtist[]>;
        artistStatuses: any;
        stats: {
            artistCount: number;
            avgScore: number;
        };
    };
}

const props = defineProps<Props>();
const { lineup: lineupBreadcrumbs } = useBreadcrumbs();

// Flatten artists for search/avatar lookup
const allArtists = computed(() => {
    if (!props.lineup) return [];
    return Object.values(props.lineup.artists).flat();
});

// Mode states
const stackMode = ref(false);
const compareMode = ref(false);
const selectedArtistIds = ref<number[]>([]);

// Search State
const searchQuery = ref('');
const isSearchExpanded = ref(false);
const searchResults = ref<SearchResultArtist[]>([]);
const isSearching = ref(false);
const addingArtistId = ref<string | number | null>(null);

// Add to Lineup Modal State
const isAddModalOpen = ref(false);
const artistToAdd = ref<SearchResultArtist | null>(null);
const suggestedTier = ref<TierType | null>(null);
const isAddingToLineup = ref(false);

const displayedResults = computed(() => searchResults.value.slice(0, 3));

// Get artists by tier
function getArtistsByTier(tier: TierType) {
    if (!props.lineup) return [];

    // Map API structure to component expectations
    return props.lineup.artists[tier].map((artist) => ({
        ...artist,
        image: artist.image_url, // Map image_url for ArtistAvatar
        genre: artist.genres || [], // Map genres for TierSection
    }));
}

function handleArtistSelect(artist: ApiArtist) {
    if (compareMode.value) {
        const index = selectedArtistIds.value.indexOf(artist.id);
        if (index === -1 && selectedArtistIds.value.length < 4) {
            selectedArtistIds.value.push(artist.id);
        } else if (index !== -1) {
            selectedArtistIds.value.splice(index, 1);
        }
    }
}

function handleArtistView(artist: ApiArtist) {
    if (!compareMode.value && !stackMode.value) {
        router.visit(`/artist/${artist.id}`);
    }
}

function clearSelection() {
    selectedArtistIds.value = [];
}

function exitCompareMode() {
    compareMode.value = false;
    selectedArtistIds.value = [];
}

// Search Logic
const performSearch = useDebounceFn(async (query: string) => {
    if (!query || query.length < 2) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const response = await fetch(
            artistSearchRoute.url({ query: { q: query } }),
            {
                headers: { Accept: 'application/json' },
            },
        );
        const data = await response.json();
        searchResults.value = data.data || [];
    } catch (e) {
        console.error('Search failed', e);
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
}, 300);

watch(searchQuery, (newVal) => {
    if (newVal.length >= 2) isSearching.value = true;
    performSearch(newVal);
});

function expandSearch() {
    isSearchExpanded.value = true;
}

function closeSearch() {
    isSearchExpanded.value = false;
    searchQuery.value = '';
    searchResults.value = [];
}

function calculateSuggestedTier(artistScore: number): TierType | null {
    if (!props.lineup || props.lineup.stats.artistCount === 0) return null;

    let bestTier: TierType | null = null;
    let minDiff = Infinity;

    for (const tier of tierOrder) {
        const artists = props.lineup.artists[tier];
        // If a tier has no artists, we can skip it for average calculation purposes
        // OR we might consider "empty tiers" as valid targets if we had a baseline average for them.
        // For now, based on "relative to lineup's tier averages", we only compare against existing averages.
        // If all tiers are empty, we return null (handled by artistCount check above).
        if (!artists || artists.length === 0) continue;

        const totalScore = artists.reduce((sum, a) => sum + a.score, 0);
        const avg = totalScore / artists.length;
        const diff = Math.abs(artistScore - avg);

        if (diff < minDiff) {
            minDiff = diff;
            bestTier = tier;
        }
    }

    // If we couldn't find a best tier (e.g. all tiers empty somehow despite artistCount > 0), return null.
    return bestTier;
}

function openAddModal(artist: SearchResultArtist) {
    if (addingArtistId.value) return;

    artistToAdd.value = artist;
    const score = artist.score || artist.spotify_popularity || 0;
    suggestedTier.value = calculateSuggestedTier(score);
    isAddModalOpen.value = true;
}

async function confirmAddArtist(payload: {
    artist: SearchResultArtist;
    tier: TierType;
}) {
    const { artist, tier } = payload;

    isAddingToLineup.value = true;
    addingArtistId.value = artist.id || artist.spotify_id; // To show spinner in search list if visible

    try {
        let artistId = artist.id;

        if (!artistId) {
            const selectResponse = await axios.post('/api/artists/select', {
                spotify_id: artist.spotify_id,
            });
            artistId = selectResponse.data.data.id;
        }

        await axios.post(`/lineups/${props.id}/artists`, {
            artist_id: artistId,
            tier: tier,
        });
        isAddModalOpen.value = false;
        router.reload({ only: ['lineup'] });
    } catch (e) {
        console.error('Failed to add artist', e);
    } finally {
        isAddingToLineup.value = false;
        addingArtistId.value = null;
    }
}

function isArtistInLineup(artist: SearchResultArtist) {
    if (artist.id) {
        return allArtists.value.some((a) => a.id === artist.id);
    }
    return false;
}

function handleViewAllResults() {
    router.visit(artistSearchRoute.url({ query: { q: searchQuery.value } }));
}

const breadcrumbs = computed(() =>
    lineupBreadcrumbs(
        props.lineup?.name ?? trans('lineups.show_page_title'),
        props.id,
    ),
);
</script>

<template>
    <Head
        :title="`${props.lineup?.name ?? $t('lineups.show_page_title')} - Artist-Tree`"
    />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div v-if="props.lineup" class="space-y-6">
            <!-- Lineup Header Card -->
            <Card class="py-0">
                <CardContent class="p-6">
                    <div
                        class="flex flex-col justify-between gap-6 md:flex-row"
                    >
                        <!-- Info -->
                        <div class="flex-1">
                            <h1 class="text-3xl font-bold">
                                {{ props.lineup.name }}
                            </h1>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ $t('lineups.show_updated_prefix') }}
                                {{ props.lineup.updatedAt }}
                            </p>

                            <div class="mt-6 flex flex-wrap items-center gap-8">
                                <!-- Artist Count -->
                                <div class="flex items-center gap-3">
                                    <div class="rounded-full bg-muted p-2.5">
                                        <Users
                                            class="h-5 w-5 text-muted-foreground"
                                        />
                                    </div>
                                    <div>
                                        <p
                                            class="mb-0.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                        >
                                            {{
                                                $t('lineups.show_stats_artists')
                                            }}
                                        </p>
                                        <p
                                            class="text-xl leading-none font-bold"
                                        >
                                            {{ props.lineup.stats.artistCount }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Avg Score -->
                                <div class="flex items-center gap-3">
                                    <ScoreBadge
                                        v-if="props.lineup.stats.avgScore"
                                        :score="
                                            Math.round(
                                                props.lineup.stats.avgScore,
                                            )
                                        "
                                        size="lg"
                                    />
                                    <div
                                        v-else
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-muted"
                                    >
                                        <span class="font-bold">-</span>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                        >
                                            {{
                                                $t(
                                                    'lineups.show_stats_avg_score',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-start gap-3">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="outline" size="icon">
                                        <MoreHorizontal class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem disabled>
                                        <Pencil class="mr-2 h-4 w-4" />
                                        {{ $t('common.action_edit') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem disabled>
                                        <Download class="mr-2 h-4 w-4" />
                                        {{ $t('lineups.show_export_button') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        disabled
                                        class="text-destructive"
                                    >
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        {{ $t('common.action_delete') }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Lineup Content -->
            <div class="space-y-6">
                <Card
                    class="relative gap-0 p-1 transition-all duration-300 ease-in-out"
                    :class="{
                        'overflow-visible': isSearchExpanded,
                        'overflow-hidden': !isSearchExpanded,
                        'rounded-b-none':
                            isSearchExpanded && searchQuery.length >= 2,
                    }"
                >
                    <div class="flex items-center p-1">
                        <!-- Search Section -->
                        <div
                            class="relative flex-1 transition-all duration-300 ease-in-out"
                        >
                            <Search
                                class="absolute top-1/2 left-3 z-10 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="
                                    isSearchExpanded
                                        ? 'Search artists...'
                                        : 'Search and add artists...'
                                "
                                class="h-10 border-none pl-9 shadow-none transition-all focus-visible:ring-0"
                                :class="
                                    isSearchExpanded
                                        ? 'bg-transparent'
                                        : 'bg-muted/50'
                                "
                                @focus="expandSearch"
                            />
                        </div>

                        <!-- Actions Section & Close Button Transition -->
                        <div class="flex items-center overflow-hidden">
                            <Transition
                                enter-active-class="transition-all duration-300 ease-in-out"
                                leave-active-class="transition-all duration-300 ease-in-out"
                                enter-from-class="max-w-0 opacity-0"
                                enter-to-class="max-w-[300px] opacity-100"
                                leave-from-class="max-w-[300px] opacity-100"
                                leave-to-class="max-w-0 opacity-0"
                            >
                                <div
                                    v-if="!isSearchExpanded"
                                    class="flex shrink-0 items-center overflow-hidden whitespace-nowrap"
                                >
                                    <div class="mx-2 h-8 w-[1px] bg-border" />
                                    <div class="mr-2 flex hidden gap-2 sm:flex">
                                        <Button
                                            variant="outline"
                                            disabled
                                            class="h-9 gap-2"
                                        >
                                            <Layers class="h-4 w-4" />
                                            {{
                                                $t('lineups.show_stack_button')
                                            }}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            disabled
                                            class="h-9 gap-2"
                                        >
                                            <Scale class="h-4 w-4" />
                                            {{
                                                $t(
                                                    'lineups.show_compare_button',
                                                )
                                            }}
                                        </Button>
                                    </div>
                                </div>
                            </Transition>

                            <Transition
                                enter-active-class="transition-all duration-300 ease-in-out"
                                leave-active-class="transition-all duration-300 ease-in-out"
                                enter-from-class="max-w-0 opacity-0"
                                enter-to-class="max-w-[50px] opacity-100"
                                leave-from-class="max-w-[50px] opacity-100"
                                leave-to-class="max-w-0 opacity-0"
                            >
                                <div
                                    v-if="isSearchExpanded"
                                    class="overflow-hidden"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="ml-2 h-9 w-9 shrink-0"
                                        @click="closeSearch"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                            </Transition>
                        </div>
                    </div>

                    <!-- Search Dropdown (Absolute) -->
                    <div
                        v-if="isSearchExpanded && searchQuery.length >= 2"
                        class="absolute top-full right-[-1px] left-[-1px] z-50 mt-0 overflow-hidden rounded-b-lg border bg-background shadow-xl"
                    >
                        <div
                            v-if="isSearching"
                            class="flex items-center justify-center py-8"
                        >
                            <Loader2
                                class="h-6 w-6 animate-spin text-muted-foreground"
                            />
                        </div>

                        <div
                            v-else-if="searchResults.length === 0"
                            class="py-8 text-center text-muted-foreground"
                        >
                            No artists found matching "{{ searchQuery }}"
                        </div>

                        <div v-else>
                            <div class="divide-y">
                                <div
                                    v-for="artist in displayedResults"
                                    :key="artist.spotify_id"
                                    class="flex items-center justify-between p-3 transition-colors hover:bg-muted/50"
                                >
                                    <div
                                        class="flex min-w-0 flex-1 items-center gap-3"
                                    >
                                        <img
                                            :src="
                                                artist.image_url ||
                                                '/placeholder.png'
                                            "
                                            :alt="artist.name"
                                            class="h-10 w-10 flex-shrink-0 rounded-md bg-muted object-cover"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="truncate text-sm font-medium"
                                            >
                                                {{ artist.name }}
                                            </p>
                                            <div
                                                class="mt-0.5 flex flex-wrap gap-1"
                                            >
                                                <Badge
                                                    v-for="genre in artist.genres.slice(
                                                        0,
                                                        2,
                                                    )"
                                                    :key="genre"
                                                    variant="secondary"
                                                    class="h-4 px-1.5 py-0 text-[10px]"
                                                >
                                                    {{ genre }}
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <ScoreBadge
                                            :score="
                                                artist.score ||
                                                artist.spotify_popularity ||
                                                0
                                            "
                                        />

                                        <Button
                                            v-if="!isArtistInLineup(artist)"
                                            size="sm"
                                            variant="ghost"
                                            class="h-8 w-8 p-0"
                                            :disabled="!!addingArtistId"
                                            @click="openAddModal(artist)"
                                        >
                                            <Loader2
                                                v-if="
                                                    addingArtistId ===
                                                    (artist.id ||
                                                        artist.spotify_id)
                                                "
                                                class="h-4 w-4 animate-spin"
                                            />
                                            <Plus v-else class="h-4 w-4" />
                                        </Button>
                                        <div
                                            v-else
                                            class="flex h-8 w-8 items-center justify-center"
                                        >
                                            <Check
                                                class="h-4 w-4 text-green-500"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- View All Link -->
                            <div class="border-t bg-muted/30 p-2 text-center">
                                <button
                                    class="flex w-full cursor-pointer items-center justify-center gap-1 py-1 text-xs font-medium text-primary hover:underline"
                                    @click="handleViewAllResults"
                                >
                                    View all results for "{{ searchQuery }}"
                                    <ChevronRight class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                </Card>

                <!-- Mode Banners -->
                <div
                    v-if="stackMode"
                    class="flex items-center justify-between rounded-lg border border-[hsl(var(--stack-purple))]/30 bg-[hsl(var(--stack-purple-bg))] p-4"
                >
                    <p class="text-sm">
                        {{ $t('lineups.show_stack_mode_description') }}
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="stackMode = false"
                    >
                        {{ $t('lineups.show_stack_mode_done') }}
                    </Button>
                </div>

                <div
                    v-if="compareMode"
                    class="flex items-center justify-between rounded-lg border border-[hsl(var(--compare-coral))]/30 bg-[hsl(var(--compare-coral-bg))] p-4"
                >
                    <div class="flex items-center gap-4">
                        <p class="text-sm">
                            {{ $t('lineups.show_compare_mode_description') }}
                        </p>
                        <div class="flex -space-x-2">
                            <ArtistAvatar
                                v-for="id in selectedArtistIds"
                                :key="id"
                                :artist="allArtists.find((a) => a.id === id)!"
                                size="sm"
                                class="border-2 border-background"
                            />
                        </div>
                        <Badge v-if="selectedArtistIds.length > 0"
                            >{{ selectedArtistIds.length }}/4</Badge
                        >
                    </div>
                    <div class="flex gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="clearSelection"
                            >{{ $t('lineups.show_compare_clear') }}</Button
                        >
                        <Button
                            size="sm"
                            :disabled="selectedArtistIds.length < 2"
                            @click="exitCompareMode"
                        >
                            {{ $t('lineups.show_compare_submit') }}
                        </Button>
                    </div>
                </div>

                <!-- Tier Sections -->
                <div class="space-y-4">
                    <TierSection
                        v-for="tier in tierOrder"
                        :key="tier"
                        :tier="tier"
                        :artists="getArtistsByTier(tier)"
                        :compare-mode="compareMode"
                        :selected-artist-ids="selectedArtistIds"
                        @select-artist="handleArtistSelect"
                        @view-artist="handleArtistView"
                        @remove-artist="() => {}"
                    />
                </div>
            </div>
        </div>

        <!-- Not Found -->
        <div v-else class="py-12 text-center">
            <p class="text-muted-foreground">
                {{ $t('lineups.show_not_found') }}
            </p>
            <Button class="mt-4" @click="router.visit('/lineups')">
                {{ $t('lineups.show_back_button') }}
            </Button>
        </div>

        <AddToLineupModal
            v-if="props.lineup"
            v-model:open="isAddModalOpen"
            :artist="artistToAdd"
            :lineup-name="props.lineup.name"
            :suggested-tier="suggestedTier"
            :is-adding="isAddingToLineup"
            @add="confirmAddArtist"
        />
    </MainLayout>
</template>
