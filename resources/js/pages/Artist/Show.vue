<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { 
    ArrowLeft, 
    Loader2, 
    AlertCircle, 
    MapPin, 
    Music, 
    Youtube, 
    Instagram, 
    Twitter, 
    TrendingUp, 
    Users, 
    Plus,
    ArrowRightLeft,
    RefreshCw
} from 'lucide-vue-next';
import { ref, onMounted, computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { show as artistShowRoute } from '@/routes/api/artists';
import ArtistMediaList from '@/components/artist/ArtistMediaList.vue';
import ArtistSimilarArtists from '@/components/artist/ArtistSimilarArtists.vue';

// API response type matching backend structure
interface ApiArtist {
    id: number;
    spotify_id: string;
    name: string;
    genres: string[];
    image_url: string | null;
    metrics: {
        spotify_monthly_listeners: number | null;
        spotify_popularity: number | null;
        youtube_subscribers: number | null;
    } | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    id: number;
}

const props = defineProps<Props>();

// State
const artist = ref<ApiArtist | null>(null);
const isLoading = ref(true);
const error = ref<string | null>(null);
const activeTab = ref<'overview' | 'data'>('overview');

// Fetch artist details on mount
onMounted(async () => {
    try {
        const response = await fetch(artistShowRoute.url(props.id), {
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            if (response.status === 404) {
                throw new Error('Artist not found');
            }
            throw new Error(`Failed to load artist: ${response.statusText}`);
        }

        const data = await response.json();
        artist.value = data.data;
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'An error occurred while loading artist';
    } finally {
        isLoading.value = false;
    }
});

// Stub data merging
const artistData = computed(() => {
    if (!artist.value) return null;
    
    return {
        ...artist.value,
        // Stubbed fields
        score: 94, // Mock score
        location: 'United States',
        description: `${artist.value.name} is a critically acclaimed artist known for their innovative approach to music and storytelling. They have garnered a massive following and numerous awards throughout their career.`,
        genres: artist.value.genres && artist.value.genres.length > 0 
            ? artist.value.genres 
            : ['Hip-Hop', 'Rap', 'West Coast'], // Fallback if empty
        metrics: {
            spotify_monthly_listeners: artist.value.metrics?.spotify_monthly_listeners ?? 58200000,
            spotify_popularity: artist.value.metrics?.spotify_popularity ?? 92,
            youtube_subscribers: artist.value.metrics?.youtube_subscribers ?? 18400000,
            instagram_followers: 17800000,
            twitter_followers: 13200000,
            youtube_views: 8200000000,
            spotify_followers: 32100000,
        },
        similar_artists: [
            { id: 101, name: 'Tyler, The Creator', score: 88, image_url: null },
            { id: 102, name: 'Billie Eilish', score: 91, image_url: null },
            { id: 103, name: 'Charli XCX', score: 88, image_url: null },
        ]
    };
});

const formatNumber = (num: number | null | undefined): string => {
    if (num === null || num === undefined) return '-';
    if (num >= 1000000000) return (num / 1000000000).toFixed(1) + 'B';
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
};

const breadcrumbs = computed(() => [
    { title: trans('common.breadcrumb_dashboard'), href: '/dashboard' },
    { title: trans('common.breadcrumb_search_artists'), href: '/search' },
    { title: artist.value?.name ?? trans('artists.show_page_title'), href: `/artist/${props.id}` },
]);

const pageTitle = computed(() =>
    artist.value ? `${artist.value.name} - Artist-Tree` : 'Artist - Artist-Tree'
);
</script>

<template>
    <Head :title="pageTitle" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center justify-center py-24">
            <div class="flex flex-col items-center gap-4">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                <p class="text-muted-foreground">{{ $t('common.loading') }}</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex items-center justify-center py-24">
            <div class="flex flex-col items-center gap-4 text-center">
                <AlertCircle class="h-12 w-12 text-destructive" />
                <div>
                    <p class="font-medium text-destructive">{{ error }}</p>
                    <p class="text-sm text-muted-foreground mt-1">{{ $t('common.error_try_again') }}</p>
                </div>
                <Button variant="outline" @click="router.visit('/search')">
                    <ArrowLeft class="w-4 h-4 mr-2" />
                    {{ $t('artists.show_back_button') }}
                </Button>
            </div>
        </div>

        <!-- Artist Content -->
        <div v-else-if="artistData" class="space-y-6">
            <!-- Back button -->
            <Button variant="ghost" size="sm" @click="router.visit('/search')" class="pl-0 hover:bg-transparent hover:text-primary">
                <ArrowLeft class="w-4 h-4 mr-2" />
                {{ $t('artists.show_back_button') }}
            </Button>

            <!-- Artist Header -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-col md:flex-row gap-6">
                        <!-- Artist Image -->
                        <div class="shrink-0">
                            <img
                                v-if="artistData.image_url"
                                :src="artistData.image_url"
                                :alt="artistData.name"
                                class="h-40 w-40 rounded-lg object-cover shadow-lg"
                            />
                            <div
                                v-else
                                class="flex h-40 w-40 items-center justify-center rounded-lg bg-muted"
                            >
                                <span class="text-4xl font-bold text-muted-foreground">
                                    {{ artistData.name.charAt(0).toUpperCase() }}
                                </span>
                            </div>
                        </div>

                        <!-- Info & Actions -->
                        <div class="flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h1 class="text-3xl font-bold">{{ artistData.name }}</h1>
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            <span 
                                                v-for="genre in artistData.genres.slice(0, 4)" 
                                                :key="genre"
                                                class="px-2 py-0.5 rounded-full bg-secondary text-secondary-foreground text-xs font-medium"
                                            >
                                                {{ genre }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-center bg-primary/10 text-primary font-bold text-xl h-12 w-12 rounded-full border-2 border-primary/20">
                                        {{ artistData.score }}
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-4 mt-4 text-sm text-muted-foreground">
                                    <div class="flex items-center gap-1">
                                        <MapPin class="w-4 h-4" />
                                        <span>{{ artistData.location }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-3 mt-6">
                                <Button disabled class="gap-2">
                                    <Plus class="w-4 h-4" />
                                    {{ $t('artists.show_add_to_lineup') }}
                                </Button>
                                <Button disabled variant="outline" class="gap-2">
                                    <ArrowRightLeft class="w-4 h-4" />
                                    {{ $t('artists.show_compare') }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Tabs Navigation -->
            <div class="border-b">
                <div class="flex gap-6">
                    <button 
                        @click="activeTab = 'overview'"
                        class="pb-2 text-sm font-medium transition-colors border-b-2"
                        :class="activeTab === 'overview' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    >
                        {{ $t('artists.show_tab_overview') }}
                    </button>
                    <button 
                        @click="activeTab = 'data'"
                        class="pb-2 text-sm font-medium transition-colors border-b-2"
                        :class="activeTab === 'data' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    >
                        {{ $t('artists.show_tab_data') }}
                    </button>
                </div>
            </div>

            <!-- Overview Tab -->
            <div v-if="activeTab === 'overview'" class="space-y-6">
                <!-- Description -->
                <p class="text-muted-foreground leading-relaxed">
                    {{ artistData.description }}
                </p>

                <!-- Quick Metrics Row -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card>
                        <CardContent class="p-4">
                            <p class="text-xs text-muted-foreground font-medium uppercase">{{ $t('artists.metric_monthly_listeners') }}</p>
                            <p class="text-2xl font-bold mt-1">{{ formatNumber(artistData.metrics.spotify_monthly_listeners) }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-4">
                            <p class="text-xs text-muted-foreground font-medium uppercase">{{ $t('artists.metric_spotify_popularity') }}</p>
                            <div class="flex items-baseline gap-1 mt-1">
                                <p class="text-2xl font-bold">{{ artistData.metrics.spotify_popularity }}</p>
                                <span class="text-sm text-muted-foreground">/ 100</span>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-4">
                            <p class="text-xs text-muted-foreground font-medium uppercase">{{ $t('artists.metric_youtube_subs') }}</p>
                            <p class="text-2xl font-bold mt-1">{{ formatNumber(artistData.metrics.youtube_subscribers) }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-4">
                            <p class="text-xs text-muted-foreground font-medium uppercase">Instagram</p>
                            <p class="text-2xl font-bold mt-1">{{ formatNumber(artistData.metrics.instagram_followers) }}</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Content Grid: Top Tracks & Releases -->
                <div class="grid md:grid-cols-2 gap-6">
                    <ArtistMediaList :artist-id="props.id" variant="top-tracks" />
                    <ArtistMediaList :artist-id="props.id" variant="recent-releases" />
                </div>

                <!-- Similar Artists -->
                <ArtistSimilarArtists :artist-id="props.id" />

                <!-- External Links -->
                <div>
                    <h3 class="font-semibold text-lg mb-4">External Links</h3>
                    <div class="flex flex-wrap gap-3">
                        <Button 
                            as-child
                            variant="outline" 
                            class="gap-2 text-green-600 hover:text-green-700 hover:bg-green-50"
                        >
                            <a :href="`https://open.spotify.com/artist/${artistData.spotify_id}`" target="_blank" rel="noopener noreferrer">
                                <Music class="w-4 h-4" />
                                Spotify
                            </a>
                        </Button>
                        <Button variant="outline" disabled class="gap-2 text-red-600 hover:text-red-700 hover:bg-red-50">
                            <Youtube class="w-4 h-4" />
                            YouTube
                        </Button>
                        <Button variant="outline" disabled class="gap-2 text-pink-600 hover:text-pink-700 hover:bg-pink-50">
                            <Instagram class="w-4 h-4" />
                            Instagram
                        </Button>
                        <Button variant="outline" disabled class="gap-2 hover:bg-slate-100">
                            <Twitter class="w-4 h-4" />
                            X / Twitter
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Data Tab (Stubbed) -->
            <div v-else-if="activeTab === 'data'" class="space-y-6">
                <!-- Detailed Metrics Grid -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card>
                        <CardContent class="p-4 space-y-2">
                            <div class="flex items-center gap-2 text-muted-foreground text-sm font-medium">
                                <Music class="w-4 h-4" />
                                <span>Monthly Listeners</span>
                            </div>
                            <p class="text-2xl font-bold">{{ formatNumber(artistData.metrics.spotify_monthly_listeners) }}</p>
                            <p class="text-xs text-green-600 flex items-center gap-1 font-medium">
                                <TrendingUp class="w-3 h-3" />
                                29.3%
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-4 space-y-2">
                            <div class="flex items-center gap-2 text-muted-foreground text-sm font-medium">
                                <TrendingUp class="w-4 h-4" />
                                <span>Spotify Popularity</span>
                            </div>
                            <p class="text-2xl font-bold">{{ artistData.metrics.spotify_popularity }} <span class="text-sm font-normal text-muted-foreground">/ 100</span></p>
                            <p class="text-xs text-muted-foreground">Out of 100</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-4 space-y-2">
                            <div class="flex items-center gap-2 text-muted-foreground text-sm font-medium">
                                <Users class="w-4 h-4" />
                                <span>Spotify Followers</span>
                            </div>
                            <p class="text-2xl font-bold">{{ formatNumber(artistData.metrics.spotify_followers) }}</p>
                            <p class="text-xs text-muted-foreground">-</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-4 space-y-2">
                            <div class="flex items-center gap-2 text-muted-foreground text-sm font-medium">
                                <Youtube class="w-4 h-4" />
                                <span>YouTube Subs</span>
                            </div>
                            <p class="text-2xl font-bold">{{ formatNumber(artistData.metrics.youtube_subscribers) }}</p>
                            <p class="text-xs text-muted-foreground">{{ formatNumber(artistData.metrics.youtube_views) }} views</p>
                        </CardContent>
                    </Card>
                     <Card>
                        <CardContent class="p-4 space-y-2">
                            <div class="flex items-center gap-2 text-muted-foreground text-sm font-medium">
                                <Instagram class="w-4 h-4" />
                                <span>Instagram</span>
                            </div>
                            <p class="text-2xl font-bold">{{ formatNumber(artistData.metrics.instagram_followers) }}</p>
                        </CardContent>
                    </Card>
                     <Card>
                        <CardContent class="p-4 space-y-2">
                            <div class="flex items-center gap-2 text-muted-foreground text-sm font-medium">
                                <Twitter class="w-4 h-4" />
                                <span>X Twitter</span>
                            </div>
                            <p class="text-2xl font-bold">{{ formatNumber(artistData.metrics.twitter_followers) }}</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Charts Area (Placeholder) -->
                <div class="grid md:grid-cols-3 gap-6">
                    <Card class="md:col-span-2">
                        <CardHeader>
                            <CardTitle class="text-base">Monthly Listeners Trend</CardTitle>
                        </CardHeader>
                        <CardContent class="h-64 flex items-end justify-between px-8 pb-4">
                            <!-- Fake Bar Chart -->
                            <div class="w-12 bg-primary/20 hover:bg-primary/30 h-[40%] rounded-t transition-all relative group">
                                <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-popover text-popover-foreground text-xs px-2 py-1 rounded shadow opacity-0 group-hover:opacity-100">Aug</div>
                            </div>
                            <div class="w-12 bg-primary/40 hover:bg-primary/50 h-[55%] rounded-t transition-all relative group">
                                <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-popover text-popover-foreground text-xs px-2 py-1 rounded shadow opacity-0 group-hover:opacity-100">Sep</div>
                            </div>
                            <div class="w-12 bg-primary/60 hover:bg-primary/70 h-[65%] rounded-t transition-all relative group">
                                <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-popover text-popover-foreground text-xs px-2 py-1 rounded shadow opacity-0 group-hover:opacity-100">Oct</div>
                            </div>
                            <div class="w-12 bg-primary/80 hover:bg-primary/90 h-[85%] rounded-t transition-all relative group">
                                <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-popover text-popover-foreground text-xs px-2 py-1 rounded shadow opacity-0 group-hover:opacity-100">Nov</div>
                            </div>
                            <div class="w-12 bg-primary hover:bg-primary/90 h-[100%] rounded-t transition-all relative group">
                                <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-popover text-popover-foreground text-xs px-2 py-1 rounded shadow opacity-0 group-hover:opacity-100">Dec</div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                         <CardHeader>
                            <CardTitle class="text-base">Score Breakdown</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-6">
                             <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>Spotify Listeners</span>
                                    <span class="font-medium">9.5/10</span>
                                </div>
                                <div class="h-2 bg-muted rounded-full overflow-hidden">
                                    <div class="h-full bg-primary w-[95%]"></div>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>Spotify Popularity</span>
                                    <span class="font-medium">9.2/10</span>
                                </div>
                                <div class="h-2 bg-muted rounded-full overflow-hidden">
                                    <div class="h-full bg-primary w-[92%]"></div>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>YouTube Subs</span>
                                    <span class="font-medium">8.0/10</span>
                                </div>
                                <div class="h-2 bg-muted rounded-full overflow-hidden">
                                    <div class="h-full bg-primary w-[80%]"></div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                
                <div class="flex justify-between items-center p-4 bg-muted/30 rounded-lg border">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Loader2 class="w-4 h-4" />
                        <span>Data last updated 2 hours ago</span>
                    </div>
                    <Button variant="outline" size="sm" class="gap-2">
                        <RefreshCw class="w-3 h-3" />
                        Refresh Data
                    </Button>
                </div>
            </div>
        </div>

        <!-- Not Found (fallback) -->
        <div v-else class="text-center py-12">
            <p class="text-muted-foreground">{{ $t('artists.show_not_found') }}</p>
            <Button class="mt-4" @click="router.visit('/search')">
                {{ $t('artists.show_back_button') }}
            </Button>
        </div>
    </MainLayout>
</template>
