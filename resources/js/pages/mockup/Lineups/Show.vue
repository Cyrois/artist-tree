<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import MockupLayout from '@/layouts/MockupLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import TierSection from '@/components/mockup/lineup/TierSection.vue';
import KanbanBoard from '@/components/mockup/booking/KanbanBoard.vue';
import ScheduleGrid from '@/components/mockup/schedule/ScheduleGrid.vue';
import ArtistAvatar from '@/components/mockup/artist/ArtistAvatar.vue';
import { getLineupById, getLineupStats, getAllLineupArtists, getLineupSchedule } from '@/data/lineups';
import { getArtistsByIds } from '@/data/artists';
import { tierOrder, formatCurrency } from '@/data/constants';
import type { Artist, TierType } from '@/data/types';
import { cn } from '@/lib/utils';
import { ArrowLeft, Search, Layers, Scale, Download, Check, Clock, DollarSign, X } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Props {
    id: number;
}

const props = defineProps<Props>();

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
        router.visit(`/mockup/artist/${artist.id}`);
    }
}

function clearSelection() {
    selectedArtistIds.value = [];
}

function exitCompareMode() {
    compareMode.value = false;
    selectedArtistIds.value = [];
}

const breadcrumbs = computed(() => [
    { title: 'Dashboard', href: '/mockup' },
    { title: 'My Lineups', href: '/mockup/lineups' },
    { title: lineup.value?.name ?? 'Lineup', href: `/mockup/lineups/${props.id}` },
]);
</script>

<template>
    <Head :title="`${lineup?.name ?? 'Lineup'} - Artist-Tree`" />
    <MockupLayout :breadcrumbs="breadcrumbs">
        <div v-if="lineup" class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" @click="router.visit('/mockup/lineups')">
                        <ArrowLeft class="w-5 h-5" />
                    </Button>
                    <div>
                        <h1 class="text-2xl font-bold">{{ lineup.name }}</h1>
                        <p class="text-sm text-muted-foreground">
                            {{ stats?.artistCount }} artists · Updated {{ lineup.updatedAt }}
                        </p>
                    </div>
                </div>
                <Button variant="outline">
                    <Download class="w-4 h-4 mr-2" />
                    Export
                </Button>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <Card>
                    <CardContent class="p-4 flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-muted">
                            <Check class="w-4 h-4 text-[hsl(var(--status-confirmed))]" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ stats?.confirmedCount }}</p>
                            <p class="text-xs text-muted-foreground">Confirmed</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4 flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-muted">
                            <Clock class="w-4 h-4 text-[hsl(var(--status-negotiating))]" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ stats?.pendingCount }}</p>
                            <p class="text-xs text-muted-foreground">Pending</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4 flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-muted">
                            <X class="w-4 h-4 text-[hsl(var(--status-declined))]" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ stats?.declinedCount }}</p>
                            <p class="text-xs text-muted-foreground">Declined</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4 flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-muted">
                            <DollarSign class="w-4 h-4" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ formatCurrency(stats?.totalBudget ?? 0) }}</p>
                            <p class="text-xs text-muted-foreground">Total Budget</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4 flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-muted">
                            <span class="text-sm font-bold">{{ stats?.avgScore }}</span>
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ stats?.avgScore }}</p>
                            <p class="text-xs text-muted-foreground">Avg Score</p>
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
                        {{ tab }}
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
                            placeholder="Search and add artists..."
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
                            Stack
                        </Button>
                        <Button
                            :variant="compareMode ? 'default' : 'outline'"
                            @click="compareMode = !compareMode; stackMode = false"
                            :class="{ 'bg-[hsl(var(--compare-coral))] hover:bg-[hsl(var(--compare-coral))]/90': compareMode }"
                        >
                            <Scale class="w-4 h-4 mr-2" />
                            Compare
                        </Button>
                    </div>
                </div>

                <!-- Mode Banners -->
                <div
                    v-if="stackMode"
                    class="flex items-center justify-between p-4 rounded-lg bg-[hsl(var(--stack-purple-bg))] border border-[hsl(var(--stack-purple))]/30"
                >
                    <p class="text-sm">
                        <span class="font-medium">Stack Mode:</span> Click the layers icon on an artist to make them primary, then click other artists to add as alternatives.
                    </p>
                    <Button variant="outline" size="sm" @click="stackMode = false">
                        Done
                    </Button>
                </div>

                <div
                    v-if="compareMode"
                    class="flex items-center justify-between p-4 rounded-lg bg-[hsl(var(--compare-coral-bg))] border border-[hsl(var(--compare-coral))]/30"
                >
                    <div class="flex items-center gap-4">
                        <p class="text-sm">
                            <span class="font-medium">Compare Mode:</span> Select up to 4 artists to compare
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
                        <Button variant="ghost" size="sm" @click="clearSelection">Clear</Button>
                        <Button size="sm" :disabled="selectedArtistIds.length < 2" @click="exitCompareMode">
                            Compare
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
                    @artist-click="(artist) => router.visit(`/mockup/artist/${artist.id}`)"
                />
            </div>

            <!-- Schedule Tab -->
            <div v-if="activeTab === 'schedule'">
                <ScheduleGrid
                    :artists="allArtists"
                    :schedule="schedule"
                    @artist-click="(artist) => router.visit(`/mockup/artist/${artist.id}`)"
                />
            </div>
        </div>

        <!-- Not Found -->
        <div v-else class="text-center py-12">
            <p class="text-muted-foreground">Lineup not found.</p>
            <Button class="mt-4" @click="router.visit('/mockup/lineups')">
                Back to Lineups
            </Button>
        </div>
    </MockupLayout>
</template>
