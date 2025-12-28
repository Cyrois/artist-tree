<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import TierSection from '@/components/lineup/TierSection.vue';
import ArtistAvatar from '@/components/artist/ArtistAvatar.vue';
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { getLineupById, getLineupStats, getAllLineupArtists } from '@/data/lineups';
import { getArtistsByIds } from '@/data/artists';
import { tierOrder } from '@/data/constants';
import type { Artist, TierType } from '@/data/types';
import { Search, Layers, Scale, Download, Calendar, Users } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';

interface Props {
    id: number;
}

const props = defineProps<Props>();
const { lineup: lineupBreadcrumbs } = useBreadcrumbs();

const lineup = computed(() => getLineupById(props.id));
const stats = computed(() => lineup.value ? getLineupStats(lineup.value) : null);
const allArtists = computed(() => lineup.value ? getAllLineupArtists(lineup.value) : []);

// Mode states
const stackMode = ref(false);
const compareMode = ref(false);
const selectedArtistIds = ref<number[]>([]);

// Search
const searchQuery = ref('');

// Get artists by tier
function getArtistsByTier(tier: TierType) {
    if (!lineup.value) return [];
    return getArtistsByIds(lineup.value.artists[tier]);
}

function handleArtistSelect(artist: Artist) {
    if (compareMode.value) {
        const index = selectedArtistIds.value.indexOf(artist.id);
        if (index === -1 && selectedArtistIds.value.length < 4) {
            selectedArtistIds.value.push(artist.id);
        } else if (index !== -1) {
            selectedArtistIds.value.splice(index, 1);
        }
    }
}

function handleArtistView(artist: Artist) {
    if (!compareMode.value && !stackMode.value) {
        router.visit(`/artist/${artist.id}`);
    }
}

function clearSelection() {
    selectedArtistIds.value = [];
}

function exitCompareMode() {
    compareMode.value = false;
    selectedArtistIds.value = [];
}

const breadcrumbs = computed(() => 
    lineupBreadcrumbs(
        lineup.value?.name ?? trans('lineups.show_page_title'), 
        props.id
    )
);
</script>

