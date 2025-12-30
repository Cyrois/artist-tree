<script setup lang="ts">
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { Card } from '@/components/ui/card';
import {
    useRecentSearches,
    type RecentSearchArtist,
} from '@/composables/useRecentSearches';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { History } from 'lucide-vue-next';

const { recentSearches } = useRecentSearches();

const handleArtistClick = async (artist: RecentSearchArtist) => {
    if (artist.id && artist.id > 0) {
        router.visit(`/artist/${artist.id}`);
    } else if (artist.spotify_id) {
        try {
            const response = await axios.post('/api/artists/select', {
                spotify_id: artist.spotify_id,
            });
            const newId = response.data.data.id;
            router.visit(`/artist/${newId}`);
        } catch (error) {
            console.error('Failed to select artist', error);
        }
    }
};
</script>

<template>
    <div v-if="recentSearches.length > 0" class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="flex items-center gap-2 text-lg font-semibold">
                <History class="h-5 w-5" />
                {{ $t('artists.recent_searches_title') }}
            </h3>
        </div>

        <!-- Grid Layout (Matching ArtistSimilarArtists.vue) -->
        <div
            class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4 lg:grid-cols-6 xl:grid-cols-8"
        >
            <Card
                v-for="(artist, index) in recentSearches"
                :key="artist.spotify_id"
                class="group cursor-pointer gap-0 overflow-hidden border-muted p-0 transition-all hover:shadow-md"
                :class="{
                    'hidden md:block': index >= 2 && index < 4,
                    'hidden lg:block': index >= 4 && index < 6,
                    'hidden xl:block': index >= 6 && index < 8,
                    hidden: index >= 8,
                }"
                @click="handleArtistClick(artist)"
            >
                <div class="relative aspect-square overflow-hidden bg-muted">
                    <img
                        v-if="artist.image_url"
                        :src="artist.image_url"
                        :alt="artist.name"
                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-110"
                    />
                    <div
                        v-else
                        class="absolute inset-0 flex items-center justify-center bg-muted text-2xl font-bold text-muted-foreground"
                    >
                        {{ artist.name.charAt(0) }}
                    </div>
                    <div class="absolute top-2 right-2">
                        <ScoreBadge :score="artist.score" />
                    </div>
                </div>
                <div class="p-3">
                    <p
                        class="truncate text-sm font-semibold transition-colors group-hover:text-primary"
                        :title="artist.name"
                    >
                        {{ artist.name }}
                    </p>
                    <p
                        v-if="artist.genres && artist.genres.length > 0"
                        class="mt-0.5 truncate text-[10px] text-muted-foreground"
                    >
                        {{ artist.genres[0] }}
                    </p>
                </div>
            </Card>
        </div>
    </div>
</template>
