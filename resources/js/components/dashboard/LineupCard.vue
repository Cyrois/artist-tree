<script setup lang="ts">
import ArtistAvatar from '@/components/artist/ArtistAvatar.vue';
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import Divider from '@/components/ui/divider/Divider.vue';
import type { Lineup } from '@/data/types';
import { trans } from 'laravel-vue-i18n';
import { Calendar, ChevronRight, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    lineup: Lineup;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    click: [lineup: Lineup];
}>();

const stats = computed(() => props.lineup.stats || {
    artistCount: 0,
    avgScore: 0,
});

const previewArtists = computed(() => props.lineup.previewArtists || []);
</script>

<template>
    <Card
        class="group cursor-pointer transition-all duration-200 hover:border-border/80 hover:shadow-md gap-0 pb-0"
        data-slot="lineup-card"
        @click="emit('click', lineup)"
    >
        <CardHeader class="pb-4">
            <div class="flex items-start justify-between">
                <div>
                    <CardTitle class="text-lg">{{ lineup.name }}</CardTitle>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ lineup.description }}
                    </p>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4 pb-6">
            <!-- Artist Avatars Preview -->
            <div v-if="previewArtists.length > 0" class="flex -space-x-2 overflow-hidden">
                <ArtistAvatar
                    v-for="artist in previewArtists"
                    :key="artist.id"
                    :artist="{ id: artist.id, name: artist.name, image: artist.image } as any"
                    size="sm"
                    class="ring-2 ring-background"
                />
                <div
                    v-if="stats.artistCount > previewArtists.length"
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-muted text-xs font-bold text-muted-foreground ring-2 ring-background"
                >
                    +{{ stats.artistCount - previewArtists.length }}
                </div>
            </div>

            <!-- Stats -->
            <div class="flex flex-col gap-3 text-sm">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <Users class="h-4 w-4" />
                    <span
                        >{{ stats.artistCount }}
                        {{ trans('lineups.card_artists') }}</span
                    >
                </div>
                <div class="flex items-center gap-2 text-muted-foreground">
                    <span class="mr-2">{{ trans('lineups.card_avg') }}</span>
                    <ScoreBadge :score="stats.avgScore" size="sm" />
                </div>
            </div>

            <!-- Last updated -->
            <div class="flex justify-end">
                <span class="text-xs text-muted-foreground">
                    {{ trans('lineups.card_updated') }} {{ lineup.updatedAt }}
                </span>
            </div>
        </CardContent>

        <CardFooter class="flex items-center justify-between border-t p-6 transition-all duration-200 group-hover:bg-muted/50">
            <span
                class="font-medium text-primary decoration-2 underline-offset-4 transition-all duration-200 group-hover:text-foreground group-hover:underline"
            >
                {{ trans('lineups.card_view_and_edit') }}
            </span>
            <ChevronRight
                class="h-4 w-4 text-primary transition-all duration-200 group-hover:translate-x-1 group-hover:text-foreground"
            />
        </CardFooter>
    </Card>
</template>
