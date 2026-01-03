<script setup lang="ts">
import { Card } from '@/components/ui/card';
import { router } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

interface Artist {
    id: number;
    name: string;
    image_url: string | null;
    tier: string;
}

interface Lineup {
    id: number;
    name: string;
    description: string;
    created_at: string;
    updated_at_human: string;
    artist_count: number;
    preview_artists: Artist[];
}

const props = defineProps<{
    lineup: Lineup;
}>();

const tiers = ['headliner', 'sub_headliner', 'mid_tier', 'undercard'];

const tierColors: Record<string, string> = {
    headliner: 'bg-black dark:bg-white',
    sub_headliner: 'bg-gray-700 dark:bg-gray-400',
    mid_tier: 'bg-gray-400 dark:bg-gray-600',
    undercard: 'bg-gray-300 dark:bg-gray-800',
};

const artistsByTier = computed(() => {
    const grouped: Record<string, Artist[]> = {
        headliner: [],
        sub_headliner: [],
        mid_tier: [],
        undercard: [],
    };

    (props.lineup.preview_artists || []).forEach((artist) => {
        if (grouped[artist.tier]) {
            grouped[artist.tier].push(artist);
        }
    });
    return grouped;
});

function getInitials(name: string) {
    return name
        .split(' ')
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function getRandomColor(name: string) {
    const colors = [
        'bg-red-500',
        'bg-blue-500',
        'bg-green-500',
        'bg-yellow-500',
        'bg-purple-500',
        'bg-pink-500',
        'bg-indigo-500',
        'bg-teal-500',
    ];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
}
</script>

<template>
    <Card
        class="group flex h-full cursor-pointer flex-col gap-0 overflow-hidden py-0 transition-all duration-200 hover:shadow-lg"
        @click="router.visit(`/lineups/${lineup.id}`)"
    >
        <div class="flex-1 px-6 pt-6 pb-4">
            <!-- Header -->
            <div class="mb-2 flex items-start justify-between">
                <div class="flex-1">
                    <h3 class="text-xl leading-tight font-bold text-foreground">
                        {{ lineup.name }}
                    </h3>
                    <p
                        class="mt-1 min-h-[1.25rem] text-sm text-muted-foreground"
                    >
                        {{ lineup.description }}
                    </p>
                </div>
                <div class="ml-4 text-right">
                    <div class="text-3xl font-bold">
                        {{ lineup.artist_count }}
                    </div>
                    <div
                        class="text-xs leading-none tracking-wide text-muted-foreground uppercase"
                    >
                        {{ $t('lineups.card_artists') }}
                    </div>
                </div>
            </div>

            <!-- Tiers List -->
            <div class="mt-8 flex flex-col justify-between space-y-4">
                <div
                    v-for="tier in tiers"
                    :key="tier"
                    class="flex h-8 items-center justify-between"
                >
                    <div class="flex flex-1 items-center gap-4">
                        <div
                            class="h-2 w-2 rounded-full"
                            :class="tierColors[tier]"
                        ></div>
                        <!-- Bullet -->
                        <span
                            class="w-24 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            {{ $t('lineups.tier_' + tier) }}
                        </span>

                        <!-- Avatars -->
                        <div class="flex -space-x-2">
                            <template v-if="artistsByTier[tier].length > 0">
                                <div
                                    v-for="artist in artistsByTier[tier].slice(
                                        0,
                                        4,
                                    )"
                                    :key="artist.id"
                                    class="relative z-0 flex h-8 w-8 items-center justify-center rounded-full border-2 border-background text-xs font-bold text-white transition-all hover:z-10"
                                    :class="getRandomColor(artist.name)"
                                    :title="artist.name"
                                >
                                    <img
                                        v-if="artist.image_url"
                                        :src="artist.image_url"
                                        class="h-full w-full rounded-full object-cover"
                                    />
                                    <span v-else>{{
                                        getInitials(artist.name)
                                    }}</span>
                                </div>
                                <div
                                    v-if="artistsByTier[tier].length > 4"
                                    class="relative z-0 flex h-8 w-8 items-center justify-center rounded-full border-2 border-background bg-muted text-xs font-medium text-muted-foreground"
                                >
                                    +{{ artistsByTier[tier].length - 4 }}
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Count -->
                    <div class="text-sm font-medium text-muted-foreground">
                        {{ artistsByTier[tier].length }}
                    </div>
                </div>

                <div class="mt-4 text-right text-xs text-muted-foreground">
                    {{ $t('lineups.card_updated') }}
                    {{ lineup.updated_at_human }}
                </div>
            </div>
        </div>

        <!-- Footer Action -->
        <div
            class="flex items-center justify-between border-t p-4 transition-all duration-200 group-hover:bg-muted/50"
        >
            <span
                class="font-medium text-primary decoration-2 underline-offset-4 transition-all duration-200 group-hover:text-foreground group-hover:underline"
            >
                {{ $t('lineups.card_view_and_edit') }}
            </span>
            <ChevronRight
                class="h-4 w-4 text-primary transition-all duration-200 group-hover:translate-x-1 group-hover:text-foreground"
            />
        </div>
    </Card>
</template>
