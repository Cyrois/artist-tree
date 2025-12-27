<script setup lang="ts">
import { onMounted, ref, computed } from 'vue';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Loader2, Music, Disc, AlertCircle, ExternalLink, Play, Square } from 'lucide-vue-next';
import { useAsyncSpotifyData } from '@/composables/useAsyncSpotifyData';
import { useSpotifyPlayback } from '@/composables/useSpotifyPlayback';
import { trans } from 'laravel-vue-i18n';

interface MediaItem {
    spotify_id: string;
    name: string;
    // Track fields
    album_name?: string;
    album_image_url?: string;
    duration_ms: number;
    preview_url?: string;
    external_url: string;
    artists?: Array<{ name: string; spotify_id: string }>;
    // Album/Release fields
    album_type?: 'album' | 'single' | 'compilation';
    release_date?: string;
    total_tracks?: number;
    image_url?: string;
}

interface Props {
    artistId: number;
    variant: 'top-tracks' | 'recent-releases';
}

const props = defineProps<Props>();

// Computed configurations based on variant
const config = computed(() => {
    if (props.variant === 'top-tracks') {
        return {
            title: trans('artists.show_top_tracks_title'),
            icon: Music,
            apiUrl: `/api/artists/${props.artistId}/top-tracks`,
            apiParams: {}, // Top tracks endpoint handles default limit
            emptyMessage: trans('artists.show_top_tracks_empty'),
            loadingMessage: trans('artists.show_top_tracks_loading'),
            errorMessage: trans('artists.show_top_tracks_error'),
        };
    } else {
        return {
            title: trans('artists.show_recent_releases_title'),
            icon: Disc,
            apiUrl: `/api/artists/${props.artistId}/albums`,
            apiParams: { limit: 5, type: 'single' },
            emptyMessage: trans('artists.show_recent_releases_empty'),
            loadingMessage: trans('artists.show_recent_releases_loading'),
            errorMessage: trans('artists.show_recent_releases_error'),
        };
    }
});

const { data: items, loading, error, load } = useAsyncSpotifyData<MediaItem[]>(
    config.value.apiUrl
);

const {
    isLoading: isPlaybackLoading,
    error: playbackError,
    playTrack,
    stop,
    isTrackPlaying,
    isContextPlaying,
    checkAuthentication,
    formattedPosition,
    formattedDuration,
    isPlaying,
    progressPercentage,
    initializePlayer
} = useSpotifyPlayback();

const isAuthDialogOpen = ref(false);
const spotifyAuthUrl = ref<string | null>(null);
const pendingItem = ref<MediaItem | null>(null);
const expandedMobileId = ref<string | null>(null);

onMounted(() => {
    load(config.value.apiParams);
    initializePlayer();
});

const toggleMobileItem = (id: string) => {
    expandedMobileId.value = expandedMobileId.value === id ? null : id;
};

const formatDuration = (ms: number): string => {
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
};

const formatReleaseDate = (date: string): string => {
    if (!date) return 'Unknown';
    const parts = date.split('-');
    if (parts.length === 1) return parts[0]; // Year only
    if (parts.length === 2) return `${parts[1]}/${parts[0]}`; // Month/Year
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
};

const isItemActive = (item: MediaItem) => {
    if (props.variant === 'top-tracks') {
        return isTrackPlaying(item.spotify_id);
    } else {
        return isContextPlaying(`spotify:album:${item.spotify_id}`);
    }
};

const handlePlayClick = async (item: MediaItem) => {
    if (isPlaybackLoading.value) return;
    
    const isActive = isItemActive(item);
    
    if (isActive) {
        await stop();
    } else {
        const { authenticated, authUrl } = await checkAuthentication();
        
        if (!authenticated && authUrl) {
            pendingItem.value = item;
            spotifyAuthUrl.value = authUrl;
            isAuthDialogOpen.value = true;
            return;
        }

        if (props.variant === 'top-tracks') {
            await playTrack(item.spotify_id, 'track');
        } else {
            await playTrack(item.spotify_id, 'album');
        }
    }
};

const confirmAuth = () => {
    if (spotifyAuthUrl.value) {
        window.location.href = spotifyAuthUrl.value;
    }
};

