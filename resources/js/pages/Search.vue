<script setup lang="ts">
import ArtistCardGrid from '@/components/artist/ArtistCardGrid.vue';
import RecentSearches from '@/components/artist/RecentSearches.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { useRecentSearches } from '@/composables/useRecentSearches';
import { allGenres } from '@/data/constants';
import type { Artist } from '@/data/types';
import MainLayout from '@/layouts/MainLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import { trans } from 'laravel-vue-i18n';
import {
    AlertCircle,
    ChevronDown,
    Loader2,
    Search,
    SlidersHorizontal,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

// API response type matching backend structure
interface ApiArtist {
    id: number | null;
    spotify_id: string;
    name: string;
    genres: string[];
    country: string | null;
    image_url: string | null;
    exists_in_database: boolean;
    source: 'local' | 'spotify';
    spotify_popularity: number;
    score: number;
}

// State
const { addSearch } = useRecentSearches();
const searchQuery = ref('');
const selectedGenres = ref<string[]>([]);
const scoreMin = ref(0);
const scoreMax = ref(100);
const sortBy = ref<'score' | 'name' | 'listeners'>('score');
const showFilters = ref(false);

// API state
const searchResults = ref<ApiArtist[]>([]);
const isLoading = ref(false);
const error = ref<string | null>(null);
const hasSearched = ref(false);

// Initialize search from URL param
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const q = params.get('q');
    if (q) {
        searchQuery.value = q;
    }
});

