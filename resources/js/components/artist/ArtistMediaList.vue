<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useAsyncSpotifyData } from '@/composables/useAsyncSpotifyData';
import { useSpotifyPlayback } from '@/composables/useSpotifyPlayback';
import { trans } from 'laravel-vue-i18n';
import {
    AlertCircle,
    Disc,
    ExternalLink,
    Loader2,
    Music,
    Play,
    Square,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

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

const {
    data: items,
    loading,
    error,
    load,
} = useAsyncSpotifyData<MediaItem[]>(config.value.apiUrl);

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
    initializePlayer,
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
    if (!date) return trans('common.status_unknown');
    const parts = date.split('-');
    if (parts.length === 1) return parts[0]; // Year only
    if (parts.length === 2) return `${parts[1]}/${parts[0]}`; // Month/Year
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
    });
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
    <Card class="w-full overflow-hidden">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <component :is="config.icon" class="h-5 w-5" />
                {{ config.title }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <div class="flex flex-col items-center gap-3">
                    <Loader2
                        class="h-6 w-6 animate-spin text-muted-foreground"
                    />
                    <p class="text-sm text-muted-foreground">
                        {{ config.loadingMessage }}
                    </p>
                </div>
            </div>

            <!-- Error State -->
            <div
                v-else-if="error"
                class="flex items-center justify-center py-12"
            >
                <div class="flex flex-col items-center gap-3 text-center">
                    <AlertCircle class="h-8 w-8 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">
                        {{ config.errorMessage }}
                    </p>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else-if="!items || items.length === 0"
                class="flex items-center justify-center py-12"
            >
                <p class="text-sm text-muted-foreground">
                    {{ config.emptyMessage }}
                </p>
            </div>

            <!-- Playback Error State -->
            <div
                v-if="playbackError && items && items.length > 0"
                class="mb-4 rounded-md border border-destructive/20 bg-destructive/10 p-3"
            >
                <div class="flex items-center gap-2 text-sm text-destructive">
                    <AlertCircle class="h-4 w-4" />
                    <p>{{ playbackError }}</p>
                </div>
            </div>

            <!-- Items List -->
            <div v-if="items && items.length > 0" class="space-y-0">
                <div
                    v-for="(item, index) in items"
                    :key="item.spotify_id"
                    class="group -mx-6 transition-all duration-200"
                    :class="{
                        'relative z-10 bg-muted/40 shadow-sm':
                            expandedMobileId === item.spotify_id,
                    }"
                >
                    <!-- Top Divider for Expanded/Active items -->
                    <div
                        v-if="
                            expandedMobileId === item.spotify_id ||
                            (index > 0 &&
                                items[index - 1].spotify_id ===
                                    expandedMobileId)
                        "
                        class="mx-6 h-px bg-border/60"
                    />
                    <div v-else-if="index > 0" class="mx-6 h-px bg-border/5" />

                    <!-- Main Row -->
                    <div
                        class="flex cursor-pointer items-center gap-3 px-6 py-3 transition-colors hover:bg-muted/50 lg:cursor-default"
                        @click="toggleMobileItem(item.spotify_id)"
                    >
                        <!-- Number -->
                        <div
                            class="w-6 flex-shrink-0 text-center text-sm font-medium text-muted-foreground"
                        >
                            {{ index + 1 }}
                        </div>

                        <!-- Image -->
                        <img
                            v-if="
                                variant === 'top-tracks'
                                    ? item.album_image_url
                                    : item.image_url
                            "
                            :src="
                                variant === 'top-tracks'
                                    ? item.album_image_url
                                    : item.image_url
                            "
                            :alt="item.name"
                            class="hidden h-12 w-12 rounded object-cover lg:block"
                        />
                        <div
                            v-else
                            class="hidden h-12 w-12 items-center justify-center rounded bg-muted lg:flex"
                        >
                            <component
                                :is="config.icon"
                                class="h-6 w-6 text-muted-foreground"
                            />
                        </div>

                        <!-- Info -->
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-medium lg:text-base"
                            >
                                {{ item.name }}
                            </p>

                            <!-- Top Tracks Info -->
                            <p
                                v-if="variant === 'top-tracks'"
                                class="truncate text-xs text-muted-foreground lg:text-sm"
                            >
                                {{ item.album_name }}
                            </p>
                            <!-- Recent Releases Info -->
                            <p
                                v-else-if="item.album_type && item.release_date"
                                class="truncate text-xs text-muted-foreground lg:text-sm"
                            >
                                {{
                                    item.album_type.charAt(0).toUpperCase() +
                                    item.album_type.slice(1)
                                }}
                                • {{ formatReleaseDate(item.release_date) }}
                            </p>
                        </div>

                        <!-- Desktop Duration & Actions Panel (Fixed Width only when visible) -->
                        <div
                            class="ml-4 hidden flex-shrink-0 items-center gap-4 lg:flex"
                            v-if="isItemActive(item)"
                        >
                            <div
                                class="text-sm text-muted-foreground tabular-nums"
                            >
                                {{ formattedPosition }} /
                                {{ formattedDuration }}
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    @click.stop="handlePlayClick(item)"
                                    :disabled="isPlaybackLoading"
                                    class="rounded-md p-2 text-primary transition-colors hover:bg-muted"
                                >
                                    <Loader2
                                        v-if="isPlaybackLoading"
                                        class="h-4 w-4 animate-spin"
                                    />
                                    <Square
                                        v-else
                                        class="h-4 w-4 fill-current"
                                    />
                                </button>
                                <a
                                    :href="item.external_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="rounded-md p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    @click.stop
                                >
                                    <ExternalLink class="h-4 w-4" />
                                </a>
                            </div>
                        </div>

                        <!-- Hover-only Desktop Actions -->
                        <div
                            class="ml-4 hidden flex-shrink-0 items-center gap-4 lg:group-hover:flex"
                            v-else
                        >
                            <div
                                class="text-sm text-muted-foreground tabular-nums"
                            >
                                {{ formatDuration(item.duration_ms) }}
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    @click.stop="handlePlayClick(item)"
                                    :disabled="isPlaybackLoading"
                                    class="rounded-md p-2 transition-colors hover:bg-muted"
                                >
                                    <Play class="h-4 w-4" />
                                </button>
                                <a
                                    :href="item.external_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="rounded-md p-2 transition-colors hover:bg-muted"
                                    @click.stop
                                >
                                    <ExternalLink class="h-4 w-4" />
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile/Tablet Actions Row -->
                    <div
                        v-if="expandedMobileId === item.spotify_id"
                        class="flex items-center justify-between border-b border-border/50 px-6 pb-4 lg:hidden"
                    >
                        <div class="text-xs text-muted-foreground tabular-nums">
                            <span v-if="showProgress(item)"
                                >{{ formattedPosition }} /
                                {{ formattedDuration }}</span
                            >
                            <span v-else>{{
                                formatDuration(item.duration_ms)
                            }}</span>
                        </div>

                        <div class="flex items-center gap-4">
                            <!-- Play/Stop Button -->
                            <button
                                @click.stop="handlePlayClick(item)"
                                :disabled="isPlaybackLoading"
                                class="flex items-center gap-2 text-sm font-medium transition-colors hover:text-primary disabled:opacity-50"
                            >
                                <div
                                    class="relative flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary"
                                >
                                    <Loader2
                                        v-if="
                                            isItemActive(item) &&
                                            isPlaybackLoading
                                        "
                                        class="h-4 w-4 animate-spin"
                                    />
                                    <Square
                                        v-else-if="isItemActive(item)"
                                        class="h-3.5 w-3.5 fill-current"
                                    />
                                    <Play v-else class="ml-0.5 h-3.5 w-3.5" />
                                </div>
                            </button>

                            <!-- External Link -->
                            <a
                                :href="item.external_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="p-2 text-muted-foreground transition-colors hover:text-foreground"
                                @click.stop
                            >
                                <ExternalLink class="h-4 w-4" />
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
                <DialogTitle>{{
                    trans('artists.spotify_auth_required_title')
                }}</DialogTitle>
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