const showProgress = (item: MediaItem) => {
    return isItemActive(item) && isPlaying.value;
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <component :is="config.icon" class="w-5 h-5" />
                {{ config.title }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <div class="flex flex-col items-center gap-3">
                    <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">{{ config.loadingMessage }}</p>
                </div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="flex items-center justify-center py-12">
                <div class="flex flex-col items-center gap-3 text-center">
                    <AlertCircle class="h-8 w-8 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">{{ config.errorMessage }}</p>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="!items || items.length === 0" class="flex items-center justify-center py-12">
                <p class="text-sm text-muted-foreground">{{ config.emptyMessage }}</p>
            </div>

            <!-- Playback Error State -->
            <div v-if="playbackError && items && items.length > 0" class="mb-4 p-3 rounded-md bg-destructive/10 border border-destructive/20">
                <div class="flex items-center gap-2 text-sm text-destructive">
                    <AlertCircle class="w-4 h-4" />
                    <p>{{ playbackError }}</p>
                </div>
            </div>

            <!-- Items List -->
            <div v-if="items && items.length > 0" class="space-y-0">
                <div
                    v-for="(item, index) in items"
                    :key="item.spotify_id"
                    class="group -mx-6"
                >
                    <!-- Main Row -->
                    <div 
                        class="flex items-center gap-3 px-6 py-3 hover:bg-muted/50 transition-colors cursor-pointer sm:cursor-default"
                        @click="toggleMobileItem(item.spotify_id)"
                    >
                        <!-- Number -->
                        <div class="flex-shrink-0 w-6 text-center text-sm font-medium text-muted-foreground">
                            {{ index + 1 }}
                        </div>

                        <!-- Image -->
                        <img
                            v-if="variant === 'top-tracks' ? item.album_image_url : item.image_url"
                            :src="variant === 'top-tracks' ? item.album_image_url : item.image_url"
                            :alt="item.name"
                            class="hidden sm:block w-12 h-12 rounded object-cover"
                        />
                        <div v-else class="hidden sm:flex w-12 h-12 rounded bg-muted items-center justify-center">
                            <component :is="config.icon" class="w-6 h-6 text-muted-foreground" />
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate text-sm sm:text-base">{{ item.name }}</p>
                            
                            <!-- Top Tracks Info -->
                            <p v-if="variant === 'top-tracks'" class="text-xs sm:text-sm text-muted-foreground truncate">
                                {{ item.album_name }}
                            </p>
                            <!-- Recent Releases Info -->
                            <p v-else-if="item.album_type && item.release_date" class="text-xs sm:text-sm text-muted-foreground truncate">
                                {{ item.album_type.charAt(0).toUpperCase() + item.album_type.slice(1) }} • {{ formatReleaseDate(item.release_date) }}
                            </p>
                        </div>

                        <!-- Duration (Desktop) -->
                        <div class="hidden sm:block flex-shrink-0 text-sm text-muted-foreground min-w-[80px] text-right tabular-nums transition-opacity" :class="{ 'opacity-0 group-hover:opacity-100': !isItemActive(item) }">
                            <span v-if="showProgress(item)">{{ formattedPosition }} / {{ formattedDuration }}</span>
                            <span v-else>{{ formatDuration(item.duration_ms) }}</span>
                        </div>

                        <!-- Actions (Desktop) -->
                        <div class="hidden sm:flex flex-shrink-0 items-center gap-2 transition-opacity" :class="{ 'opacity-0 group-hover:opacity-100': !isItemActive(item) }">
                            <!-- Play/Stop Button -->
                            <button
                                @click.stop="handlePlayClick(item)"
                                :disabled="isPlaybackLoading"
                                class="relative p-2 rounded-md hover:bg-muted transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                :title="isItemActive(item) ? 'Stop' : 'Play'"
                            >
                                 <!-- Loading/Progress Indicator -->
                                <div
                                    v-if="showProgress(item)"
                                    class="absolute inset-0 rounded-md overflow-hidden"
                                >
                                    <div
                                        class="h-full bg-primary/20 transition-all duration-300"
                                        :style="{ width: `${progressPercentage}%` }"
                                    />
                                </div>

                                <div class="relative flex items-center justify-center">
                                    <Loader2
                                        v-if="isItemActive(item) && isPlaybackLoading"
                                        class="w-4 h-4 animate-spin"
                                    />
                                    <Square
                                        v-else-if="isItemActive(item)"
                                        class="w-4 h-4 fill-current"
                                    />
                                    <Play
                                        v-else
                                        class="w-4 h-4"
                                    />
                                </div>
                            </button>
                            <!-- External Link -->
                            <a
                                :href="item.external_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="p-2 rounded-md hover:bg-muted transition-colors"
                                title="Open in Spotify"
                                @click.stop
                            >
                                <ExternalLink class="w-4 h-4" />
                            </a>
                        </div>
                    </div>

                    <!-- Mobile Actions Row -->
                    <div 
                        v-if="expandedMobileId === item.spotify_id" 
                        class="sm:hidden px-6 pb-3 flex items-center justify-between bg-muted/20 border-b border-border/50"
                    >
                         <div class="text-xs text-muted-foreground tabular-nums">
                            <span v-if="showProgress(item)">{{ formattedPosition }} / {{ formattedDuration }}</span>
                            <span v-else>{{ formatDuration(item.duration_ms) }}</span>
                        </div>

                        <div class="flex items-center gap-4">
                             <!-- Play/Stop Button -->
                            <button
                                @click.stop="handlePlayClick(item)"
                                :disabled="isPlaybackLoading"
                                class="flex items-center gap-2 text-sm font-medium hover:text-primary transition-colors disabled:opacity-50"
                            >
                                <div class="relative flex items-center justify-center w-8 h-8 rounded-full bg-primary/10 text-primary">
                                    <Loader2
                                        v-if="isItemActive(item) && isPlaybackLoading"
                                        class="w-4 h-4 animate-spin"
                                    />
                                    <Square
                                        v-else-if="isItemActive(item)"
                                        class="w-3.5 h-3.5 fill-current"
                                    />
                                    <Play
                                        v-else
                                        class="w-3.5 h-3.5 ml-0.5"
                                    />
                                </div>
                                {{ isItemActive(item) ? trans('common.action_stop') : trans('common.action_play') }}
                            </button>

                             <!-- External Link -->
                            <a
                                :href="item.external_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="p-2 text-muted-foreground hover:text-foreground transition-colors"
                                @click.stop
                            >
                                <ExternalLink class="w-4 h-4" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Spotify Auth Dialog -->
    <Dialog v-model:open="isAuthDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('artists.spotify_auth_required_title') }}</DialogTitle>
                <DialogDescription>
                    {{ trans('artists.spotify_auth_required_description') }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="isAuthDialogOpen = false">
                    {{ trans('common.action_cancel') }}
                </Button>
                <Button @click="confirmAuth">
                    {{ trans('artists.spotify_auth_confirm_button') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