<template>
    <Head :title="`${lineup?.name ?? $t('lineups.show_page_title')} - Artist-Tree`" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div v-if="lineup" class="space-y-6">
            <!-- Lineup Header Card -->
            <Card class="py-0">
                <CardContent class="p-6">
                    <div class="flex flex-col md:flex-row justify-between gap-6">
                        <!-- Info -->
                        <div class="flex-1">
                            <h1 class="text-3xl font-bold">{{ lineup.name }}</h1>
                            
                            <div class="flex flex-wrap items-center gap-8 mt-6">
                                <!-- Artist Count -->
                                <div class="flex items-center gap-3">
                                    <div class="p-2.5 rounded-full bg-muted">
                                        <Users class="w-5 h-5 text-muted-foreground" />
                                    </div>
                                    <div>
                                        <p class="text-xs text-muted-foreground font-medium uppercase tracking-wider mb-0.5">{{ $t('lineups.show_stats_artists') }}</p>
                                        <p class="text-xl font-bold leading-none">{{ stats?.artistCount ?? 0 }}</p>
                                    </div>
                                </div>

                                <!-- Avg Score -->
                                <div class="flex items-center gap-3">
                                    <ScoreBadge 
                                        v-if="stats?.avgScore" 
                                        :score="Math.round(stats.avgScore)" 
                                        size="lg"
                                    />
                                    <div v-else class="h-10 w-10 flex items-center justify-center rounded-full bg-muted">
                                        <span class="font-bold">-</span>
                                    </div>
                                    <div>
                                        <p class="text-xs text-muted-foreground font-medium uppercase tracking-wider mb-0.5">{{ $t('lineups.show_stats_avg_score') }}</p>
                                        <p class="text-xl font-bold leading-none">{{ stats?.avgScore ?? '-' }}</p>
                                    </div>
                                </div>

                                <!-- Updated Date -->
                                <div class="flex items-center gap-3">
                                    <div class="p-2.5 rounded-full bg-muted">
                                        <Calendar class="w-5 h-5 text-muted-foreground" />
                                    </div>
                                    <div>
                                        <p class="text-xs text-muted-foreground font-medium uppercase tracking-wider mb-0.5">{{ $t('lineups.show_updated_prefix') }}</p>
                                        <p class="text-xl font-bold leading-none">{{ lineup.updatedAt }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-start gap-3">
                            <Button variant="outline">
                                <Download class="w-4 h-4 mr-2" />
                                {{ $t('lineups.show_export_button') }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Lineup Content -->
            <div class="space-y-6">
                <!-- Toolbar -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="$t('lineups.show_search_placeholder')"
                            class="pl-9"
                        />
                    </div>
                    <Separator orientation="vertical" class="hidden sm:block h-10" />
                    <div class="flex gap-2">
                        <Button
                            :variant="stackMode ? 'default' : 'outline'"
                            @click="stackMode = !stackMode; compareMode = false"
                            :class="{ 'bg-[hsl(var(--stack-purple))] hover:bg-[hsl(var(--stack-purple))]/90': stackMode }"
                        >
                            <Layers class="w-4 h-4 mr-2" />
                            {{ $t('lineups.show_stack_button') }}
                        </Button>
                        <Button
                            :variant="compareMode ? 'default' : 'outline'"
                            @click="compareMode = !compareMode; stackMode = false"
                            :class="{ 'bg-[hsl(var(--compare-coral))] hover:bg-[hsl(var(--compare-coral))]/90': compareMode }"
                        >
                            <Scale class="w-4 h-4 mr-2" />
                            {{ $t('lineups.show_compare_button') }}
                        </Button>
                    </div>
                </div>

                <!-- Mode Banners -->
                <div
                    v-if="stackMode"
                    class="flex items-center justify-between p-4 rounded-lg bg-[hsl(var(--stack-purple-bg))] border border-[hsl(var(--stack-purple))]/30"
                >
                    <p class="text-sm">
                        {{ $t('lineups.show_stack_mode_description') }}
                    </p>
                    <Button variant="outline" size="sm" @click="stackMode = false">
                        {{ $t('lineups.show_stack_mode_done') }}
                    </Button>
                </div>

                <div
                    v-if="compareMode"
                    class="flex items-center justify-between p-4 rounded-lg bg-[hsl(var(--compare-coral-bg))] border border-[hsl(var(--compare-coral))]/30"
                >
                    <div class="flex items-center gap-4">
                        <p class="text-sm">
                            {{ $t('lineups.show_compare_mode_description') }}
                        </p>
                        <div class="flex -space-x-2">
                            <ArtistAvatar
                                v-for="id in selectedArtistIds"
                                :key="id"
                                :artist="allArtists.find(a => a.id === id)!"
                                size="sm"
                                class="border-2 border-background"
                            />
                        </div>
                        <Badge v-if="selectedArtistIds.length > 0">{{ selectedArtistIds.length }}/4</Badge>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="ghost" size="sm" @click="clearSelection">{{ $t('lineups.show_compare_clear') }}</Button>
                        <Button size="sm" :disabled="selectedArtistIds.length < 2" @click="exitCompareMode">
                            {{ $t('lineups.show_compare_submit') }}
                        </Button>
                    </div>
                </div>

                <!-- Tier Sections -->
                <div class="space-y-4">
                    <TierSection
                        v-for="tier in tierOrder"
                        :key="tier"
                        :tier="tier"
                        :artists="getArtistsByTier(tier)"
                        :statuses="lineup.artistStatuses"
                        :compare-mode="compareMode"
                        :selected-artist-ids="selectedArtistIds"
                        @select-artist="handleArtistSelect"
                        @view-artist="handleArtistView"
                        @remove-artist="() => {}"
                    />
                </div>
            </div>
        </div>

        <!-- Not Found -->
        <div v-else class="text-center py-12">
            <p class="text-muted-foreground">{{ $t('lineups.show_not_found') }}</p>
            <Button class="mt-4" @click="router.visit('/lineups')">
                {{ $t('lineups.show_back_button') }}
            </Button>
        </div>
    </MainLayout>
</template>
