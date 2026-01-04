<script setup lang="ts">
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { formatNumber } from '@/data/constants';
import type { Artist } from '@/data/types';
import { cn } from '@/lib/utils';
import { ChevronRight, Globe } from 'lucide-vue-next';
import { computed } from 'vue';
import ArtistAvatar from './ArtistAvatar.vue';

interface Props {
    artist: Artist;
    compact?: boolean;
    showMetrics?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    compact: false,
    showMetrics: true,
});

const emit = defineEmits<{
    click: [artist: Artist];
}>();

const displayGenres = computed(() => {
    const genres = props.artist.genres || props.artist.genre || [];
    return genres.slice(0, 2);
});

const totalGenresCount = computed(() => {
    return (props.artist.genres || props.artist.genre || []).length;
});
</script>

<template>
    <Card
        :class="
            cn(
                'group relative cursor-pointer border transition-all duration-200 hover:border-border/80 hover:shadow-md',
                compact ? 'p-3' : 'p-4',
            )
        "
        data-slot="artist-card"
        @click="emit('click', artist)"
    >
        <div class="relative flex items-center gap-4">
            <!-- Avatar with score overlay -->
            <div class="relative flex-shrink-0">
                <ArtistAvatar :artist="artist" :size="compact ? 'sm' : 'md'" />
                <div class="absolute -right-1 -bottom-1">
                    <ScoreBadge :score="artist.score" />
                </div>
            </div>

            <!-- Info -->
            <div class="min-w-0 flex-1">
                <h3 class="truncate font-semibold text-foreground">
                    {{ artist.name }}
                </h3>
                <div class="mt-1 flex flex-wrap gap-1">
                    <Badge
                        v-for="genre in displayGenres"
                        :key="genre"
                        variant="secondary"
                        class="px-2 py-0 text-xs"
                    >
                        {{ genre }}
                    </Badge>
                    <span
                        v-if="totalGenresCount > 2"
                        class="text-xs text-muted-foreground"
                    >
                        +{{ totalGenresCount - 2 }}
                    </span>
                </div>

                <!-- Country & Secondary Info -->
                <div
                    v-if="!compact && artist.country"
                    class="mt-1.5 flex items-center gap-3 text-xs text-muted-foreground"
                >
                    <span class="flex items-center gap-1">
                        <Globe class="h-3 w-3" />
                        {{ artist.country }}
                    </span>
                </div>

                <!-- Metrics -->
                <div
                    v-if="showMetrics && !compact"
                    class="mt-2 flex items-center gap-4 text-xs text-muted-foreground"
                >
                    <span class="flex items-center gap-1">
                        <svg
                            class="h-3 w-3 text-[hsl(var(--spotify))]"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"
                            />
                        </svg>
                        {{ formatNumber(artist.spotifyListeners) }}
                    </span>
                    <span
                        v-if="artist.youtubeSubscribers"
                        class="flex items-center gap-1"
                    >
                        <svg
                            class="h-3 w-3 text-[hsl(var(--youtube))]"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"
                            />
                        </svg>
                        {{ formatNumber(artist.youtubeSubscribers) }}
                    </span>
                </div>
            </div>

            <!-- Hover arrow -->
            <ChevronRight
                class="h-5 w-5 flex-shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
            />
        </div>
    </Card>
</template>
