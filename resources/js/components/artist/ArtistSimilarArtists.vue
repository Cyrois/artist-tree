<script setup lang="ts">
import { onMounted } from 'vue';
import { Card } from '@/components/ui/card';
import { Loader2, AlertCircle, Users } from 'lucide-vue-next';
import { useAsyncSpotifyData } from '@/composables/useAsyncSpotifyData';
import ScoreBadge from '@/components/score/ScoreBadge.vue';

interface SimilarArtist {
    spotify_id: string;
    name: string;
    genres: string[];
    image_url: string | null;
    spotify_popularity: number;
    spotify_followers: number;
}

interface Props {
    artistId: number;
}

const props = defineProps<Props>();

const { data: artists, loading, error, load } = useAsyncSpotifyData<SimilarArtist[]>(
    `/api/artists/${props.artistId}/similar`
);

onMounted(() => {
    load({ limit: 8 });
});
</script>

<template>
    <div class="space-y-4">
        <h3 class="font-semibold text-lg flex items-center gap-2">
            <Users class="w-5 h-5" />
            {{ $t('artists.show_similar_artists_title') }}
        </h3>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <div class="flex flex-col items-center gap-3">
                <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                <p class="text-sm text-muted-foreground">{{ $t('artists.similar_finding') }}</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex items-center justify-center py-12">
            <div class="flex flex-col items-center gap-3 text-center">
                <AlertCircle class="h-8 w-8 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">{{ error }}</p>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!artists || artists.length === 0" class="bg-muted/30 rounded-lg py-12 flex flex-col items-center justify-center border border-dashed">
            <Users class="h-8 w-8 text-muted-foreground mb-2" />
            <p class="text-sm text-muted-foreground">{{ $t('artists.similar_none_found') }}</p>
        </div>

        <!-- Grid Layout -->
        <div v-else class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-3 md:gap-4">
            <Card 
                v-for="(similar, index) in artists" 
                :key="similar.spotify_id" 
                class="overflow-hidden hover:shadow-md transition-all cursor-pointer group border-muted p-0 gap-0"
                :class="{
                    'hidden md:block': index >= 2 && index < 4,
                    'hidden lg:block': index >= 4 && index < 6,
                    'hidden xl:block': index >= 6 && index < 8,
                    'hidden': index >= 8
                }"
            >
                <div class="aspect-square bg-muted relative overflow-hidden">
                    <img 
                        v-if="similar.image_url"
                        :src="similar.image_url" 
                        :alt="similar.name"
                        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
                    />
                    <div v-else class="absolute inset-0 flex items-center justify-center text-muted-foreground font-bold text-2xl bg-muted">
                        {{ similar.name.charAt(0) }}
                    </div>
                    <div class="absolute top-2 right-2">
                        <ScoreBadge :score="similar.spotify_popularity" />
                    </div>
                </div>
                <div class="p-3">
                    <p class="font-semibold text-sm truncate group-hover:text-primary transition-colors" :title="similar.name">
                        {{ similar.name }}
                    </p>
                    <p v-if="similar.genres && similar.genres.length > 0" class="text-[10px] text-muted-foreground truncate mt-0.5">
                        {{ similar.genres[0] }}
                    </p>
                </div>
            </Card>
        </div>
    </div>
</template>