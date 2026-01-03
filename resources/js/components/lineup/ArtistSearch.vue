<script setup lang="ts">
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    Check,
    ChevronRight,
    ExternalLink,
    Layers,
    Loader2,
    Scale,
    Search,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    addingArtistId: string | number | null;
    isArtistInLineup: (artist: SearchResultArtist) => boolean;
    stackMode: boolean;
    compareMode: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    'add-artist': [artist: SearchResultArtist];
    'toggle-stack': [];
    'toggle-compare': [];
}>();

// Search State
const searchQuery = ref('');
const isSearchExpanded = ref(false);
const searchResults = ref<SearchResultArtist[]>([]);
const isSearching = ref(false);

const displayedResults = computed(() => searchResults.value.slice(0, 3));

// Search Logic
const performSearch = useDebounceFn(async (query: string) => {
    if (!query || query.length < 2) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const response = await fetch(
            `/api/artists/search?q=${encodeURIComponent(query)}`,
            {
                headers: { Accept: 'application/json' },
            },
        );
        const data = await response.json();
        searchResults.value = data.data || [];
    } catch (e) {
        console.error('Search failed', e);
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
}, 300);

watch(searchQuery, (newVal) => {
    if (newVal.length >= 2) isSearching.value = true;
    performSearch(newVal);
});

function expandSearch() {
    isSearchExpanded.value = true;
}

function closeSearch() {
    isSearchExpanded.value = false;
    searchQuery.value = '';
    searchResults.value = [];
}

function handleViewAllResults() {
    router.visit(`/search?q=${encodeURIComponent(searchQuery.value)}`);
}

function navigateToArtist(artist: SearchResultArtist) {
    if (artist.id) {
        router.visit(`/artist/${artist.id}`);
    } else {
        router.visit(`/search?q=${encodeURIComponent(artist.name)}`);
    }
}
</script>

