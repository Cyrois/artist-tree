<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ArtistAvatar from '@/components/artist/ArtistAvatar.vue';
import ArtistCard from '@/components/artist/ArtistCard.vue';
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import MiniChart from '@/components/chart/MiniChart.vue';
import ScoreBreakdown from '@/components/chart/ScoreBreakdown.vue';
import { getArtistById, getSimilarArtists } from '@/data/artists';
import { formatNumber } from '@/data/constants';
import type { Artist } from '@/data/types';
import { ArrowLeft, Plus, Scale, Globe, Calendar, Disc, TrendingUp, RefreshCw, Music, Instagram, Twitter, Youtube } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Props {
    id: number;
}

const props = defineProps<Props>();

const artist = computed(() => getArtistById(props.id));
const similarArtists = computed(() => artist.value ? getSimilarArtists(props.id) : []);

const activeTab = ref<'overview' | 'data'>('overview');

// Mock score breakdown
const scoreBreakdown = computed(() => {
    if (!artist.value) return [];
    return [
        { label: 'Spotify Listeners', value: artist.value.spotifyListeners, weight: 0.4, contribution: (artist.value.score * 0.4) },
        { label: 'Spotify Popularity', value: artist.value.spotifyPopularity, weight: 0.3, contribution: (artist.value.score * 0.3) },
        { label: 'YouTube Subscribers', value: artist.value.youtubeSubscribers, weight: 0.3, contribution: (artist.value.score * 0.3) },
    ];
});

function handleArtistClick(a: Artist) {
    router.visit(`/artist/${a.id}`);
}

const breadcrumbs = computed(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Search Artists', href: '/search' },
    { title: artist.value?.name ?? 'Artist', href: `/artist/${props.id}` },
]);
</script>

