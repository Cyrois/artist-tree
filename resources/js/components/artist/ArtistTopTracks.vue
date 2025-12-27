<script setup lang="ts">
import { onMounted } from 'vue';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Loader2, Music, AlertCircle, ExternalLink, Play, Square } from 'lucide-vue-next';
import { useAsyncSpotifyData } from '@/composables/useAsyncSpotifyData';
import { useSpotifyPlayback } from '@/composables/useSpotifyPlayback';
import { trans } from 'laravel-vue-i18n';

interface Track {
    spotify_id: string;
    name: string;
    album_name: string;
    album_image_url: string;
    duration_ms: number;
    preview_url: string;
    external_url: string;
    artists: Array<{ name: string; spotify_id: string }>;
}

interface Props {
    artistId: number;
}

const props = defineProps<Props>();

const { data: tracks, loading, error, load } = useAsyncSpotifyData<Track[]>(
    `/api/artists/${props.artistId}/top-tracks`
);
// Note: meta is available but not used for top tracks (always shows 5)

const {
    isReady,
    isPlaying,
    currentTrackId,
    isLoading: isPlaybackLoading,
    error: playbackError,
    formattedPosition,
    formattedDuration,
    progressPercentage,
    playTrack,
    togglePlayPause,
    stop,
    isTrackPlaying,
} = useSpotifyPlayback();

onMounted(() => {
    load();
});

const formatDuration = (ms: number): string => {
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
};

const handlePlayClick = async (track: Track) => {
    if (isPlaybackLoading.value) return;
    
    if (isTrackPlaying(track.spotify_id)) {
        // If this track is playing, stop playback
        await stop();
    } else {
        // Play this track (will stop any currently playing track)
        await playTrack(track.spotify_id);
    }
};

const isCurrentTrack = (trackId: string) => {
    return currentTrackId.value === trackId;
};

const showProgress = (trackId: string) => {
    return isCurrentTrack(trackId) && isPlaying.value;
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Music class="w-5 h-5" />
                {{ trans('artists.show_top_tracks_title') }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <div class="flex flex-col items-center gap-3">
                    <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">{{ trans('artists.show_top_tracks_loading') }}</p>
                </div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="flex items-center justify-center py-12">
                <div class="flex flex-col items-center gap-3 text-center">
                    <AlertCircle class="h-8 w-8 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">{{ trans('artists.show_top_tracks_error') }}</p>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="!tracks || tracks.length === 0" class="flex items-center justify-center py-12">
                <p class="text-sm text-muted-foreground">{{ trans('artists.show_top_tracks_empty') }}</p>
            </div>

            <!-- Playback Error State -->
            <div v-if="playbackError && tracks && tracks.length > 0" class="mb-4 p-3 rounded-md bg-destructive/10 border border-destructive/20">
                <div class="flex items-center gap-2 text-sm text-destructive">
                    <AlertCircle class="w-4 h-4" />
                    <p>{{ playbackError }}</p>
                </div>
            </div>

            <!-- Tracks List -->
            <div v-if="tracks && tracks.length > 0" class="space-y-0">
                <div
                    v-for="(track, index) in tracks"
                    :key="track.spotify_id"
                    class="flex items-center gap-3 px-6 py-3 hover:bg-muted/50 transition-colors group -mx-6"
                >
                    <!-- Track Number -->
                    <div class="flex-shrink-0 w-6 text-center text-sm font-medium text-muted-foreground">
                        {{ index + 1 }}
                    </div>

                    <!-- Album Art -->
                    <img
                        v-if="track.album_image_url"
                        :src="track.album_image_url"
                        :alt="track.album_name"
                        class="w-12 h-12 rounded object-cover"
                    />
                    <div v-else class="w-12 h-12 rounded bg-muted flex items-center justify-center">
                        <Music class="w-6 h-6 text-muted-foreground" />
                    </div>

                    <!-- Track Info -->
                    <div class="flex-1 min-w-0">
                        <p class="font-medium truncate">{{ track.name }}</p>
                        <p class="text-sm text-muted-foreground truncate">{{ track.album_name }}</p>
                    </div>

                    <!-- Duration -->
                    <div class="flex-shrink-0 text-sm text-muted-foreground min-w-[80px] text-right tabular-nums">
                        <span v-if="showProgress(track.spotify_id)">{{ formattedPosition }} / {{ formattedDuration }}</span>
                        <span v-else>{{ formatDuration(track.duration_ms) }}</span>
                    </div>

                    <!-- Actions -->
                    <div class="flex-shrink-0 flex items-center gap-2">
                        <!-- Play/Stop Button -->
                        <button
                            @click="handlePlayClick(track)"
                            :disabled="isPlaybackLoading"
                            class="relative p-2 rounded-md hover:bg-muted transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            :title="isTrackPlaying(track.spotify_id) ? 'Stop' : 'Play'"
                        >
                            <!-- Loading/Progress Indicator -->
                            <div
                                v-if="showProgress(track.spotify_id)"
                                class="absolute inset-0 rounded-md overflow-hidden"
                            >
                                <div
                                    class="h-full bg-primary/20 transition-all duration-300"
                                    :style="{ width: `${progressPercentage}%` }"
                                />
                            </div>
                            <!-- Play/Stop Icon -->
                            <div class="relative flex items-center justify-center">
                                <Loader2
                                    v-if="isCurrentTrack(track.spotify_id) && isPlaybackLoading"
                                    class="w-4 h-4 animate-spin"
                                />
                                <Square
                                    v-else-if="isTrackPlaying(track.spotify_id)"
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
                            :href="track.external_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="p-2 rounded-md hover:bg-muted transition-colors"
                            title="Open in Spotify"
                        >
                            <ExternalLink class="w-4 h-4" />
                        </a>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