<template>
    <Card
        class="relative gap-0 p-1 transition-all duration-300 ease-in-out"
        :class="{
            'overflow-visible': isSearchExpanded,
            'overflow-hidden': !isSearchExpanded,
            'rounded-b-none': isSearchExpanded && searchQuery.length >= 2,
        }"
    >
        <div class="flex items-center p-1">
            <!-- Search Section -->
            <div
                class="relative flex-1 transition-all duration-300 ease-in-out"
            >
                <Search
                    class="absolute top-1/2 left-3 z-10 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="
                        isSearchExpanded
                            ? $t('dashboard.search_placeholder')
                            : $t('lineups.show_search_placeholder')
                    "
                    class="h-10 border-none pl-9 shadow-none transition-all focus-visible:ring-0"
                    :class="isSearchExpanded ? 'bg-transparent' : 'bg-muted/50'"
                    @focus="expandSearch"
                />
            </div>

            <!-- Actions Section & Close Button Transition -->
            <div class="flex items-center overflow-hidden">
                <Transition
                    enter-active-class="transition-all duration-300 ease-in-out"
                    leave-active-class="transition-all duration-300 ease-in-out"
                    enter-from-class="max-w-0 opacity-0"
                    enter-to-class="max-w-[300px] opacity-100"
                    leave-from-class="max-w-[300px] opacity-100"
                    leave-to-class="max-w-0 opacity-0"
                >
                    <div
                        v-if="!isSearchExpanded"
                        class="flex shrink-0 items-center overflow-hidden whitespace-nowrap"
                    >
                        <div class="mx-2 h-8 w-[1px] bg-border" />
                        <div class="mr-2 flex hidden gap-2 sm:flex">
                            <Button
                                variant="outline"
                                :class="
                                    stackMode
                                        ? 'border-[hsl(var(--stack-purple))] bg-[hsl(var(--stack-purple))] text-white hover:bg-[hsl(var(--stack-purple))]/90 hover:text-white'
                                        : 'hover:bg-muted'
                                "
                                class="h-9 gap-2 transition-all"
                                @click="emit('toggle-stack')"
                            >
                                <Layers class="h-4 w-4" />
                                {{
                                    stackMode
                                        ? $t('lineups.show_stack_exit')
                                        : $t('lineups.show_stack_button')
                                }}
                            </Button>
                            <Button
                                variant="outline"
                                :class="{
                                    'border-[hsl(var(--compare-coral))] bg-[hsl(var(--compare-coral-bg))]':
                                        compareMode,
                                }"
                                class="h-9 gap-2"
                                @click="emit('toggle-compare')"
                            >
                                <Scale class="h-4 w-4" />
                                {{ $t('lineups.show_compare_button') }}
                            </Button>
                        </div>
                    </div>
                </Transition>

                <Transition
                    enter-active-class="transition-all duration-300 ease-in-out"
                    leave-active-class="transition-all duration-300 ease-in-out"
                    enter-from-class="max-w-0 opacity-0"
                    enter-to-class="max-w-[50px] opacity-100"
                    leave-from-class="max-w-[50px] opacity-100"
                    leave-to-class="max-w-0 opacity-0"
                >
                    <div v-if="isSearchExpanded" class="overflow-hidden">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="ml-2 h-9 w-9 shrink-0"
                            @click="closeSearch"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                </Transition>
            </div>
        </div>

        <!-- Search Dropdown (Absolute) -->
        <div
            v-if="isSearchExpanded && searchQuery.length >= 2"
            class="absolute top-full right-[-1px] left-[-1px] z-50 mt-0 overflow-hidden rounded-b-lg border bg-background shadow-xl"
        >
            <div
                v-if="isSearching"
                class="flex items-center justify-center py-8"
            >
                <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
            </div>

            <div
                v-else-if="searchResults.length === 0"
                class="py-8 text-center text-muted-foreground"
            >
                {{
                    $t('lineups.show_search_no_results', { query: searchQuery })
                }}
            </div>

            <div v-else>
                <div class="divide-y">
                    <div
                        v-for="artist in displayedResults"
                        :key="artist.spotify_id"
                        class="flex cursor-pointer items-center justify-between p-3 transition-colors hover:bg-muted/50"
                        @click="
                            !isArtistInLineup(artist) &&
                            emit('add-artist', artist)
                        "
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <img
                                :src="artist.image_url || '/placeholder.png'"
                                :alt="artist.name"
                                class="h-10 w-10 flex-shrink-0 rounded-md bg-muted object-cover"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ artist.name }}
                                </p>
                                <div class="mt-0.5 flex flex-wrap gap-1">
                                    <Badge
                                        v-for="genre in artist.genres.slice(
                                            0,
                                            2,
                                        )"
                                        :key="genre"
                                        variant="secondary"
                                        class="h-4 px-1.5 py-0 text-[10px]"
                                    >
                                        {{ genre }}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <ScoreBadge
                                :score="
                                    artist.score ||
                                    artist.spotify_popularity ||
                                    0
                                "
                            />

                            <div
                                v-if="
                                    addingArtistId ===
                                    (artist.id || artist.spotify_id)
                                "
                                class="flex h-8 w-8 items-center justify-center"
                            >
                                <Loader2 class="h-4 w-4 animate-spin" />
                            </div>
                            <div
                                v-else-if="isArtistInLineup(artist)"
                                class="flex h-8 w-8 items-center justify-center"
                            >
                                <Check class="h-4 w-4 text-green-500" />
                            </div>

                            <Button
                                size="sm"
                                variant="ghost"
                                class="h-8 w-8 p-0"
                                @click.stop="navigateToArtist(artist)"
                            >
                                <ExternalLink class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- View All Link -->
                <div class="border-t bg-muted/30 p-2 text-center">
                    <button
                        class="flex w-full cursor-pointer items-center justify-center gap-1 py-1 text-xs font-medium text-primary hover:underline"
                        @click="handleViewAllResults"
                    >
                        {{
                            $t('lineups.show_search_view_all', {
                                query: searchQuery,
                            })
                        }}
                        <ChevronRight class="h-3 w-3" />
                    </button>
                </div>
            </div>
        </div>
    </Card>
</template>
