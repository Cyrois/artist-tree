<script setup lang="ts">
import type { Artist } from '@/data/types';
import { cn } from '@/lib/utils';
import ArtistCard from './ArtistCard.vue';

interface Props {
    artists: Artist[];
    compact?: boolean;
    columns?: 2 | 3 | 4 | 5;
}

withDefaults(defineProps<Props>(), {
    compact: false,
    columns: 4,
});

const emit = defineEmits<{
    'select-artist': [artist: Artist];
}>();

const gridClasses = {
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
    5: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5',
};
</script>

<template>
    <div
        :class="cn('grid gap-4', gridClasses[columns])"
        data-slot="artist-card-grid"
    >
        <ArtistCard
            v-for="artist in artists"
            :key="artist.id"
            :artist="artist"
            :compact="compact"
            @click="emit('select-artist', artist)"
        />
    </div>
</template>
