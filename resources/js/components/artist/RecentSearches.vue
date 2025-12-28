<script setup lang="ts">
import { Card } from '@/components/ui/card';
import { History } from 'lucide-vue-next';
import { useRecentSearches, type RecentSearchArtist } from '@/composables/useRecentSearches';
import { router } from '@inertiajs/vue3';

const { recentSearches } = useRecentSearches();

const handleArtistClick = (artist: RecentSearchArtist) => {
    if (artist.id && artist.id > 0) {
        router.visit(`/artist/${artist.id}`);
    } else if (artist.spotify_id) {
        router.visit(`/artist?spotify_id=${artist.spotify_id}`);
    }
};

</script>

<template>
    <div v-if="recentSearches.length > 0" class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-lg flex items-center gap-2">
                <History class="w-5 h-5" />
                {{ $t('artists.recent_searches_title') }}
            </h3>
        </div>

        <!-- Grid Layout (Matching ArtistSimilarArtists.vue) -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-3 md:gap-4">
            <Card 
                v-for="(artist, index) in recentSearches" 
                :key="artist.spotify_id" 
                class="overflow-hidden hover:shadow-md transition-all cursor-pointer group border-muted p-0 gap-0"
                :class="{
                    'hidden md:block': index >= 2 && index < 4,
                    'hidden lg:block': index >= 4 && index < 6,
                    'hidden xl:block': index >= 6 && index < 8,
                    'hidden': index >= 8
                }"
                @click="handleArtistClick(artist)"
            >
                <div class="aspect-square bg-muted relative overflow-hidden">
                    <img 
                        v-if="artist.image_url"
                        :src="artist.image_url" 
                        :alt="artist.name"
                        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
                    />
                    <div v-else class="absolute inset-0 flex items-center justify-center text-muted-foreground font-bold text-2xl bg-muted">
                        {{ artist.name.charAt(0) }}
                    </div>
                    <div class="absolute top-2 right-2 bg-black/70 text-white text-[10px] px-1.5 py-0.5 rounded font-medium backdrop-blur-sm">
                        {{ artist.spotify_popularity }}
                    </div>
                </div>
                <div class="p-3">
                    <p class="font-semibold text-sm truncate group-hover:text-primary transition-colors" :title="artist.name">
                        {{ artist.name }}
                    </p>
                    <p v-if="artist.genres && artist.genres.length > 0" class="text-[10px] text-muted-foreground truncate mt-0.5">
                        {{ artist.genres[0] }}
                    </p>
                </div>
            </Card>
        </div>
    </div>
</template>
