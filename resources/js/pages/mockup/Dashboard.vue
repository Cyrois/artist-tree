<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import MockupLayout from '@/layouts/MockupLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import LineupCard from '@/components/mockup/lineup/LineupCard.vue';
import ArtistCard from '@/components/mockup/artist/ArtistCard.vue';
import { getLineups } from '@/data/lineups';
import { searchArtists, getTrendingArtists } from '@/data/artists';
import type { Artist, Lineup } from '@/data/types';
import { Search, Plus, Music, TrendingUp } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';

const lineups = getLineups();
const trendingArtists = getTrendingArtists(5);

const searchQuery = ref('');
const searchResults = ref<Artist[]>([]);
const showSearchDropdown = ref(false);

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout>;
watch(searchQuery, (query) => {
    clearTimeout(searchTimeout);
    if (query.length < 2) {
        searchResults.value = [];
        showSearchDropdown.value = false;
        return;
    }
    searchTimeout = setTimeout(() => {
        searchResults.value = searchArtists(query).slice(0, 8);
        showSearchDropdown.value = searchResults.value.length > 0;
    }, 300);
});

function handleLineupClick(lineup: Lineup) {
    router.visit(`/mockup/lineups/${lineup.id}`);
}

function handleArtistClick(artist: Artist) {
    showSearchDropdown.value = false;
    searchQuery.value = '';
    router.visit(`/mockup/artist/${artist.id}`);
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

const breadcrumbs = [{ title: 'Dashboard', href: '/mockup' }];
</script>

<template>
    <Head title="Dashboard - Artist-Tree" />
    <MockupLayout :breadcrumbs="breadcrumbs">
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
                        Build Your Dream Lineup
                    </h1>
                    <p class="text-muted-foreground text-lg">
                        Search for artists, compare metrics, and create the perfect festival lineup.
                    </p>

                    <!-- Search -->
                    <div class="relative max-w-xl mx-auto mt-6">
                        <div class="relative">
                            <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search for artists..."
                                class="pl-12 pr-4 py-6 text-lg rounded-xl border-2 focus:border-primary"
                                @focus="handleSearchFocus"
                                @blur="handleSearchBlur"
                            />
                        </div>

                        <!-- Search Dropdown -->
                        <div
                            v-if="showSearchDropdown"
                            class="absolute top-full left-0 right-0 mt-2 bg-background border rounded-xl shadow-lg z-50 max-h-96 overflow-y-auto"
                        >
                            <div class="p-2 space-y-1">
                                <div
                                    v-for="artist in searchResults"
                                    :key="artist.id"
                                    class="p-3 rounded-lg hover:bg-muted cursor-pointer transition-colors"
                                    @click="handleArtistClick(artist)"
                                >
                                    <div class="flex items-center gap-3">
                                        <img :src="artist.image" :alt="artist.name" class="w-10 h-10 rounded-lg" />
                                        <div>
                                            <p class="font-medium">{{ artist.name }}</p>
                                            <p class="text-sm text-muted-foreground">{{ artist.genre.slice(0, 2).join(', ') }}</p>
                                        </div>
                                        <span class="ml-auto text-sm font-bold text-primary">{{ artist.score }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Your Lineups Section -->
            <div>
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold">Your Lineups</h2>
                        <p class="text-muted-foreground">Manage your festival lineups</p>
                    </div>
                    <Button @click="router.visit('/mockup/lineups')">
                        View All
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
                    <h2 class="text-2xl font-bold">Trending Artists</h2>
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
    </MockupLayout>
</template>
