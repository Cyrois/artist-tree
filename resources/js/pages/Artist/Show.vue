<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ArrowLeft, Loader2, AlertCircle } from 'lucide-vue-next';
import { ref, onMounted, computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { show as artistShowRoute } from '@/routes/api/artists';
import ArtistTopTracks from '@/components/artist/ArtistTopTracks.vue';
import ArtistAlbums from '@/components/artist/ArtistAlbums.vue';

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
                <p class="text-muted-foreground">Loading artist...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex items-center justify-center py-24">
            <div class="flex flex-col items-center gap-4 text-center">
                <AlertCircle class="h-12 w-12 text-destructive" />
                <div>
                    <p class="font-medium text-destructive">{{ error }}</p>
                    <p class="text-sm text-muted-foreground mt-1">Please try again later</p>
                </div>
                <Button variant="outline" @click="router.visit('/search')">
                    <ArrowLeft class="w-4 h-4 mr-2" />
                    Back to Search
                </Button>
            </div>
        </div>

        <!-- Artist Content -->
        <div v-else-if="artist" class="space-y-8">
            <!-- Back button -->
            <Button variant="ghost" size="sm" @click="router.visit('/search')">
                <ArrowLeft class="w-4 h-4 mr-2" />
                {{ $t('artists.show_back_button') }}
            </Button>

            <!-- Artist Header - Simple: just name and image -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex flex-col items-center gap-6 md:flex-row md:items-start">
                        <!-- Artist Image -->
                        <div class="shrink-0">
                            <img
                                v-if="artist.image_url"
                                :src="artist.image_url"
                                :alt="artist.name"
                                class="h-48 w-48 rounded-lg object-cover shadow-lg"
                            />
                            <div
                                v-else
                                class="flex h-48 w-48 items-center justify-center rounded-lg bg-muted"
                            >
                                <span class="text-4xl font-bold text-muted-foreground">
                                    {{ artist.name.charAt(0).toUpperCase() }}
                                </span>
                            </div>
                        </div>

                        <!-- Artist Name -->
                        <div class="flex-1 text-center md:text-left">
                            <h1 class="text-3xl font-bold">{{ artist.name }}</h1>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Async Spotify Features -->
            <div class="space-y-6">
                <ArtistTopTracks :artist-id="props.id" />
                <ArtistAlbums :artist-id="props.id" />
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
