<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import TierSection from '@/components/lineup/TierSection.vue';
import KanbanBoard from '@/components/booking/KanbanBoard.vue';
import ScheduleGrid from '@/components/schedule/ScheduleGrid.vue';
import ArtistAvatar from '@/components/artist/ArtistAvatar.vue';
import { getLineupById, getLineupStats, getAllLineupArtists, getLineupSchedule } from '@/data/lineups';
import { getArtistsByIds } from '@/data/artists';
import { tierOrder, formatCurrency } from '@/data/constants';
import type { Artist, TierType } from '@/data/types';
import { ArrowLeft, Search, Layers, Scale, Download } from 'lucide-vue-next';
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
const schedule = computed(() => getLineupSchedule(props.id));

// Tab state
const activeTab = ref<'lineup' | 'booking' | 'schedule'>('lineup');

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

// Get artist tiers map for Kanban
const artistTiers = computed(() => {
    if (!lineup.value) return {};
    const map: Record<number, TierType> = {};
    tierOrder.forEach((tier) => {
        lineup.value!.artists[tier].forEach((id) => {
            map[id] = tier;
        });
    });
    return map;
});

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
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" @click="router.visit('/lineups')">
                        <ArrowLeft class="w-5 h-5" />
                    </Button>
                    <div>
                        <h1 class="text-2xl font-bold">{{ lineup.name }}</h1>
                        <p class="text-sm text-muted-foreground">
                            {{ stats?.artistCount }} {{ $t('lineups.show_stats_artists').toLowerCase() }} · {{ $t('lineups.show_updated_prefix') }} {{ lineup.updatedAt }}
                        </p>
                    </div>
                </div>
                <Button variant="outline">
                    <Download class="w-4 h-4 mr-2" />
                    {{ $t('lineups.show_export_button') }}
                </Button>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Artist Count & Average Score Card -->
                <Card>
                    <CardContent class="px-4 py-1.5 flex flex-col h-full">
                        <div class="flex flex-col justify-between h-full">
                            <div class="flex justify-between items-baseline">
                                <p class="text-xs text-muted-foreground">{{ $t('lineups.show_stats_artists') }}</p>
                                <p class="text-lg font-bold">{{ stats?.artistCount ?? 0 }}</p>
                            </div>
                            <Separator class="my-1" />
                            <div class="flex justify-between items-center">
                                <p class="text-xs text-muted-foreground">{{ $t('lineups.show_stats_avg_score') }}</p>
                                <div class="p-2 rounded-lg bg-green-100">
                                    <span class="text-sm font-bold text-green-700">{{ stats?.avgScore }}</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Artist Status Breakdown Card -->
                <Card>
                    <CardContent class="px-4 py-1.5">
                        <div class="grid grid-cols-4 gap-2 text-center">
                            <div>
                                <p class="text-lg font-bold" :style="{ color: '#8b5cf6' }">{{ stats?.ideaCount ?? 0 }}</p>
                                <p class="text-[10px] text-muted-foreground">{{ $t('lineups.show_stats_idea') }}</p>
                            </div>
                            <div>
                                <p class="text-lg font-bold" :style="{ color: '#3b82f6' }">{{ stats?.outreachCount ?? 0 }}</p>
                                <p class="text-[10px] text-muted-foreground">{{ $t('lineups.show_stats_outreach') }}</p>
                            </div>
                            <div>
                                <p class="text-lg font-bold" :style="{ color: '#f59e0b' }">{{ stats?.negotiatingCount ?? 0 }}</p>
                                <p class="text-[10px] text-muted-foreground">{{ $t('lineups.show_stats_negotiating') }}</p>
                            </div>
                            <div>
                                <p class="text-lg font-bold" :style="{ color: '#6366f1' }">{{ stats?.contractSentCount ?? 0 }}</p>
                                <p class="text-[10px] text-muted-foreground">{{ $t('lineups.show_stats_contract') }}</p>
                            </div>
                        </div>
                        <Separator class="my-1.5" />
                        <div class="grid grid-cols-2 gap-2 text-center">
                            <div>
                                <p class="text-lg font-bold" :style="{ color: '#10b981' }">{{ stats?.contractSignedCount ?? 0 }}</p>
                                <p class="text-[10px] text-muted-foreground">{{ $t('lineups.show_stats_signed') }}</p>
                            </div>
                            <div>
                                <p class="text-lg font-bold" :style="{ color: '#059669' }">{{ stats?.confirmedCount ?? 0 }}</p>
                                <p class="text-[10px] text-muted-foreground">{{ $t('lineups.show_stats_confirmed') }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Budget Breakdown Card -->
                <Card>
                    <CardContent class="px-4 py-1.5 h-full">
                        <div class="space-y-1 flex flex-col h-full justify-between">
                            <div class="flex justify-between items-baseline">
                                <p class="text-xs text-muted-foreground">{{ $t('lineups.show_stats_projected') }}</p>
                                <p class="text-sm font-semibold">{{ formatCurrency(stats?.totalBudget ?? 0) }}</p>
                            </div>
                            <div class="flex justify-between items-baseline">
                                <p class="text-xs text-muted-foreground">{{ $t('lineups.show_stats_confirmed') }}</p>
                                <p class="text-lg font-bold text-green-600">{{ formatCurrency(stats?.confirmedBudget ?? 0) }}</p>
                            </div>
                            <div class="flex justify-between items-baseline">
                                <p class="text-xs text-muted-foreground">{{ $t('lineups.show_stats_remaining') }}</p>
                                <p class="text-sm font-medium">{{ formatCurrency((stats?.totalBudget ?? 0) - (stats?.confirmedBudget ?? 0)) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Tabs -->
            <div class="border-b">
                <div class="flex gap-6">
                    <button
                        v-for="tab in ['lineup', 'booking', 'schedule'] as const"
                        :key="tab"
                        :class="[
                            'pb-3 text-sm font-medium transition-colors border-b-2 -mb-px capitalize',
                            activeTab === tab ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeTab = tab"
                    >
                        {{ $t(`lineups.show_tab_${tab}`) }}
                    </button>
                </div>
            </div>

            <!-- Lineup Tab -->
            <div v-if="activeTab === 'lineup'" class="space-y-6">
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

            <!-- Booking Tab -->
            <div v-if="activeTab === 'booking'">
                <KanbanBoard
                    :artists="allArtists"
                    :statuses="lineup.artistStatuses"
                    :artist-tiers="artistTiers"
                    @artist-click="(artist) => router.visit(`/artist/${artist.id}`)"
                />
            </div>

            <!-- Schedule Tab -->
            <div v-if="activeTab === 'schedule'">
                <ScheduleGrid
                    :artists="allArtists"
                    :schedule="schedule"
                    @artist-click="(artist) => router.visit(`/artist/${artist.id}`)"
                />
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
