<script setup lang="ts">
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Divider from '@/components/ui/divider/Divider.vue';
import type { Lineup } from '@/data/types';
import { trans } from 'laravel-vue-i18n';
import { Calendar, Users } from 'lucide-vue-next';
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
</script>

<template>
    <Card
        class="cursor-pointer transition-all duration-200 hover:border-border/80 hover:shadow-md"
        data-slot="lineup-card"
        @click="emit('click', lineup)"
    >
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between">
                <div>
                    <CardTitle class="text-lg">{{ lineup.name }}</CardTitle>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ lineup.description }}
                    </p>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
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
            <div>
                <Divider class="my-2" />
                <div
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <Calendar class="h-3 w-3" />
                    <span
                        >{{ trans('lineups.card_updated') }}
                        {{ lineup.updatedAt }}</span
                    >
                </div>
            </div>
        </CardContent>
    </Card>
</template>
