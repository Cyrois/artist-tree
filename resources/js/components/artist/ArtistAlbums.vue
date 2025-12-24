<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Loader2, Disc, AlertCircle, ExternalLink, ChevronDown, ChevronUp } from 'lucide-vue-next';
import { useAsyncSpotifyData } from '@/composables/useAsyncSpotifyData';
import { trans } from 'laravel-vue-i18n';

interface Album {
    spotify_id: string;
    name: string;
    album_type: 'album' | 'single' | 'compilation';
    release_date: string;
    total_tracks: number;
    image_url: string;
    external_url: string;
}

interface Props {
    artistId: number;
}

const props = defineProps<Props>();

const isExpanded = ref(false);
const isLoadingMore = ref(false);

const { data: albums, loading, error, meta, load } = useAsyncSpotifyData<Album[]>(
    `/api/artists/${props.artistId}/albums`
);

onMounted(() => {
    load({ limit: 5 });
});

const handleViewAll = async () => {
    isLoadingMore.value = true;
    await load({ limit: 20 });
    isExpanded.value = true;
    isLoadingMore.value = false;
};

const handleShowLess = async () => {
    isLoadingMore.value = true;
    await load({ limit: 5 });
    isExpanded.value = false;
    isLoadingMore.value = false;
};

const formatReleaseDate = (date: string): string => {
    if (!date) return 'Unknown';
    const parts = date.split('-');
    if (parts.length === 1) return parts[0]; // Year only
    if (parts.length === 2) return `${parts[1]}/${parts[0]}`; // Month/Year
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
};

const getAlbumTypeLabel = (type: string): string => {
    return type.charAt(0).toUpperCase() + type.slice(1);
};
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="flex items-center gap-2">
                <Disc class="w-5 h-5" />
                {{ trans('artists.show_albums_singles_title') }}
            </CardTitle>
            <Button
                v-if="meta?.has_more && !isExpanded && !loading"
                variant="ghost"
                size="sm"
                :disabled="isLoadingMore"
                @click="handleViewAll"
            >
                <Loader2 v-if="isLoadingMore" class="h-4 w-4 animate-spin mr-1" />
                <ChevronDown v-else class="h-4 w-4 mr-1" />
                View All
            </Button>
            <Button
                v-else-if="isExpanded && !loading"
                variant="ghost"
                size="sm"
                :disabled="isLoadingMore"
                @click="handleShowLess"
            >
                <Loader2 v-if="isLoadingMore" class="h-4 w-4 animate-spin mr-1" />
                <ChevronUp v-else class="h-4 w-4 mr-1" />
                Show Less
            </Button>
        </CardHeader>
        <CardContent>
            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <div class="flex flex-col items-center gap-3">
                    <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">Loading albums...</p>
                </div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="flex items-center justify-center py-12">
                <div class="flex flex-col items-center gap-3 text-center">
                    <AlertCircle class="h-8 w-8 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">Unable to load albums</p>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="!albums || albums.length === 0" class="flex items-center justify-center py-12">
                <p class="text-sm text-muted-foreground">No albums available</p>
            </div>

            <!-- Albums Grid -->
            <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <a
                    v-for="album in albums"
                    :key="album.spotify_id"
                    :href="album.external_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="group"
                >
                    <div class="space-y-2">
                        <!-- Album Cover -->
                        <div class="relative aspect-square rounded-lg overflow-hidden bg-muted">
                            <img
                                v-if="album.image_url"
                                :src="album.image_url"
                                :alt="album.name"
                                class="w-full h-full object-cover transition-transform group-hover:scale-105"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <Disc class="w-12 h-12 text-muted-foreground" />
                            </div>

                            <!-- Hover Overlay -->
                            <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <ExternalLink class="w-6 h-6 text-white" />
                            </div>
                        </div>

                        <!-- Album Info -->
                        <div class="space-y-1">
                            <p class="font-medium text-sm line-clamp-2 group-hover:underline">
                                {{ album.name }}
                            </p>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <span>{{ formatReleaseDate(album.release_date) }}</span>
                                <span>•</span>
                                <span>{{ getAlbumTypeLabel(album.album_type) }}</span>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </CardContent>
    </Card>
</template>