// Debounced search function (300ms as per CLAUDE.md)
const performSearch = useDebounceFn(async (query: string) => {
    if (!query || query.length < 2) {
        searchResults.value = [];
        hasSearched.value = false;
        return;
    }

    isLoading.value = true;
    error.value = null;
    hasSearched.value = true;

    try {
        const response = await fetch(
            `/api/artists/search?q=${encodeURIComponent(query)}`,
            {
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        if (!response.ok) {
            throw new Error(`Search failed: ${response.statusText}`);
        }

        const data = await response.json();
        searchResults.value = data.data || [];
    } catch (err) {
        error.value =
            err instanceof Error
                ? err.message
                : 'An error occurred while searching';
        searchResults.value = [];
    } finally {
        isLoading.value = false;
    }
}, 300);

// Watch search query and trigger debounced search
watch(searchQuery, (newValue) => {
    if (newValue.length >= 2) {
        isLoading.value = true; // Show loading immediately for better UX
    }
    performSearch(newValue);
});

// Convert API artists to display format for filtering/sorting
const filteredArtists = computed(() => {
    // Map API results to the Artist type expected by components
    let results: Artist[] = searchResults.value.map((apiArtist) => ({
        id: apiArtist.id ?? 0,
        name: apiArtist.name,
        genre: apiArtist.genres,
        score: apiArtist.score || 0,
        spotifyListeners: 0,
        spotifyPopularity: apiArtist.spotify_popularity || 0,
        spotifyFollowers: 0,
        youtubeSubscribers: 0,
        youtubeViews: 0,
        instagramFollowers: 0,
        twitterFollowers: 0,
        lastUpdated: '',
        country: apiArtist.country || '',
        formedYear: 0,
        label: '',
        bio: '',
        topTracks: [],
        albums: [],
        metricsHistory: { listeners: [], months: [] },
        tierSuggestion: 'mid_tier' as const,
        similarArtists: [],
        image: apiArtist.image_url ?? undefined,
        // Store extra data for navigation
        _spotifyId: apiArtist.spotify_id,
        _existsInDatabase: apiArtist.exists_in_database,
        _source: apiArtist.source,
    })) as (Artist & {
        _spotifyId: string;
        _existsInDatabase: boolean;
        _source: string;
    })[];

    // Genre filter
    if (selectedGenres.value.length > 0) {
        results = results.filter((a) =>
            a.genre.some((g) => selectedGenres.value.includes(g)),
        );
    }

    // Score range filter (only applicable if we have scores)
    results = results.filter(
        (a) => a.score >= scoreMin.value && a.score <= scoreMax.value,
    );

    // Sort
    results.sort((a, b) => {
        switch (sortBy.value) {
            case 'name':
                return a.name.localeCompare(b.name);
            case 'listeners':
                return b.spotifyListeners - a.spotifyListeners;
            case 'score':
            default:
                return b.score - a.score;
        }
    });

    return results;
});

const activeFilterCount = computed(() => {
    let count = 0;
    if (selectedGenres.value.length > 0) count++;
    if (scoreMin.value > 0 || scoreMax.value < 100) count++;
    return count;
});

function toggleGenre(genre: string) {
    const index = selectedGenres.value.indexOf(genre);
    if (index === -1) {
        selectedGenres.value.push(genre);
    } else {
        selectedGenres.value.splice(index, 1);
    }
}

function clearFilters() {
    selectedGenres.value = [];
    scoreMin.value = 0;
    scoreMax.value = 100;
}

async function handleArtistClick(
    artist: Artist & { _spotifyId?: string; _existsInDatabase?: boolean },
) {
    // Add to recent searches
    if (artist._spotifyId) {
        addSearch({
            id: artist.id,
            spotify_id: artist._spotifyId,
            name: artist.name,
            genres: artist.genre,
            image_url: artist.image || null,
            spotify_popularity: artist.spotifyPopularity || 0,
            spotify_followers: artist.spotifyFollowers || 0,
            score: artist.score || 0,
        });
    }

    // Navigate using database ID if available, otherwise use Spotify ID to create/select
    if (artist.id && artist.id > 0) {
        router.visit(`/artist/${artist.id}`);
    } else if (artist._spotifyId) {
        try {
            // Select (create) artist in backend using Spotify ID
            const response = await axios.post('/api/artists/select', {
                spotify_id: artist._spotifyId,
            });
            const newId = response.data.data.id;
            router.visit(`/artist/${newId}`);
        } catch (error) {
            console.error('Failed to select artist', error);
            // Fallback (might fail if route doesn't exist, but worth a try or just alert)
            // router.visit(`/artist?spotify_id=${artist._spotifyId}`);
        }
    }
}

const sortOptions = [
    { value: 'score', label: trans('artists.search_sort_score') },
    { value: 'name', label: trans('artists.search_sort_name') },
    { value: 'listeners', label: trans('artists.search_sort_listeners') },
];

const { search: searchBreadcrumbs } = useBreadcrumbs();
const breadcrumbs = searchBreadcrumbs();
</script>

<template>
    <Head :title="$t('artists.search_page_title')" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Search Header -->
            <div class="flex flex-col gap-4 sm:flex-row">
                <!-- Search Input -->
                <div class="relative flex-1">
                    <Search
                        class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="$t('artists.search_input_placeholder')"
                        class="pl-10"
                    />
                </div>

                <!-- Filter Toggle -->
                <Button
                    variant="outline"
                    @click="showFilters = !showFilters"
                    :class="{ 'border-primary': showFilters }"
                >
                    <SlidersHorizontal class="mr-2 h-4 w-4" />
                    {{ $t('artists.search_filters_button') }}
                    <Badge
                        v-if="activeFilterCount > 0"
                        variant="secondary"
                        class="ml-2"
                    >
                        {{ activeFilterCount }}
                    </Badge>
                </Button>

                <!-- Sort Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline">
                            {{ $t('artists.search_sort_button') }}
                            <ChevronDown class="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            v-for="option in sortOptions"
                            :key="option.value"
                            @click="sortBy = option.value as any"
                            :class="{ 'bg-accent': sortBy === option.value }"
                        >
                            {{ option.label }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <!-- Recent Searches -->
            <RecentSearches
                v-if="!hasSearched && !isLoading && searchQuery.length < 2"
            />

            <!-- Filter Panel -->
            <Card v-if="showFilters">
                <CardContent class="space-y-6 pt-6">
                    <!-- Genres -->
                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="font-medium">
                                {{ $t('artists.search_genres_title') }}
                            </h3>
                            <Button
                                v-if="selectedGenres.length > 0"
                                variant="ghost"
                                size="sm"
                                @click="selectedGenres = []"
                            >
                                {{ $t('artists.search_clear_button') }}
                            </Button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="genre in allGenres.slice(0, 15)"
                                :key="genre"
                                :variant="
                                    selectedGenres.includes(genre)
                                        ? 'default'
                                        : 'outline'
                                "
                                class="cursor-pointer transition-colors"
                                @click="toggleGenre(genre)"
                            >
                                {{ genre }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Score Range -->
                    <div>
                        <h3 class="mb-3 font-medium">
                            {{ $t('artists.search_score_range_title') }}
                        </h3>
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-muted-foreground">{{
                                    $t('artists.search_score_min')
                                }}</span>
                                <Input
                                    v-model.number="scoreMin"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-20"
                                />
                            </div>
                            <span class="text-muted-foreground">-</span>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-muted-foreground">{{
                                    $t('artists.search_score_max')
                                }}</span>
                                <Input
                                    v-model.number="scoreMax"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-20"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Clear All -->
                    <div class="flex justify-end border-t pt-2">
                        <Button variant="outline" @click="clearFilters">
                            {{ $t('artists.search_clear_all_filters') }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Loading State -->
            <div
                v-if="isLoading"
                class="flex items-center justify-center py-12"
            >
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                <span class="ml-3 text-muted-foreground">{{
                    $t('common.loading')
                }}</span>
            </div>

            <!-- Error State -->
            <Card v-else-if="error" class="border-destructive">
                <CardContent
                    class="flex items-center gap-3 pt-6 text-destructive"
                >
                    <AlertCircle class="h-5 w-5" />
                    <p>{{ error }}</p>
                </CardContent>
            </Card>

            <!-- Results Count -->
            <div
                v-else-if="hasSearched"
                class="flex items-center justify-between"
            >
                <p class="text-muted-foreground">
                    <span class="font-medium text-foreground">{{
                        filteredArtists.length
                    }}</span>
                    {{ $t('artists.search_results_count') }}
                </p>
            </div>

            <!-- No Results Message -->
            <div
                v-if="
                    hasSearched &&
                    !isLoading &&
                    !error &&
                    filteredArtists.length === 0
                "
                class="py-12 text-center"
            >
                <Search class="mx-auto h-12 w-12 text-muted-foreground/50" />
                <h3 class="mt-4 text-lg font-medium">
                    {{ $t('artists.search_no_results_title') }}
                </h3>
                <p class="mt-2 text-muted-foreground">
                    {{ $t('artists.search_no_results_description') }}
                </p>
            </div>

            <!-- Results Grid -->
            <ArtistCardGrid
                v-if="!isLoading && !error && filteredArtists.length > 0"
                :artists="filteredArtists"
                :columns="4"
                @select-artist="handleArtistClick"
            />
        </div>
    </MainLayout>
</template>
