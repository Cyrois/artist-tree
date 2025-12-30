<script setup lang="ts">
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { Card } from '@/components/ui/card';
import { useAsyncSpotifyData } from '@/composables/useAsyncSpotifyData';
import { AlertCircle, Loader2, Users } from 'lucide-vue-next';
import { onMounted } from 'vue';

interface SimilarArtist {
    spotify_id: string;
    name: string;
    genres: string[];
    image_url: string | null;
    score: number;
    spotify_followers: number;
}

interface Props {
    artistId: number;
}

const props = defineProps<Props>();

const {
    data: artists,
    loading,
    error,
    load,
} = useAsyncSpotifyData<SimilarArtist[]>(
    `/api/artists/${props.artistId}/similar`,
);

onMounted(() => {
    load({ limit: 8 });
});
</script>

<template>
    <div class="space-y-4">
        <h3 class="flex items-center gap-2 text-lg font-semibold">
            <Users class="h-5 w-5" />
            {{ $t('artists.show_similar_artists_title') }}
        </h3>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <div class="flex flex-col items-center gap-3">
                <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                <p class="text-sm text-muted-foreground">
                    {{ $t('artists.similar_finding') }}
                </p>
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
        <div
            v-else-if="!artists || artists.length === 0"
            class="flex flex-col items-center justify-center rounded-lg border border-dashed bg-muted/30 py-12"
        >
            <Users class="mb-2 h-8 w-8 text-muted-foreground" />
            <p class="text-sm text-muted-foreground">
                {{ $t('artists.similar_none_found') }}
            </p>
        </div>

        <!-- Grid Layout -->
        <div
            v-else
            class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4 lg:grid-cols-6 xl:grid-cols-8"
        >
            <Card
                v-for="(similar, index) in artists"
                :key="similar.spotify_id"
                class="group cursor-pointer gap-0 overflow-hidden border-muted p-0 transition-all hover:shadow-md"
                :class="{
                    'hidden md:block': index >= 2 && index < 4,
                    'hidden lg:block': index >= 4 && index < 6,
                    'hidden xl:block': index >= 6 && index < 8,
                    hidden: index >= 8,
                }"
            >
                <div class="relative aspect-square overflow-hidden bg-muted">
                    <img
                        v-if="similar.image_url"
                        :src="similar.image_url"
                        :alt="similar.name"
                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-110"
                    />
                    <div
                        v-else
                        class="absolute inset-0 flex items-center justify-center bg-muted text-2xl font-bold text-muted-foreground"
                    >
                        {{ similar.name.charAt(0) }}
                    </div>
                    <div class="absolute top-2 right-2">
                        <ScoreBadge :score="similar.score" />
                    </div>
                </div>
                <div class="p-3">
                    <p
                        class="truncate text-sm font-semibold transition-colors group-hover:text-primary"
                        :title="similar.name"
                    >
                        {{ similar.name }}
                    </p>
                    <p
                        v-if="similar.genres && similar.genres.length > 0"
                        class="mt-0.5 truncate text-[10px] text-muted-foreground"
                    >
                        {{ similar.genres[0] }}
                    </p>
                </div>
            </Card>
        </div>
    </div>
</template>
