<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import TierSection from '@/components/lineup/TierSection.vue';
import ArtistAvatar from '@/components/artist/ArtistAvatar.vue';
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { tierOrder } from '@/data/constants';
import type { TierType } from '@/data/types';
import { Search, Layers, Scale, Download, Users, X, Loader2, Plus, Check, ChevronRight } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { useDebounceFn } from '@vueuse/core';
import { search as artistSearchRoute } from '@/routes/api/artists';
import axios from 'axios';

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

const displayedResults = computed(() => searchResults.value.slice(0, 3));

// Get artists by tier
function getArtistsByTier(tier: TierType) {
    if (!props.lineup) return [];
    
    // Map API structure to component expectations
    return props.lineup.artists[tier].map(artist => ({
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
        const response = await fetch(artistSearchRoute.url({ query: { q: query } }), {
            headers: { 'Accept': 'application/json' },
        });
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

async function addArtistToLineup(artist: SearchResultArtist) {
    if (addingArtistId.value) return;
    
    addingArtistId.value = artist.id || artist.spotify_id;
    
    try {
        let artistId = artist.id;
        
        if (!artistId) {
             const selectResponse = await axios.post('/api/artists/select', {
                spotify_id: artist.spotify_id
            });
            artistId = selectResponse.data.data.id;
        }

        await axios.post(route('lineups.artists.store', props.id), {
            artist_id: artistId
        });
        
        router.reload({ only: ['lineup'] });
    } catch (e) {
        console.error('Failed to add artist', e);
    } finally {
        addingArtistId.value = null;
    }
}

function isArtistInLineup(artist: SearchResultArtist) {
    if (artist.id) {
        return allArtists.value.some(a => a.id === artist.id);
    }
    return false;
}

function handleViewAllResults() {
    router.visit(artistSearchRoute.url({ query: { q: searchQuery.value } }));
}

const breadcrumbs = computed(() => 
    lineupBreadcrumbs(
        props.lineup?.name ?? trans('lineups.show_page_title'), 
        props.id
    )
);
</script>

<template>
    <Head :title="`${props.lineup?.name ?? $t('lineups.show_page_title')} - Artist-Tree`" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div v-if="props.lineup" class="space-y-6">
            <!-- Lineup Header Card -->
            <Card class="py-0">
                <CardContent class="p-6">
                    <div class="flex flex-col md:flex-row justify-between gap-6">
                        <!-- Info -->
                        <div class="flex-1">
                            <h1 class="text-3xl font-bold">{{ props.lineup.name }}</h1>
                            <p class="text-sm text-muted-foreground mt-1">
                                {{ $t('lineups.show_updated_prefix') }} {{ props.lineup.updatedAt }}
                            </p>
                            
                            <div class="flex flex-wrap items-center gap-8 mt-6">
                                <!-- Artist Count -->
                                <div class="flex items-center gap-3">
                                    <div class="p-2.5 rounded-full bg-muted">
                                        <Users class="w-5 h-5 text-muted-foreground" />
                                    </div>
                                    <div>
                                        <p class="text-xs text-muted-foreground font-medium uppercase tracking-wider mb-0.5">{{ $t('lineups.show_stats_artists') }}</p>
                                        <p class="text-xl font-bold leading-none">{{ props.lineup.stats.artistCount }}</p>
                                    </div>
                                </div>

                                <!-- Avg Score -->
                                <div class="flex items-center gap-3">
                                    <ScoreBadge 
                                        v-if="props.lineup.stats.avgScore" 
                                        :score="Math.round(props.lineup.stats.avgScore)" 
                                        size="lg"
                                    />
                                    <div v-else class="h-10 w-10 flex items-center justify-center rounded-full bg-muted">
                                        <span class="font-bold">-</span>
                                    </div>
                                    <div>
                                        <p class="text-xs text-muted-foreground font-medium uppercase tracking-wider">{{ $t('lineups.show_stats_avg_score') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-start gap-3">
                            <Button variant="outline">
                                <Download class="w-4 h-4 mr-2" />
                                {{ $t('lineups.show_export_button') }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Lineup Content -->
            <div class="space-y-6">
                <Card 
                    class="relative transition-all duration-300 ease-in-out p-1 gap-0" 
                    :class="{
                        'overflow-visible': isSearchExpanded, 
                        'overflow-hidden': !isSearchExpanded,
                        'rounded-b-none': isSearchExpanded && searchQuery.length >= 2
                    }"
                >
                    <div class="p-1 flex items-center">
                        <!-- Search Section -->
                        <div 
                            class="relative flex-1 transition-all duration-300 ease-in-out"
                        >
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground z-10" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="isSearchExpanded ? 'Search artists...' : 'Search and add artists...'"
                                class="pl-9 border-none focus-visible:ring-0 transition-all shadow-none h-10"
                                :class="isSearchExpanded ? 'bg-transparent' : 'bg-muted/50'"
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
                                <div v-if="!isSearchExpanded" class="flex items-center shrink-0 overflow-hidden whitespace-nowrap">
                                    <div class="w-[1px] h-8 bg-border mx-2" />
                                    <div class="flex gap-2 hidden sm:flex mr-2">
                                        <Button
                                            variant="outline"
                                            disabled
                                            class="gap-2 h-9"
                                        >
                                            <Layers class="w-4 h-4" />
                                            {{ $t('lineups.show_stack_button') }}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            disabled
                                            class="gap-2 h-9"
                                        >
                                            <Scale class="w-4 h-4" />
                                            {{ $t('lineups.show_compare_button') }}
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
                                <div v-if="isSearchExpanded" class="overflow-hidden">
                                    <Button 
                                        variant="ghost" 
                                        size="icon" 
                                        class="shrink-0 h-9 w-9 ml-2"
                                        @click="closeSearch"
                                    >
                                        <X class="w-4 h-4" />
                                    </Button>
                                </div>
                            </Transition>
                        </div>
                    </div>

                    <!-- Search Dropdown (Absolute) -->
                    <div 
                        v-if="isSearchExpanded && searchQuery.length >= 2" 
                        class="absolute top-full left-[-1px] right-[-1px] mt-0 bg-background border rounded-b-lg shadow-xl z-50 overflow-hidden"
                    >
                        <div v-if="isSearching" class="flex justify-center items-center py-8">
                            <Loader2 class="w-6 h-6 animate-spin text-muted-foreground" />
                        </div>
                        
                        <div v-else-if="searchResults.length === 0" class="text-center py-8 text-muted-foreground">
                            No artists found matching "{{ searchQuery }}"
                        </div>
                        
                        <div v-else>
                            <div class="divide-y">
                                <div 
                                    v-for="artist in displayedResults" 
                                    :key="artist.spotify_id"
                                    class="flex items-center justify-between p-3 hover:bg-muted/50 transition-colors"
                                >
                                    <div class="flex items-center gap-3 flex-1 min-w-0">
                                        <img 
                                            :src="artist.image_url || '/placeholder.png'" 
                                            :alt="artist.name" 
                                            class="w-10 h-10 rounded-md object-cover bg-muted flex-shrink-0" 
                                        />
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-sm truncate">{{ artist.name }}</p>
                                            <div class="flex flex-wrap gap-1 mt-0.5">
                                                <Badge 
                                                    v-for="genre in artist.genres.slice(0, 2)" 
                                                    :key="genre"
                                                    variant="secondary"
                                                    class="text-[10px] px-1.5 py-0 h-4"
                                                >
                                                    {{ genre }}
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <ScoreBadge :score="artist.score || artist.spotify_popularity || 0" />
                                        
                                        <Button 
                                            v-if="!isArtistInLineup(artist)"
                                            size="sm" 
                                            variant="ghost"
                                            class="h-8 w-8 p-0"
                                            :disabled="!!addingArtistId"
                                            @click="addArtistToLineup(artist)"
                                        >
                                            <Loader2 v-if="addingArtistId === (artist.id || artist.spotify_id)" class="w-4 h-4 animate-spin" />
                                            <Plus v-else class="w-4 h-4" />
                                        </Button>
                                        <div v-else class="h-8 w-8 flex items-center justify-center">
                                            <Check class="w-4 h-4 text-green-500" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- View All Link -->
                            <div class="border-t bg-muted/30 p-2 text-center">
                                <button 
                                    class="text-xs text-primary hover:underline font-medium flex items-center justify-center gap-1 w-full py-1 cursor-pointer"
                                    @click="handleViewAllResults"
                                >
                                    View all results for "{{ searchQuery }}"
                                    <ChevronRight class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                </Card>

                <!-- Mode Banners -->
                <div
                    v-if="stackMode"
                    class="flex items-center justify-between p-4 rounded-lg bg-[hsl(var(--stack-purple-bg))] border border-[hsl(var(--stack-purple))]/30"
                >
                    <p class="text-sm">
                        {{ $t('lineups.show_stack_mode_description') }}
                    </p>
                    <Button variant="outline" size="sm" @click="stackMode = false">
                        {{ $t('lineups.show_stack_mode_done') }}
                    </Button>
                </div>

                <div
                    v-if="compareMode"
                    class="flex items-center justify-between p-4 rounded-lg bg-[hsl(var(--compare-coral-bg))] border border-[hsl(var(--compare-coral))]/30"
                >
                    <div class="flex items-center gap-4">
                        <p class="text-sm">
                            {{ $t('lineups.show_compare_mode_description') }}
                        </p>
                        <div class="flex -space-x-2">
                            <ArtistAvatar
                                v-for="id in selectedArtistIds"
                                :key="id"
                                :artist="allArtists.find(a => a.id === id)!"
                                size="sm"
                                class="border-2 border-background"
                            />
                        </div>
                        <Badge v-if="selectedArtistIds.length > 0">{{ selectedArtistIds.length }}/4</Badge>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="ghost" size="sm" @click="clearSelection">{{ $t('lineups.show_compare_clear') }}</Button>
                        <Button size="sm" :disabled="selectedArtistIds.length < 2" @click="exitCompareMode">
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
        <div v-else class="text-center py-12">
            <p class="text-muted-foreground">{{ $t('lineups.show_not_found') }}</p>
            <Button class="mt-4" @click="router.visit('/lineups')">
                {{ $t('lineups.show_back_button') }}
            </Button>
        </div>
    </MainLayout>
</template>