<template>
    <Head :title="`${artist?.name ?? 'Artist'} - Artist-Tree`" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div v-if="artist" class="space-y-8">
            <!-- Back button -->
            <Button variant="ghost" size="sm" @click="router.visit('/search')">
                <ArrowLeft class="w-4 h-4 mr-2" />
                Back to Search
            </Button>

            <!-- Header -->
            <div class="flex flex-col md:flex-row gap-6">
                <ArtistAvatar :artist="artist" size="xl" />

                <div class="flex-1 space-y-4">
                    <div>
                        <div class="flex items-center gap-4">
                            <h1 class="text-3xl font-bold">{{ artist.name }}</h1>
                            <ScoreBadge :score="artist.score" size="lg" />
                        </div>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <Badge v-for="genre in artist.genre" :key="genre" variant="secondary">
                                {{ genre }}
                            </Badge>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-4 text-sm text-muted-foreground">
                        <div class="flex items-center gap-1">
                            <Globe class="w-4 h-4" />
                            {{ artist.country }}
                        </div>
                        <div class="flex items-center gap-1">
                            <Calendar class="w-4 h-4" />
                            Since {{ artist.formedYear }}
                        </div>
                        <div class="flex items-center gap-1">
                            <Disc class="w-4 h-4" />
                            {{ artist.label }}
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <Button>
                            <Plus class="w-4 h-4 mr-2" />
                            Add to Lineup
                        </Button>
                        <Button variant="outline">
                            <Scale class="w-4 h-4 mr-2" />
                            Compare
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-b">
                <div class="flex gap-6">
                    <button
                        :class="[
                            'pb-3 text-sm font-medium transition-colors border-b-2 -mb-px',
                            activeTab === 'overview' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeTab = 'overview'"
                    >
                        Overview
                    </button>
                    <button
                        :class="[
                            'pb-3 text-sm font-medium transition-colors border-b-2 -mb-px',
                            activeTab === 'data' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeTab = 'data'"
                    >
                        Data & Metrics
                    </button>
                </div>
            </div>

            <!-- Overview Tab -->
            <div v-if="activeTab === 'overview'" class="space-y-8">
                <!-- Bio -->
                <div>
                    <h2 class="text-lg font-semibold mb-3">About</h2>
                    <p class="text-muted-foreground">{{ artist.bio }}</p>
                </div>

                <!-- Quick Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card>
                        <CardContent class="pt-6">
                            <p class="text-sm text-muted-foreground">Monthly Listeners</p>
                            <p class="text-2xl font-bold">{{ formatNumber(artist.spotifyListeners) }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <p class="text-sm text-muted-foreground">Spotify Popularity</p>
                            <p class="text-2xl font-bold">{{ artist.spotifyPopularity }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <p class="text-sm text-muted-foreground">YouTube Subs</p>
                            <p class="text-2xl font-bold">{{ formatNumber(artist.youtubeSubscribers) }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <p class="text-sm text-muted-foreground">Instagram</p>
                            <p class="text-2xl font-bold">{{ formatNumber(artist.instagramFollowers) }}</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Top Tracks -->
                <div>
                    <h2 class="text-lg font-semibold mb-3">Top Tracks</h2>
                    <div class="space-y-2">
                        <div
                            v-for="(track, index) in artist.topTracks"
                            :key="track"
                            class="flex items-center gap-3 p-3 rounded-lg bg-muted/50"
                        >
                            <span class="w-6 text-center text-muted-foreground">{{ index + 1 }}</span>
                            <Music class="w-4 h-4 text-muted-foreground" />
                            <span>{{ track }}</span>
                        </div>
                    </div>
                </div>

                <!-- Albums -->
                <div>
                    <h2 class="text-lg font-semibold mb-3">Discography</h2>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="album in artist.albums" :key="album" variant="outline" class="py-1.5 px-3">
                            {{ album }}
                        </Badge>
                    </div>
                </div>

                <!-- Similar Artists -->
                <div v-if="similarArtists.length > 0">
                    <h2 class="text-lg font-semibold mb-3">Similar Artists</h2>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <ArtistCard
                            v-for="a in similarArtists"
                            :key="a.id"
                            :artist="a"
                            compact
                            @click="handleArtistClick"
                        />
                    </div>
                </div>

                <!-- External Links -->
                <div>
                    <h2 class="text-lg font-semibold mb-3">Listen & Follow</h2>
                    <div class="flex flex-wrap gap-3">
                        <Button variant="outline" class="bg-[hsl(var(--spotify))]/10 border-[hsl(var(--spotify))]/30 hover:bg-[hsl(var(--spotify))]/20">
                            <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>
                            </svg>
                            Spotify
                        </Button>
                        <Button variant="outline" class="bg-[hsl(var(--youtube))]/10 border-[hsl(var(--youtube))]/30 hover:bg-[hsl(var(--youtube))]/20">
                            <Youtube class="w-4 h-4 mr-2" />
                            YouTube
                        </Button>
                        <Button variant="outline" class="bg-[hsl(var(--instagram))]/10 border-[hsl(var(--instagram))]/30 hover:bg-[hsl(var(--instagram))]/20">
                            <Instagram class="w-4 h-4 mr-2" />
                            Instagram
                        </Button>
                        <Button variant="outline">
                            <Twitter class="w-4 h-4 mr-2" />
                            Twitter
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Data & Metrics Tab -->
            <div v-if="activeTab === 'data'" class="space-y-8">
                <!-- Metrics Grid -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card>
                        <CardContent class="pt-6">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-sm text-muted-foreground">Monthly Listeners</p>
                                <TrendingUp class="w-4 h-4 text-[hsl(var(--score-high))]" />
                            </div>
                            <p class="text-2xl font-bold">{{ formatNumber(artist.spotifyListeners) }}</p>
                            <p class="text-xs text-[hsl(var(--score-high))]">+12.4% from last month</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <p class="text-sm text-muted-foreground">Spotify Popularity</p>
                            <p class="text-2xl font-bold">{{ artist.spotifyPopularity }}/100</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <p class="text-sm text-muted-foreground">Spotify Followers</p>
                            <p class="text-2xl font-bold">{{ formatNumber(artist.spotifyFollowers) }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <p class="text-sm text-muted-foreground">YouTube Subscribers</p>
                            <p class="text-2xl font-bold">{{ formatNumber(artist.youtubeSubscribers) }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <p class="text-sm text-muted-foreground">Instagram Followers</p>
                            <p class="text-2xl font-bold">{{ formatNumber(artist.instagramFollowers) }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <p class="text-sm text-muted-foreground">Twitter Followers</p>
                            <p class="text-2xl font-bold">{{ formatNumber(artist.twitterFollowers) }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <p class="text-sm text-muted-foreground">YouTube Views</p>
                            <p class="text-2xl font-bold">{{ formatNumber(artist.youtubeViews) }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <p class="text-sm text-muted-foreground">Active Since</p>
                            <p class="text-2xl font-bold">{{ artist.formedYear }}</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Listeners Trend Chart -->
                <Card>
                    <CardHeader>
                        <CardTitle>Monthly Listeners Trend</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <MiniChart
                            :data="artist.metricsHistory.listeners"
                            :labels="artist.metricsHistory.months"
                            :height="120"
                            :show-labels="true"
                        />
                    </CardContent>
                </Card>

                <!-- Score Breakdown -->
                <Card>
                    <CardHeader>
                        <CardTitle>Score Breakdown</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ScoreBreakdown :metrics="scoreBreakdown" />
                    </CardContent>
                </Card>

                <!-- Data Freshness -->
                <div class="flex items-center justify-between p-4 rounded-lg bg-muted/50">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <RefreshCw class="w-4 h-4" />
                        <span>Last updated: {{ artist.lastUpdated }}</span>
                    </div>
                    <Button variant="outline" size="sm">
                        <RefreshCw class="w-4 h-4 mr-2" />
                        Refresh Data
                    </Button>
                </div>
            </div>
        </div>

        <!-- Not Found -->
        <div v-else class="text-center py-12">
            <p class="text-muted-foreground">Artist not found.</p>
            <Button class="mt-4" @click="router.visit('/search')">
                Back to Search
            </Button>
        </div>
    </MainLayout>
</template>
