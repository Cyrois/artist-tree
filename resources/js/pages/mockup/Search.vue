<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import MockupLayout from '@/layouts/MockupLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import ArtistCardGrid from '@/components/mockup/artist/ArtistCardGrid.vue';
import ArtistCard from '@/components/mockup/artist/ArtistCard.vue';
import { getArtists, getTrendingArtists, getSimilarArtists, searchArtists, filterArtistsByGenre, filterArtistsByScoreRange, sortArtists } from '@/data/artists';
import { allGenres } from '@/data/constants';
import type { Artist } from '@/data/types';
import { Search, SlidersHorizontal, X, ChevronDown, TrendingUp } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';

// State
const searchQuery = ref('');
const selectedGenres = ref<string[]>([]);
const scoreMin = ref(0);
const scoreMax = ref(100);
const sortBy = ref<'score' | 'name' | 'listeners'>('score');
const showFilters = ref(false);

// Get all artists initially
const allArtists = getArtists();
const trendingArtists = getTrendingArtists(10);

// Filtered artists
const filteredArtists = computed(() => {
    let results = [...allArtists];

    // Text search
    if (searchQuery.value.length >= 2) {
        results = searchArtists(searchQuery.value);
    }

    // Genre filter
    if (selectedGenres.value.length > 0) {
        results = results.filter(a =>
            a.genre.some(g => selectedGenres.value.includes(g))
        );
    }

    // Score range filter
    results = results.filter(a => a.score >= scoreMin.value && a.score <= scoreMax.value);

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

// Similar artists (based on first result's genres)
const similarArtists = computed(() => {
    if (filteredArtists.value.length === 0) return [];
    const firstArtist = filteredArtists.value[0];
    return getSimilarArtists(firstArtist.id).slice(0, 5);
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

function handleArtistClick(artist: Artist) {
    router.visit(`/mockup/artist/${artist.id}`);
}

const sortOptions = [
    { value: 'score', label: 'Score (High to Low)' },
    { value: 'name', label: 'Name (A-Z)' },
    { value: 'listeners', label: 'Listeners (High to Low)' },
];

const breadcrumbs = [
    { title: 'Dashboard', href: '/mockup' },
    { title: 'Search Artists', href: '/mockup/search' },
];
</script>

<template>
    <Head title="Search Artists - Artist-Tree" />
    <MockupLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Search Header -->
            <div class="flex flex-col sm:flex-row gap-4">
                <!-- Search Input -->
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search artists by name or genre..."
                        class="pl-10"
                    />
                </div>

                <!-- Filter Toggle -->
                <Button
                    variant="outline"
                    @click="showFilters = !showFilters"
                    :class="{ 'border-primary': showFilters }"
                >
                    <SlidersHorizontal class="w-4 h-4 mr-2" />
                    Filters
                    <Badge v-if="activeFilterCount > 0" variant="secondary" class="ml-2">
                        {{ activeFilterCount }}
                    </Badge>
                </Button>

                <!-- Sort Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline">
                            Sort
                            <ChevronDown class="w-4 h-4 ml-2" />
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

            <!-- Filter Panel -->
            <Card v-if="showFilters">
                <CardContent class="pt-6 space-y-6">
                    <!-- Genres -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-medium">Genres</h3>
                            <Button v-if="selectedGenres.length > 0" variant="ghost" size="sm" @click="selectedGenres = []">
                                Clear
                            </Button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="genre in allGenres.slice(0, 15)"
                                :key="genre"
                                :variant="selectedGenres.includes(genre) ? 'default' : 'outline'"
                                class="cursor-pointer transition-colors"
                                @click="toggleGenre(genre)"
                            >
                                {{ genre }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Score Range -->
                    <div>
                        <h3 class="font-medium mb-3">Score Range</h3>
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-muted-foreground">Min:</span>
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
                                <span class="text-sm text-muted-foreground">Max:</span>
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
                    <div class="flex justify-end pt-2 border-t">
                        <Button variant="outline" @click="clearFilters">
                            Clear All Filters
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Results Count -->
            <div class="flex items-center justify-between">
                <p class="text-muted-foreground">
                    <span class="font-medium text-foreground">{{ filteredArtists.length }}</span> artists found
                </p>
            </div>

            <!-- Results Grid -->
            <ArtistCardGrid
                :artists="filteredArtists"
                :columns="4"
                @select-artist="handleArtistClick"
            />

            <!-- Similar Artists (if searching) -->
            <div v-if="similarArtists.length > 0 && searchQuery.length >= 2" class="pt-6 border-t">
                <h3 class="text-lg font-semibold mb-4">Similar Artists</h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <ArtistCard
                        v-for="artist in similarArtists"
                        :key="artist.id"
                        :artist="artist"
                        compact
                        :show-metrics="false"
                        @click="handleArtistClick"
                    />
                </div>
            </div>

            <!-- Trending Artists -->
            <div class="pt-6 border-t">
                <div class="flex items-center gap-2 mb-4">
                    <TrendingUp class="w-5 h-5 text-primary" />
                    <h3 class="text-lg font-semibold">Trending Artists</h3>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
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
