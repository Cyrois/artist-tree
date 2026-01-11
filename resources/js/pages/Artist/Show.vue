<script setup lang="ts">
import ArtistAddToLineupModal from '@/components/artist/ArtistAddToLineupModal.vue';
import ArtistMediaList from '@/components/artist/ArtistMediaList.vue';
import ArtistSimilarArtists from '@/components/artist/ArtistSimilarArtists.vue';
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import MainLayout from '@/layouts/MainLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRightLeft,
    ExternalLink,
    Globe,
    Instagram,
    Loader2,
    Music,
    Plus,
    RefreshCw,
    TrendingUp,
    Users,
    Youtube,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

// API response type matching backend structure
interface ApiArtist {
    id: number;
    spotify_id: string;
    name: string;
    genres: string[];
    country: string | null;
    image_url: string | null;
    score: number;
    links: { platform: string; url: string }[];
    metrics: {
        spotify_monthly_listeners: number | null;
        spotify_popularity: number | null;
        spotify_followers: number | null;
        youtube_subscribers: number | null;
        youtube_avg_views: number | null;
        youtube_avg_likes: number | null;
        youtube_avg_comments: number | null;
        youtube_videos_analyzed: number | null;
        instagram_followers: number | null;
        tiktok_followers: number | null;
        refreshed_at?: string;
        youtube_refreshed_at?: string;
        youtube_analytics_refreshed_at?: string;
    } | null;
    created_at: string;
    updated_at: string;
}

interface Lineup {
    id: number;
    name: string;
    artists_count: number;
}

interface Props {
    id: number;
    userLineups: Lineup[];
}

const props = defineProps<Props>();

const { artist: artistBreadcrumbs } = useBreadcrumbs();

// State
const artist = ref<ApiArtist | null>(null);
const isLoading = ref(true);
const error = ref<string | null>(null);
const activeTab = ref<'overview' | 'data' | 'links'>('overview');
const showAddToLineupModal = ref(false);
const isRefreshing = ref(false);

