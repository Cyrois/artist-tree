<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import LineupCard from '@/components/lineup/LineupCard.vue';
import ArtistCard from '@/components/artist/ArtistCard.vue';
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { getLineups } from '@/data/lineups';
import { getTrendingArtists } from '@/data/artists';
import type { Artist, Lineup } from '@/data/types';
import { Search, Music, TrendingUp, ChevronRight } from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import axios from 'axios';

interface ArtistSearchResult {
    id: number | null;
    spotify_id: string;
    name: string;
    genres: string[];
    image_url: string | null;
    exists_in_database: boolean;
    dummy_score: number;
}

const lineups = getLineups();
const trendingArtists = getTrendingArtists(5);

const searchQuery = ref('');
const searchResults = ref<ArtistSearchResult[]>([]);
const showSearchDropdown = ref(false);

const displayedResults = computed(() => searchResults.value.slice(0, 3));

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout>;
watch(searchQuery, (query) => {
    clearTimeout(searchTimeout);
    if (query.length < 2) {
        searchResults.value = [];
        showSearchDropdown.value = false;
        return;
    }
    searchTimeout = setTimeout(async () => {
        try {
            const response = await axios.get('/api/artists/search', {
                params: { q: query }
            });
            searchResults.value = response.data.data.map((artist: any) => ({
                ...artist,
                dummy_score: Math.floor(Math.random() * 40) + 60 // Random score 60-100
            }));
            showSearchDropdown.value = searchResults.value.length > 0;
        } catch (error) {
            console.error('Search failed', error);
            searchResults.value = [];
        }
    }, 300);
});

function handleLineupClick(lineup: Lineup) {
    router.visit(`/lineups/${lineup.id}`);
}

async function handleArtistClick(artist: ArtistSearchResult) {
    showSearchDropdown.value = false;
    searchQuery.value = '';

    if (artist.id) {
        router.visit(`/artist/${artist.id}`);
    } else {
        // Artist exists on Spotify but not in DB yet
        try {
            const response = await axios.post('/api/artists/select', {
                spotify_id: artist.spotify_id
            });
            const newId = response.data.data.id;
            router.visit(`/artist/${newId}`);
        } catch (error) {
            console.error('Failed to select artist', error);
        }
    }
}

function handleViewAllResults() {
    showSearchDropdown.value = false;
    router.visit(route('search'), { data: { q: searchQuery.value } });
}

function handleSearchFocus() {
    if (searchResults.value.length > 0) {
        showSearchDropdown.value = true;
    }
}

function handleSearchBlur() {
    // Delay to allow click on dropdown
    setTimeout(() => {
        showSearchDropdown.value = false;
    }, 200);
}

const breadcrumbs = [{ title: trans('common.breadcrumb_dashboard'), href: '/dashboard' }];
</script>

<template>
    <Head :title="$t('dashboard.page_title')" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-8">
            <!-- Hero Section -->
            <div class="relative rounded-2xl bg-gradient-to-br from-primary/5 via-primary/10 to-primary/5 p-8 md:p-12">
                <div class="max-w-2xl mx-auto text-center space-y-4">
                    <div class="flex items-center justify-center gap-3 mb-4">
                        <div class="w-12 h-12 rounded-xl bg-primary flex items-center justify-center">
                            <Music class="w-6 h-6 text-primary-foreground" />
                        </div>
                    </div>
                    <h1 class="text-3xl md:text-4xl font-bold tracking-tight">
                        {{ $t('dashboard.hero_title') }}
                    </h1>
                    <p class="text-muted-foreground text-lg">
                        {{ $t('dashboard.hero_subtitle') }}
                    </p>

                    <!-- Search -->
                    <div class="relative max-w-xl mx-auto mt-6">
                        <div class="relative">
                            <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="$t('dashboard.search_placeholder')"
                                class="bg-white pl-12 pr-4 py-6 text-lg rounded-xl border-2 focus:border-primary"
                                @focus="handleSearchFocus"
                                @blur="handleSearchBlur"
                            />
                        </div>

                        <!-- Search Dropdown -->
                        <div
                            v-if="showSearchDropdown"
                            class="absolute top-full left-0 right-0 mt-2 bg-background border rounded-xl shadow-lg z-50 overflow-hidden"
                        >
                            <div class="space-y-0">
                                <div
                                    v-for="artist in displayedResults"
                                    :key="artist.spotify_id"
                                    class="group px-4 py-3 hover:bg-muted cursor-pointer transition-colors flex items-center gap-4"
                                    @click="handleArtistClick(artist)"
                                >
                                    <!-- Image -->
                                    <img 
                                        :src="artist.image_url || '/placeholder.png'" 
                                        :alt="artist.name" 
                                        class="w-12 h-12 rounded-lg object-cover flex-shrink-0 bg-muted" 
                                    />
                                    
                                    <!-- Content -->
                                    <div class="flex-1 min-w-0 text-left">
                                        <p class="font-semibold text-foreground truncate">{{ artist.name }}</p>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            <Badge 
                                                v-for="genre in artist.genres.slice(0, 3)" 
                                                :key="genre"
                                                variant="secondary"
                                                class="text-[10px] px-1.5 py-0 h-5"
                                            >
                                                {{ genre }}
                                            </Badge>
                                        </div>
                                    </div>

                                    <!-- Score & Action -->
                                    <div class="flex items-center gap-3">
                                        <ScoreBadge :score="artist.dummy_score" />
                                        <ChevronRight class="w-4 h-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="border-t bg-muted/30 p-2 text-center">
                                <button 
                                    class="text-sm text-primary hover:underline font-medium flex items-center justify-center gap-1 w-full py-2 cursor-pointer"
                                    @click="handleViewAllResults"
                                >
                                    {{ $t('common.view_all_results') }}
                                    <ChevronRight class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Your Lineups Section -->
            <div>
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold">{{ $t('dashboard.lineups_section_title') }}</h2>
                        <p class="text-muted-foreground">{{ $t('dashboard.lineups_section_subtitle') }}</p>
                    </div>
                    <Button @click="router.visit('/lineups')">
                        {{ $t('dashboard.lineups_view_all_button') }}
                    </Button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <LineupCard
                        v-for="lineup in lineups"
                        :key="lineup.id"
                        :lineup="lineup"
                        @click="handleLineupClick"
                    />
                </div>
            </div>

            <!-- Trending Artists Section -->
            <div>
                <div class="flex items-center gap-2 mb-6">
                    <TrendingUp class="w-5 h-5 text-primary" />
                    <h2 class="text-2xl font-bold">{{ $t('dashboard.trending_section_title') }}</h2>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                    <ArtistCard
                        v-for="artist in trendingArtists"
                        :key="artist.id"
                        :artist="artist"
                        compact
                        :show-metrics="false"
                        @click="handleArtistClick"
                    />
                </div>
            </div>
        </div>
    </MainLayout>
</template>
