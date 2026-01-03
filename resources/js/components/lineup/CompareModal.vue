<script setup lang="ts">
import ArtistAvatar from '@/components/artist/ArtistAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
import { formatNumber } from '@/data/constants';
import type { Artist } from '@/data/types';
import { trans } from 'laravel-vue-i18n';
import {
    Activity,
    BarChart3,
    Instagram,
    Music2,
    Users,
    Youtube,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    open: boolean;
    artists: Artist[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

// Mock data helper
function getValue(artist: Artist, key: keyof Artist, multiplier: number = 1) {
    const val = artist[key];
    if (typeof val === 'number' && val > 0) return val;
    // Mock generation based on ID to be deterministic
    return (
        (Math.abs(Math.sin(artist.id + multiplier)) * 10000000 * multiplier) %
        (100000000 * multiplier)
    );
}

// Configuration for rows
const metrics = computed(() => [
    {
        id: 'score',
        label: trans('artists.metric_artist_score'),
        icon: Activity,
        getValue: (a: Artist) => a.score || Math.floor(Math.random() * 40) + 60, // Mock score 60-100
        format: (v: number) => Math.round(v),
        type: 'score',
    },
    {
        id: 'listeners',
        label: trans('artists.metric_monthly_listeners'),
        icon: Music2,
        getValue: (a: Artist) =>
            a.spotifyListeners || getValue(a, 'spotifyListeners', 1),
        format: (v: number) => formatNumber(v),
        type: 'number',
    },
    {
        id: 'popularity',
        label: trans('artists.metric_spotify_popularity'),
        icon: BarChart3,
        getValue: (a: Artist) =>
            a.spotifyPopularity || Math.floor(Math.random() * 40) + 60,
        format: (v: number) => `${Math.round(v)}/100`,
        type: 'progress',
    },
    {
        id: 'spotify_followers',
        label: trans('artists.show_spotify_followers'),
        icon: Users,
        getValue: (a: Artist) =>
            a.spotifyFollowers || getValue(a, 'spotifyFollowers', 0.5),
        format: (v: number) => formatNumber(v),
        type: 'number',
    },
    {
        id: 'youtube_subs',
        label: trans('artists.show_youtube_subscribers'),
        icon: Youtube,
        getValue: (a: Artist) =>
            a.youtubeSubscribers || getValue(a, 'youtubeSubscribers', 0.3),
        format: (v: number) => formatNumber(v),
        type: 'number',
    },
    {
        id: 'youtube_views',
        label: trans('artists.metric_total_youtube_views'),
        icon: Youtube,
        getValue: (a: Artist) =>
            a.youtubeViews || getValue(a, 'youtubeViews', 10),
        format: (v: number) => formatNumber(v),
        type: 'number',
    },
    {
        id: 'instagram',
        label: trans('artists.show_instagram_followers'),
        icon: Instagram,
        getValue: (a: Artist) =>
            a.instagramFollowers || getValue(a, 'instagramFollowers', 0.8),
        format: (v: number) => formatNumber(v),
        type: 'number',
    },
]);

// Determine highest value for each metric row
function isHighest(metricId: string, value: number) {
    if (props.artists.length < 2) return false;
    const values = props.artists.map((a) => {
        const metric = metrics.value.find((m) => m.id === metricId);
        return metric ? metric.getValue(a) : 0;
    });
    return value === Math.max(...values);
}

const gridColsStyle = computed(() => {
    return {
        gridTemplateColumns: `200px repeat(${props.artists.length}, 1fr)`,
    };
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="flex max-h-[85vh] max-w-[95vw] flex-col p-0 md:max-w-6xl"
        >
            <DialogHeader class="px-6 pt-6 pb-2">
                <DialogTitle class="text-2xl font-bold"
                    >{{ $t('lineups.compare_modal_title') }}</DialogTitle
                >
                <DialogDescription>
                    {{ $t('lineups.compare_modal_subtitle', { count: artists.length }) }}
                </DialogDescription>
            </DialogHeader>

            <div class="scrollbar-hide flex-1 overflow-x-auto overflow-y-auto p-6">
                <div class="min-w-[800px] space-y-6">
                    <!-- Header Row -->
                    <div class="grid gap-4" :style="gridColsStyle">
                        <div class="pt-20">
                            <!-- Empty top-left cell -->
                        </div>
                        <div
                            v-for="artist in artists"
                            :key="artist.id"
                            class="flex flex-col items-center text-center"
                        >
                            <ArtistAvatar
                                :artist="artist"
                                size="xl"
                                class="mb-4 h-24 w-24 border-4 border-background shadow-lg"
                                :class="{
                                    'ring-4 ring-[hsl(var(--compare-coral))] ring-offset-2': false,
                                }"
                            />
                            <h3 class="mb-2 text-lg font-bold">
                                {{ artist.name }}
                            </h3>
                            <div class="flex flex-wrap justify-center gap-2">
                                <Badge
                                    v-if="artist.lineup_tier"
                                    variant="secondary"
                                    class="text-[10px] uppercase"
                                >
                                    {{ $t(`lineups.tier_${artist.lineup_tier}`) }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <!-- Metrics Rows -->
                    <div class="space-y-2">
                        <div
                            v-for="metric in metrics"
                            :key="metric.id"
                            class="group grid items-center gap-4 rounded-xl bg-muted/30 p-4 transition-colors hover:bg-muted/50"
                            :style="gridColsStyle"
                        >
                            <!-- Label Column -->
                            <div class="flex items-center gap-3 font-medium">
                                <component
                                    :is="metric.icon"
                                    class="h-4 w-4 text-muted-foreground"
                                />
                                {{ metric.label }}
                            </div>

                            <!-- Artist Values -->
                            <div
                                v-for="artist in artists"
                                :key="artist.id"
                                class="text-center"
                            >
                                <div
                                    class="flex flex-col items-center justify-center gap-1"
                                >
                                    <!-- Score Display -->
                                    <template v-if="metric.type === 'score'">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-2xl font-bold"
                                                :class="
                                                    isHighest(
                                                        metric.id,
                                                        metric.getValue(artist),
                                                    )
                                                        ? 'text-green-600'
                                                        : ''
                                                "
                                            >
                                                {{
                                                    metric.format(
                                                        metric.getValue(artist),
                                                    )
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    isHighest(
                                                        metric.id,
                                                        metric.getValue(artist),
                                                    )
                                                "
                                                class="flex items-center text-[10px] font-bold text-green-600 uppercase"
                                            >
                                                <div
                                                    class="mr-1 flex h-4 w-4 items-center justify-center rounded-full bg-green-100"
                                                >
                                                    <span class="text-xs"
                                                        >★</span
                                                    >
                                                </div>
                                                {{ $t('lineups.compare_modal_highest') }}
                                            </span>
                                        </div>
                                    </template>

                                    <!-- Progress Bar Display -->
                                    <template
                                        v-else-if="metric.type === 'progress'"
                                    >
                                        <div class="w-full max-w-[140px]">
                                            <div
                                                class="mb-1 text-center font-bold"
                                            >
                                                {{
                                                    metric.format(
                                                        metric.getValue(artist),
                                                    )
                                                }}
                                            </div>
                                            <Progress
                                                :model-value="
                                                    metric.getValue(artist)
                                                "
                                                class="h-2"
                                                :class="
                                                    isHighest(
                                                        metric.id,
                                                        metric.getValue(artist),
                                                    )
                                                        ? 'bg-green-100 [&>div]:bg-green-500'
                                                        : '[&>div]:bg-[hsl(var(--compare-coral))]'
                                                "
                                            />
                                        </div>
                                    </template>

                                    <!-- Standard Number Display -->
                                    <template v-else>
                                        <span
                                            class="font-bold"
                                            :class="
                                                isHighest(
                                                    metric.id,
                                                    metric.getValue(artist),
                                                )
                                                    ? 'text-green-600'
                                                    : ''
                                            "
                                        >
                                            {{
                                                metric.format(
                                                    metric.getValue(artist),
                                                )
                                            }}
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter
                class="flex flex-row items-center justify-between border-t p-6 sm:justify-between"
            >
                <div
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <span class="text-green-600">★</span> {{ $t('lineups.compare_modal_legend') }}
                </div>
                <Button variant="outline" @click="emit('update:open', false)"
                    >{{ $t('common.action_close') }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