// Fetch artist details on mount
onMounted(async () => {
    try {
        const response = await fetch(`/api/artists/${props.id}`, {
            credentials: 'include',
            headers: {
                Accept: 'application/json',
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
        error.value =
            err instanceof Error
                ? err.message
                : trans('artists.error_load_failed');
    } finally {
        isLoading.value = false;
    }
});

const formatNumber = (num: number | null | undefined): string => {
    if (num === null || num === undefined || num === 0) return 'N/A';
    if (num >= 1000000000) return (num / 1000000000).toFixed(1) + 'B';
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
};

const refreshArtistData = async () => {
    if (!artist.value || isRefreshing.value) return;
    
    isRefreshing.value = true;
    try {
        const response = await fetch(`/api/artists/${artist.value.id}/refresh`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`Failed to refresh artist data: ${response.statusText}`);
        }

        const data = await response.json();
        artist.value = data.data;
    } catch (err) {
        console.error('Failed to refresh artist data:', err);
        // Could show a toast notification here
    } finally {
        isRefreshing.value = false;
    }
};

function handleAddToLineupSubmit(data: any) {
    router.post(
        `/api/lineups/${data.lineupId}/artists`,
        {
            artist_id: data.artistId,
            tier: data.tier,
        },
        {
            onSuccess: () => {
                showAddToLineupModal.value = false;
            },
        },
    );
}

const breadcrumbs = computed(() =>
    artistBreadcrumbs(
        artist.value?.name ?? trans('artists.show_page_title'),
        props.id,
    ),
);

const pageTitle = computed(() =>
    artist.value
        ? `${artist.value.name} - Artist-Tree`
        : `${trans('artists.show_page_title')} - Artist-Tree`,
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
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ $t('common.error_try_again') }}
                    </p>
                </div>
                <Button variant="outline" @click="router.visit('/search')">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    {{ $t('artists.show_back_button') }}
                </Button>
            </div>
        </div>

        <!-- Artist Content -->
        <div v-else-if="artist" class="space-y-6">
            <!-- Back button -->
            <Button
                variant="ghost"
                size="sm"
                @click="router.visit('/search')"
                class="pl-0 hover:bg-transparent hover:text-primary hover:underline"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />
                {{ $t('artists.show_back_button') }}
            </Button>

            <!-- Artist Header -->
            <Card class="py-0">
                <CardContent class="p-6">
                    <div class="flex flex-col gap-6 md:flex-row">
                        <!-- Artist Image -->
                        <div class="shrink-0">
                            <img
                                v-if="artist.image_url"
                                :src="artist.image_url"
                                :alt="artist.name"
                                class="h-40 w-40 rounded-lg object-cover shadow-lg"
                            />
                            <div
                                v-else
                                class="flex h-40 w-40 items-center justify-center rounded-lg bg-muted"
                            >
                                <span
                                    class="text-4xl font-bold text-muted-foreground"
                                >
                                    {{ artist.name.charAt(0).toUpperCase() }}
                                </span>
                            </div>
                        </div>

                        <!-- Info & Actions -->
                        <div class="flex flex-1 flex-col justify-between">
                            <div>
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h1 class="text-3xl font-bold">
                                            {{ artist.name }}
                                        </h1>
                                        <div
                                            v-if="
                                                artist.genres &&
                                                artist.genres.length > 0
                                            "
                                            class="mt-2 flex flex-wrap gap-2"
                                        >
                                            <span
                                                v-for="genre in artist.genres.slice(
                                                    0,
                                                    4,
                                                )"
                                                :key="genre"
                                                class="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
                                            >
                                                {{ genre }}
                                            </span>
                                        </div>
                                    </div>
                                    <ScoreBadge
                                        v-if="artist.score"
                                        :score="Math.round(artist.score)"
                                        size="lg"
                                        class="text-xl"
                                        title="Artist-Tree Score"
                                    />
                                </div>

                                <div
                                    class="mt-4 flex items-center gap-4 text-sm text-muted-foreground"
                                >
                                    <div
                                        v-if="artist.country"
                                        class="flex items-center gap-1"
                                    >
                                        <Globe class="h-4 w-4" />
                                        <span>{{ artist.country }}</span>
                                    </div>
                                    <div
                                        v-if="artist.spotify_id"
                                        class="flex items-center gap-1"
                                    >
                                        <Music class="h-4 w-4" />
                                        <span>{{
                                            $t('artists.show_verified_spotify')
                                        }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 flex gap-3">
                                <Button
                                    class="gap-2"
                                    @click="showAddToLineupModal = true"
                                >
                                    <Plus class="h-4 w-4" />
                                    {{
                                        $t('artists.show_add_to_lineup_button')
                                    }}
                                </Button>
                                <Button
                                    disabled
                                    variant="outline"
                                    class="gap-2"
                                >
                                    <ArrowRightLeft class="h-4 w-4" />
                                    {{ $t('artists.show_compare_button') }}
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
                        class="border-b-2 pb-2 text-sm font-medium transition-colors"
                        :class="
                            activeTab === 'overview'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ $t('artists.show_tab_overview') }}
                    </button>
                    <button
                        @click="activeTab = 'data'"
                        class="border-b-2 pb-2 text-sm font-medium transition-colors"
                        :class="
                            activeTab === 'data'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ $t('artists.show_tab_data') }}
                    </button>
                    <button
                        @click="activeTab = 'links'"
                        class="border-b-2 pb-2 text-sm font-medium transition-colors"
                        :class="
                            activeTab === 'links'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                    >
                        External Links
                    </button>
                </div>
            </div>

            <!-- Overview Tab -->
            <div v-if="activeTab === 'overview'" class="space-y-6">
                <!-- Description (Generic for now) -->
                <p class="leading-relaxed text-muted-foreground">
                    {{
                        $t('artists.show_description_template', {
                            name: artist.name,
                        })
                    }}
                </p>

                <!-- Quick Metrics Row -->
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <Card class="py-0">
                        <CardContent class="p-4">
                            <p
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                {{ $t('artists.metric_spotify_popularity') }}
                            </p>
                            <p class="mt-1 text-2xl font-bold">
                                {{
                                    formatNumber(
                                        artist.metrics?.spotify_popularity,
                                    )
                                }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card class="py-0">
                        <CardContent class="p-4">
                            <p
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                {{ $t('artists.show_spotify_followers') }}
                            </p>
                            <p class="mt-1 text-2xl font-bold">
                                {{
                                    formatNumber(
                                        artist.metrics?.spotify_followers,
                                    )
                                }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card class="py-0">
                        <CardContent class="p-4">
                            <p
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                {{ $t('artists.metric_youtube_subs') }}
                            </p>
                            <p class="mt-1 text-2xl font-bold">
                                {{
                                    formatNumber(
                                        artist.metrics?.youtube_subscribers,
                                    )
                                }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card class="py-0">
                        <CardContent class="p-4">
                            <p
                                class="text-xs font-medium text-muted-foreground uppercase"
                            >
                                {{ $t('artists.show_instagram') }}
                            </p>
                            <p class="mt-1 text-2xl font-bold">
                                {{
                                    formatNumber(
                                        artist.metrics?.instagram_followers,
                                    )
                                }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Content Grid: Top Tracks & Releases -->
                <div class="grid gap-6 md:grid-cols-2">
                    <ArtistMediaList
                        :artist-id="props.id"
                        variant="top-tracks"
                    />
                    <ArtistMediaList
                        :artist-id="props.id"
                        variant="recent-releases"
                    />
                </div>

                <!-- Similar Artists -->
                <ArtistSimilarArtists :artist-id="props.id" />

                <!-- External Links -->
                <div>
                    <h3 class="mb-4 text-lg font-semibold">
                        {{ $t('artists.show_external_links') }}
                    </h3>
                    <div class="flex flex-wrap gap-3">
                        <Button
                            v-if="artist.spotify_id"
                            as-child
                            variant="outline"
                            class="gap-2 text-green-600 hover:bg-green-50 hover:text-green-700"
                        >
                            <a
                                :href="`https://open.spotify.com/artist/${artist.spotify_id}`"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Music class="h-4 w-4" />
                                Spotify
                            </a>
                        </Button>
                        <Button
                            variant="outline"
                            disabled
                            class="gap-2 text-red-600 hover:bg-red-50 hover:text-red-700"
                        >
                            <Youtube class="h-4 w-4" />
                            YouTube
                        </Button>
                        <Button
                            variant="outline"
                            disabled
                            class="gap-2 text-pink-600 hover:bg-pink-50 hover:text-pink-700"
                        >
                            <Instagram class="h-4 w-4" />
                            Instagram
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Data Tab -->
            <div v-else-if="activeTab === 'data'" class="space-y-6">
                <!-- Detailed Metrics Grid -->
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent class="space-y-2 p-4">
                            <div
                                class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                            >
                                <Users class="h-4 w-4" />
                                <span>{{
                                    $t('artists.show_spotify_followers')
                                }}</span>
                            </div>
                            <p class="text-2xl font-bold">
                                {{
                                    formatNumber(
                                        artist.metrics?.spotify_followers,
                                    )
                                }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="space-y-2 p-4">
                            <div
                                class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                            >
                                <TrendingUp class="h-4 w-4" />
                                <span>{{
                                    $t('artists.show_spotify_popularity')
                                }}</span>
                            </div>
                            <div>
                                <ScoreBadge
                                    v-if="
                                        artist.metrics && 
                                        artist.metrics.spotify_popularity !== null &&
                                        artist.metrics.spotify_popularity !== undefined
                                    "
                                    :score="artist.metrics.spotify_popularity"
                                    size="md"
                                />
                                <p v-else class="text-2xl font-bold">N/A</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="space-y-2 p-4">
                            <div
                                class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                            >
                                <Youtube class="h-4 w-4" />
                                <span>{{
                                    $t('artists.show_youtube_subscribers')
                                }}</span>
                            </div>
                            <p class="text-2xl font-bold">
                                {{
                                    formatNumber(
                                        artist.metrics?.youtube_subscribers,
                                    )
                                }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="space-y-2 p-4">
                            <div
                                class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                            >
                                <Instagram class="h-4 w-4" />
                                <span>{{ $t('artists.show_instagram') }}</span>
                            </div>
                            <p class="text-2xl font-bold">
                                {{
                                    formatNumber(
                                        artist.metrics?.instagram_followers,
                                    )
                                }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- YouTube Video Analytics Section -->
                <div v-if="artist.metrics && artist.metrics.youtube_subscribers !== null">
                    <h3 class="mb-4 text-lg font-semibold">YouTube Video Analytics</h3>
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <Card>
                            <CardContent class="space-y-2 p-4">
                                <div
                                    class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                                >
                                    <Youtube class="h-4 w-4" />
                                    <span>Average Views</span>
                                </div>
                                <p class="text-2xl font-bold">
                                    {{
                                        artist.metrics?.youtube_avg_views !== null
                                            ? formatNumber(artist.metrics.youtube_avg_views)
                                            : 'N/A'
                                    }}
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent class="space-y-2 p-4">
                                <div
                                    class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                                >
                                    <Youtube class="h-4 w-4" />
                                    <span>Average Likes</span>
                                </div>
                                <p class="text-2xl font-bold">
                                    {{
                                        artist.metrics?.youtube_avg_likes !== null
                                            ? formatNumber(artist.metrics.youtube_avg_likes)
                                            : 'N/A'
                                    }}
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent class="space-y-2 p-4">
                                <div
                                    class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                                >
                                    <Youtube class="h-4 w-4" />
                                    <span>Average Comments</span>
                                </div>
                                <p class="text-2xl font-bold">
                                    {{
                                        artist.metrics?.youtube_avg_comments !== null
                                            ? formatNumber(artist.metrics.youtube_avg_comments)
                                            : 'N/A'
                                    }}
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent class="space-y-2 p-4">
                                <div
                                    class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                                >
                                    <Youtube class="h-4 w-4" />
                                    <span>Videos Analyzed</span>
                                </div>
                                <p class="text-2xl font-bold">
                                    {{
                                        artist.metrics?.youtube_videos_analyzed !== null
                                            ? artist.metrics.youtube_videos_analyzed
                                            : 'N/A'
                                    }}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Charts Area (Placeholder for now, but using real data if possible) -->
                <div class="grid gap-6 md:grid-cols-3">
                    <Card class="md:col-span-2">
                        <CardHeader>
                            <CardTitle class="text-base">{{
                                $t('artists.show_metric_comparison')
                            }}</CardTitle>
                        </CardHeader>
                        <CardContent
                            class="flex h-64 items-center justify-center"
                        >
                            <p class="text-sm text-muted-foreground">
                                {{
                                    $t(
                                        'artists.show_metric_history_placeholder',
                                    )
                                }}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">{{
                                $t('artists.show_score_breakdown_title')
                            }}</CardTitle>
                        </CardHeader>
                        <CardContent v-if="artist.metrics" class="space-y-6">
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>{{
                                        $t('artists.show_spotify_followers')
                                    }}</span>
                                    <span class="font-medium"
                                        >{{
                                            Math.round(
                                                (Math.log10(
                                                    (artist.metrics
                                                        ?.spotify_followers ||
                                                        0) + 1,
                                                ) /
                                                    Math.log10(100000000)) *
                                                    100,
                                            )
                                        }}
                                        / 100</span
                                    >
                                </div>
                                <div
                                    class="h-2 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full bg-primary"
                                        :style="{
                                            width: `${(Math.log10((artist.metrics?.spotify_followers || 0) + 1) / Math.log10(100000000)) * 100}%`,
                                        }"
                                    ></div>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>{{
                                        $t('artists.show_spotify_popularity')
                                    }}</span>
                                    <span class="font-medium"
                                        >{{
                                            artist.metrics?.spotify_popularity || 0
                                        }}
                                        / 100</span
                                    >
                                </div>
                                <div
                                    class="h-2 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full bg-primary"
                                        :style="{
                                            width: `${artist.metrics?.spotify_popularity || 0}%`,
                                        }"
                                    ></div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div
                    v-if="artist.metrics && (artist.metrics.refreshed_at || artist.metrics.youtube_refreshed_at || artist.metrics.youtube_analytics_refreshed_at)"
                    class="space-y-4"
                >
                    <!-- Refresh timestamps -->
                    <div class="grid gap-4 md:grid-cols-3">
                        <div
                            v-if="artist.metrics.refreshed_at"
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <RefreshCw class="h-4 w-4" />
                            <div>
                                <p class="font-medium">Spotify Data</p>
                                <p>{{ new Date(artist.metrics.refreshed_at).toLocaleString() }}</p>
                            </div>
                        </div>
                        <div
                            v-if="artist.metrics.youtube_refreshed_at"
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <Youtube class="h-4 w-4" />
                            <div>
                                <p class="font-medium">YouTube Basic</p>
                                <p>{{ new Date(artist.metrics.youtube_refreshed_at).toLocaleString() }}</p>
                            </div>
                        </div>
                        <div
                            v-if="artist.metrics.youtube_analytics_refreshed_at"
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <Youtube class="h-4 w-4" />
                            <div>
                                <p class="font-medium">YouTube Analytics</p>
                                <p>{{ new Date(artist.metrics.youtube_analytics_refreshed_at).toLocaleString() }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Refresh button -->
                    <div class="flex justify-end">
                        <Button 
                            variant="outline" 
                            size="sm" 
                            class="gap-2" 
                            :disabled="isRefreshing"
                            @click="refreshArtistData"
                        >
                            <RefreshCw class="h-3 w-3" :class="{ 'animate-spin': isRefreshing }" />
                            {{ isRefreshing ? 'Refreshing...' : $t('artists.show_refresh_data_button') }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- External Links Tab -->
            <div v-else-if="activeTab === 'links'" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>External Links</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="border-t">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr class="border-b">
                                        <th
                                            class="h-12 px-6 text-left align-middle font-medium text-muted-foreground"
                                        >
                                            Platform
                                        </th>
                                        <th
                                            class="h-12 px-6 text-left align-middle font-medium text-muted-foreground"
                                        >
                                            URL
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(link, index) in artist.links"
                                        :key="index"
                                        class="border-b last:border-0 hover:bg-muted/50"
                                    >
                                        <td class="p-6 capitalize">
                                            {{
                                                link.platform.replace('_', ' ')
                                            }}
                                        </td>
                                        <td class="p-6">
                                            <a
                                                :href="link.url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="flex items-center gap-2 text-primary hover:underline"
                                            >
                                                {{ link.url }}
                                                <ExternalLink class="h-3 w-3" />
                                            </a>
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            !artist.links ||
                                            artist.links.length === 0
                                        "
                                    >
                                        <td
                                            colspan="2"
                                            class="p-6 text-center text-muted-foreground"
                                        >
                                            No external links found for this
                                            artist.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Not Found (fallback) -->
        <div v-else class="py-12 text-center">
            <p class="text-muted-foreground">
                {{ $t('artists.show_not_found') }}
            </p>
            <Button class="mt-4" @click="router.visit('/search')">
                {{ $t('artists.show_back_button') }}
            </Button>
        </div>

        <ArtistAddToLineupModal
            v-if="artist"
            v-model:open="showAddToLineupModal"
            :artist="artist"
            :lineups="props.userLineups"
            @submit="handleAddToLineupSubmit"
        />
    </MainLayout>
</template>
