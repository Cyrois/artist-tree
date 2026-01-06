<script setup lang="ts">
import AddToLineupModal from '@/components/lineup/AddToLineupModal.vue';
import ArtistSearch from '@/components/lineup/ArtistSearch.vue';
import CompareModal from '@/components/lineup/CompareModal.vue';
import CompareModeBanner from '@/components/lineup/CompareModeBanner.vue';
import DeleteLineupModal from '@/components/lineup/DeleteLineupModal.vue';
import EditLineupModal from '@/components/lineup/EditLineupModal.vue';
import RemoveArtistFromLineupModal from '@/components/lineup/RemoveArtistFromLineupModal.vue';
import StackModeBanner from '@/components/lineup/StackModeBanner.vue';
import TierSection from '@/components/lineup/TierSection.vue';
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useBreadcrumbs } from '@/composables/useBreadcrumbs';
import { tierOrder } from '@/data/constants';
import type { Artist, TierType } from '@/data/types';
import MainLayout from '@/layouts/MainLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { useElementBounding, useWindowSize } from '@vueuse/core';
import axios from 'axios';
import { trans } from 'laravel-vue-i18n';
import { Download, Pencil, Settings, Trash2, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ApiArtist extends Artist {
    lineup_tier: TierType;
}

interface SearchResultArtist {
    id: number | null;
    spotify_id: string;
    name: string;
    image_url: string | null;
    genres: string[];
    score: number;
    exists_in_database: boolean;
    source: 'local' | 'spotify';
    spotify_popularity: number;
}

interface Props {
    id: number;
    lineup: {
        id: number;
        name: string;
        description?: string | null;
        updated_at: string;
        updated_at_human: string;
        artists: Record<TierType, ApiArtist[]>;
        artistStatuses: any;
        stats: {
            artist_count: number;
            avg_score: number;
        };
    };
}

const props = defineProps<Props>();
const { lineup: lineupBreadcrumbs } = useBreadcrumbs();

// Local state for dynamic updates
const lineupData = ref(props.lineup);

// Sync local state when props change
watch(
    () => props.lineup,
    (newVal) => {
        lineupData.value = newVal;
    },
    { deep: true },
);

// Search Position Logic
const searchContainerRef = ref<HTMLElement | null>(null);
const searchComponentRef = ref<HTMLElement | null>(null);
const pageContentRef = ref<HTMLElement | null>(null);

const { top: searchTop } = useElementBounding(searchContainerRef);
const { height: searchHeight } = useElementBounding(searchComponentRef);
const { left: pageLeft, width: pageWidth } = useElementBounding(pageContentRef);
const { width: windowWidth } = useWindowSize();
const isLargeScreen = computed(() => windowWidth.value >= 1024); // lg breakpoint

// Sticky top offset is always 0 so it slides behind the StackModeBanner (z-100)
const stickyTopOffset = computed(() => 0);

// Trigger stickiness when the container hits the top (or below the stack banner)
const isStuck = computed(() => {
    if (!isLargeScreen.value || !searchContainerRef.value) return false;
    return searchTop.value <= stickyTopOffset.value;
});

// Flatten artists for search/avatar lookup
const allArtists = computed(() => {
    if (!lineupData.value) return [];
    return Object.values(lineupData.value.artists).flat();
});

// Mode states
const stackMode = ref(false);
const compareMode = ref(false);
const isCompareModalOpen = ref(false);
const selectedArtistIds = ref<number[]>([]);
const isAddingAlternativesTo = ref<string | null>(null); // stack_id
const stackingTier = ref<TierType | null>(null);

const stackingPrimaryArtist = computed(() => {
    if (!isAddingAlternativesTo.value) return null;
    return allArtists.value.find(
        (a) =>
            a.stack_id === isAddingAlternativesTo.value && a.is_stack_primary,
    );
});

const selectedArtists = computed(() => {
    return allArtists.value.filter((a) =>
        selectedArtistIds.value.includes(a.id),
    );
});

// Search State
const addingArtistId = ref<string | number | null>(null);

// Add to Lineup Modal State
const isAddModalOpen = ref(false);
const artistToAdd = ref<SearchResultArtist | null>(null);
const suggestedTier = ref<TierType | null>(null);
const isAddingToLineup = ref(false);

// Edit Lineup Modal State
const isEditModalOpen = ref(false);

// Delete Lineup Modal State
const isDeleteModalOpen = ref(false);

// Remove Artist Modal State
const isRemoveArtistModalOpen = ref(false);
const artistToRemove = ref<ApiArtist | null>(null);

// Get artists by tier
function getArtistsByTier(tier: TierType) {
    if (!lineupData.value) return [];

    // Map API structure to component expectations
    return lineupData.value.artists[tier].map((artist) => ({
        ...artist,
        image: artist.image_url || undefined, // Map image_url for ArtistAvatar
        genre: artist.genres || [], // Map genres for TierSection
    }));
}

async function handleArtistSelect(artist: Artist) {
    if (compareMode.value) {
        const index = selectedArtistIds.value.indexOf(artist.id);
        if (index === -1 && selectedArtistIds.value.length < 4) {
            selectedArtistIds.value.push(artist.id);
        } else if (index !== -1) {
            selectedArtistIds.value.splice(index, 1);
        }
    } else if (stackMode.value && isAddingAlternativesTo.value) {
        // Adding alternative to stack
        if (artist.stack_id === isAddingAlternativesTo.value) return;

        // Only allow selecting artists in the same tier
        if (stackingTier.value && artist.lineup_tier !== stackingTier.value)
            return;

        try {
            const response = await axios.post(
                `/api/lineups/${props.id}/stacks`,
                {
                    artist_id: artist.id,
                    stack_id: isAddingAlternativesTo.value,
                },
            );
            lineupData.value = response.data.lineup;
        } catch (e) {
            console.error('Failed to update stack', e);
        }
    }
}

function handleArtistView(artist: Artist) {
    if (!compareMode.value && !stackMode.value) {
        router.visit(`/artist/${artist.id}`);
    }
}

function handleArtistRemove(artist: Artist) {
    artistToRemove.value = artist as ApiArtist;
    isRemoveArtistModalOpen.value = true;
}

async function handleStartStack(artist: Artist) {
    stackingTier.value = artist.lineup_tier || null;

    if (artist.stack_id) {
        isAddingAlternativesTo.value = artist.stack_id;
        stackMode.value = true;
        return;
    }

    try {
        const response = await axios.post(`/api/lineups/${props.id}/stacks`, {
            artist_id: artist.id,
        });
        lineupData.value = response.data.lineup;

        // Find the new stack_id for this artist
        const updatedArtist = (
            Object.values(lineupData.value.artists).flat() as Artist[]
        ).find((a) => a.id === artist.id);
        if (updatedArtist?.stack_id) {
            isAddingAlternativesTo.value = updatedArtist.stack_id;
            stackMode.value = true;
        }
    } catch (e) {
        console.error('Failed to start stack', e);
    }
}

async function handlePromoteArtist(artist: Artist) {
    if (!artist.stack_id) return;

    try {
        const response = await axios.post(
            `/api/lineups/${props.id}/stacks/${artist.stack_id}/promote`,
            {
                artist_id: artist.id,
            },
        );
        lineupData.value = response.data.lineup;
    } catch (e) {
        console.error('Failed to promote artist', e);
    }
}

async function handleRemoveFromStack(artist: Artist) {
    try {
        const response = await axios.post(
            `/api/lineups/${props.id}/stacks/artists/${artist.id}/remove`,
        );
        lineupData.value = response.data.lineup;
    } catch (e) {
        console.error('Failed to remove from stack', e);
    }
}

async function handleDissolveStack(stackId: string) {
    try {
        const response = await axios.post(
            `/api/lineups/${props.id}/stacks/${stackId}/dissolve`,
        );
        lineupData.value = response.data.lineup;
    } catch (e) {
        console.error('Failed to dissolve stack', e);
    }
}

function clearSelection() {
    selectedArtistIds.value = [];
}

function toggleStack() {
    stackMode.value = !stackMode.value;
    if (stackMode.value) {
        compareMode.value = false;
        selectedArtistIds.value = [];
    }
    if (!stackMode.value) {
        isAddingAlternativesTo.value = null;
        stackingTier.value = null;
    }
}

function toggleCompare() {
    compareMode.value = !compareMode.value;
    if (compareMode.value) {
        stackMode.value = false;
        isAddingAlternativesTo.value = null;
        stackingTier.value = null;
    }
    if (!compareMode.value) selectedArtistIds.value = [];
}

function exitCompareMode() {
    compareMode.value = false;
    selectedArtistIds.value = [];
}

async function openAddModal(artist: SearchResultArtist) {
    if (addingArtistId.value) return;

    artistToAdd.value = artist;
    isAddModalOpen.value = true;
    suggestedTier.value = null; // Reset while loading

    // Fetch suggested tier from backend
    try {
        const score = artist.score || artist.spotify_popularity || 0;
        const response = await axios.get(
            `/api/lineups/${props.id}/suggest-tier`,
            {
                params: {
                    artist_id: artist.id,
                    score: score,
                },
            },
        );
        suggestedTier.value = response.data.suggested_tier;
    } catch (e) {
        console.error('Failed to get tier suggestion', e);
    }
}

async function confirmAddArtist(payload: {
    artist: SearchResultArtist;
    tier: TierType;
}) {
    const { artist, tier } = payload;

    isAddingToLineup.value = true;
    addingArtistId.value = artist.id || artist.spotify_id; // To show spinner in search list if visible

    try {
        let artistId = artist.id;

        if (!artistId) {
            const selectResponse = await axios.post('/api/artists/select', {
                spotify_id: artist.spotify_id,
            });
            artistId = selectResponse.data.data.id;
        }

        const response = await axios.post(`/api/lineups/${props.id}/artists`, {
            artist_id: artistId,
            tier: tier,
        });

        // If the backend returns the updated lineup, update local state
        if (response.data.lineup) {
            lineupData.value = response.data.lineup;
        } else {
            // Fallback for safety
            router.reload({ only: ['lineup'] });
        }

        isAddModalOpen.value = false;
    } catch (e) {
        console.error('Failed to add artist', e);
    } finally {
        isAddingToLineup.value = false;
        addingArtistId.value = null;
    }
}

function isArtistInLineup(artist: SearchResultArtist) {
    return allArtists.value.some((a) => {
        if (artist.id && a.id === artist.id) return true;
        if (artist.spotify_id && a.spotify_id === artist.spotify_id)
            return true;
        return false;
    });
}

const breadcrumbs = computed(() =>
    lineupBreadcrumbs(
        lineupData.value?.name ?? trans('lineups.show_page_title'),
        props.id,
    ),
);
</script>

<template>
    <Head
        :title="`${lineupData?.name ?? $t('lineups.show_page_title')} - Artist-Tree`"
    />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div v-if="lineupData" ref="pageContentRef" class="space-y-6">
            <!-- Mode Banners -->
            <StackModeBanner
                :show="stackMode"
                :primary-artist-name="stackingPrimaryArtist?.name"
                @close="
                    stackMode = false;
                    isAddingAlternativesTo = null;
                    stackingTier = null;
                "
            />
            <CompareModeBanner
                :show="compareMode"
                :selected-artists="selectedArtists"
                @close="exitCompareMode"
                @clear="clearSelection"
                @submit="isCompareModalOpen = true"
            />

            <!-- Lineup Header Card -->
            <Card class="relative py-0">
                <CardContent class="p-6">
                    <!-- Actions -->
                    <div class="absolute top-6 right-6 z-10">
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                >
                                    <Settings class="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    @click="isEditModalOpen = true"
                                >
                                    <Pencil class="mr-2 h-4 w-4" />
                                    {{ $t('common.action_edit') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem disabled>
                                    <Download class="mr-2 h-4 w-4" />
                                    {{ $t('lineups.show_export_button') }}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    class="text-destructive"
                                    @click="isDeleteModalOpen = true"
                                >
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    {{ $t('common.action_delete') }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <div
                        class="flex flex-col justify-between gap-6 md:flex-row"
                    >
                        <!-- Info -->
                        <div class="flex-1 pr-10 md:pr-0">
                            <h1 class="text-3xl font-bold">
                                {{ lineupData.name }}
                            </h1>
                            <p
                                class="mt-1 min-h-[1.25rem] text-sm text-muted-foreground"
                            >
                                {{ lineupData.description }}
                            </p>

                            <div class="mt-6 flex flex-wrap items-center gap-8">
                                <!-- Artist Count -->
                                <div class="flex items-center gap-3">
                                    <div class="rounded-full bg-muted p-2.5">
                                        <Users
                                            class="h-5 w-5 text-muted-foreground"
                                        />
                                    </div>
                                    <div>
                                        <p
                                            class="mb-0.5 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                        >
                                            {{
                                                $t('lineups.show_stats_artists')
                                            }}
                                        </p>
                                        <p
                                            class="text-xl leading-none font-bold"
                                        >
                                            {{ lineupData.stats.artist_count }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Avg Score -->
                                <div class="flex items-center gap-3">
                                    <ScoreBadge
                                        v-if="lineupData.stats.avg_score"
                                        :score="
                                            Math.round(
                                                lineupData.stats.avg_score,
                                            )
                                        "
                                        size="lg"
                                    />
                                    <div
                                        v-else
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-muted"
                                    >
                                        <span class="font-bold">-</span>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                        >
                                            {{
                                                $t(
                                                    'lineups.show_stats_avg_score',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Updated Timestamp -->
                    <div
                        class="mt-8 text-xs text-muted-foreground md:absolute md:right-6 md:bottom-6 md:mt-0"
                    >
                        {{ $t('lineups.card_updated') }}
                        {{ lineupData.updated_at_human }}
                    </div>
                </CardContent>
            </Card>

            <!-- Lineup Content -->
            <div class="space-y-6">
                <!-- Artist Search Component -->
                <div
                    ref="searchContainerRef"
                    class="relative z-30"
                    :style="{ height: isStuck ? `${searchHeight}px` : 'auto' }"
                >
                    <div
                        ref="searchComponentRef"
                        :class="[
                            'transition-all duration-300 ease-in-out',
                            isStuck
                                ? 'fixed z-50 flex h-16 items-center border-b-2 border-primary/10 bg-background/95 px-6 shadow-md backdrop-blur-md'
                                : 'relative w-full',
                        ]"
                        :style="
                            isStuck
                                ? {
                                      top: `${stickyTopOffset}px`,
                                      left: `${pageLeft}px`,
                                      width: `${pageWidth}px`,
                                  }
                                : {}
                        "
                    >
                        <div
                            :class="[
                                'transition-all duration-300',
                                isStuck
                                    ? 'flex h-full w-full items-center'
                                    : 'w-full',
                            ]"
                        >
                            <ArtistSearch
                                class="w-full"
                                :adding-artist-id="addingArtistId"
                                :is-artist-in-lineup="isArtistInLineup"
                                :stack-mode="stackMode"
                                :compare-mode="compareMode"
                                :class="[
                                    isStuck
                                        ? 'border-none bg-transparent p-0 shadow-none'
                                        : '',
                                ]"
                                @add-artist="openAddModal"
                                @toggle-stack="toggleStack"
                                @toggle-compare="toggleCompare"
                            />
                        </div>
                    </div>
                </div>

                <!-- Tier Sections -->
                <div class="space-y-4">
                    <TierSection
                        v-for="tier in tierOrder"
                        :key="tier"
                        :tier="tier"
                        :artists="getArtistsByTier(tier)"
                        :compare-mode="compareMode"
                        :stack-mode="stackMode"
                        :selected-artist-ids="selectedArtistIds"
                        :is-adding-alternatives-to="isAddingAlternativesTo"
                        :stacking-tier="stackingTier"
                        @select-artist="handleArtistSelect"
                        @view-artist="handleArtistView"
                        @remove-artist="handleArtistRemove"
                        @start-stack="handleStartStack"
                        @promote-artist="handlePromoteArtist"
                        @remove-from-stack="handleRemoveFromStack"
                        @dissolve-stack="handleDissolveStack"
                        @deselect-stack="
                            isAddingAlternativesTo = null;
                            stackingTier = null;
                        "
                    />
                </div>
            </div>
        </div>

        <!-- Not Found -->
        <div v-else class="py-12 text-center">
            <p class="text-muted-foreground">
                {{ $t('lineups.show_not_found') }}
            </p>
            <Button class="mt-4" @click="router.visit('/lineups')">
                {{ $t('lineups.show_back_button') }}
            </Button>
        </div>

        <AddToLineupModal
            v-if="lineupData"
            v-model:open="isAddModalOpen"
            :artist="artistToAdd"
            :lineup-name="lineupData.name"
            :suggested-tier="suggestedTier"
            :is-adding="isAddingToLineup"
            @add="confirmAddArtist"
        />

        <RemoveArtistFromLineupModal
            v-if="lineupData"
            v-model:open="isRemoveArtistModalOpen"
            :lineup-id="lineupData.id"
            :artist="artistToRemove"
            @updated="(data) => (lineupData = data)"
        />

        <EditLineupModal
            v-if="lineupData"
            v-model:open="isEditModalOpen"
            :lineup="lineupData"
        />

        <DeleteLineupModal
            v-if="lineupData"
            v-model:open="isDeleteModalOpen"
            :lineup="lineupData"
        />

        <CompareModal
            v-model:open="isCompareModalOpen"
            :artists="selectedArtists"
        />
    </MainLayout>
</template>
