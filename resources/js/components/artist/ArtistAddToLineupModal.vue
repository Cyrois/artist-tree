<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { tierOrder } from '@/data/constants';
import { TierType } from '@/data/types';
import axios from 'axios';
import { ArrowLeft, Check, ChevronRight, Loader2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Lineup {
    id: number;
    name: string;
    artists_count: number;
}

interface Artist {
    id: number;
    name: string;
    image_url: string | null;
    score: number | null;
    metrics?: {
        spotify_popularity: number | null;
    } | null;
}

interface Props {
    open: boolean;
    artist: Artist | null;
    lineups: Lineup[];
}

const props = defineProps<Props>();
const emit = defineEmits(['update:open', 'submit']);

const step = ref(1);
const selectedLineup = ref<Lineup | null>(null);
const selectedTier = ref<TierType | null>(null);
const isSubmitting = ref(false);
const suggestedTier = ref<TierType | null>(null);
const isLoadingSuggestion = ref(false);

// Reset state when modal opens
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            isSubmitting.value = false;
            suggestedTier.value = null;
            selectedTier.value = null;

            if (props.lineups.length === 1) {
                handleLineupSelect(props.lineups[0]);
            } else {
                step.value = 1;
                selectedLineup.value = null;
            }
        }
    },
);

const displayScore = computed(() => {
    if (!props.artist) return 0;
    return props.artist.score || props.artist.metrics?.spotify_popularity || 0;
});

async function handleLineupSelect(lineup: Lineup) {
    selectedLineup.value = lineup;
    step.value = 2;
    await fetchSuggestedTier(lineup.id);
}

async function fetchSuggestedTier(lineupId: number) {
    if (!props.artist) return;

    isLoadingSuggestion.value = true;
    try {
        const response = await axios.get(`/api/lineups/${lineupId}/suggest-tier`, {
            params: { artist_id: props.artist.id },
        });
        suggestedTier.value = response.data.suggested_tier;
        // Auto-select suggested tier if not already selected
        if (!selectedTier.value && suggestedTier.value) {
            selectedTier.value = suggestedTier.value;
        }
    } catch (error) {
        console.error('Failed to fetch suggested tier:', error);
    } finally {
        isLoadingSuggestion.value = false;
    }
}

function handleBack() {
    step.value = 1;
    selectedLineup.value = null;
    selectedTier.value = null;
    suggestedTier.value = null;
}

function handleSubmit() {
    if (!selectedLineup.value || !selectedTier.value || !props.artist) return;

    isSubmitting.value = true;
    emit('submit', {
        lineupId: selectedLineup.value.id,
        artistId: props.artist.id,
        tier: selectedTier.value,
    });
    // The parent component is responsible for closing the modal after success
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent
            class="gap-0 overflow-hidden p-0 sm:max-w-lg"
            :show-close-button="false"
        >
            <div class="p-6">
                <!-- Header -->
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="step === 2 && lineups.length > 1"
                            variant="ghost"
                            size="icon"
                            class="-ml-2 h-8 w-8"
                            @click="handleBack"
                        >
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                        <DialogTitle class="text-xl font-bold">
                            {{
                                step === 1
                                    ? $t('lineups.select_lineup_title')
                                    : $t('lineups.add_modal_title')
                            }}
                        </DialogTitle>
                    </div>
                    <DialogClose
                        class="rounded-md p-2 opacity-70 transition-colors hover:bg-muted hover:opacity-100"
                    >
                        <X class="h-4 w-4" />
                    </DialogClose>
                </div>

                <!-- Artist Card -->
                <div
                    v-if="artist"
                    class="mb-6 flex items-center gap-3 rounded-xl border bg-muted/40 p-3"
                >
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[#EE6055] text-lg font-bold text-white"
                    >
                        <img
                            v-if="artist.image_url"
                            :src="artist.image_url"
                            :alt="artist.name"
                            class="h-full w-full object-cover"
                        />
                        <span v-else>{{
                            artist.name.substring(0, 2).toUpperCase()
                        }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3
                            class="mb-1 text-lg leading-tight font-bold break-words"
                            :title="artist.name"
                        >
                            {{ artist.name }}
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            {{ $t('artists.score_label') }}:
                            {{ Math.round(displayScore) }}
                        </p>
                    </div>
                    <div
                        v-if="step === 2 && selectedLineup"
                        class="shrink-0 text-right"
                    >
                        <p
                            class="text-[10px] font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            {{ $t('lineups.add_modal_adding_to') }}
                        </p>
                        <p class="max-w-[120px] truncate text-sm font-medium">
                            {{ selectedLineup.name }}
                        </p>
                    </div>
                </div>

                <!-- Step 1: Lineup List -->
                <div v-if="step === 1" class="space-y-3">
                    <button
                        v-for="lineup in lineups"
                        :key="lineup.id"
                        @click="handleLineupSelect(lineup)"
                        class="flex w-full items-center justify-between rounded-xl border-2 border-muted bg-card p-4 transition-all duration-200 hover:border-muted-foreground/30 hover:bg-muted/10"
                    >
                        <div class="text-left">
                            <h4 class="font-medium text-foreground">
                                {{ lineup.name }}
                            </h4>
                            <p class="text-sm text-muted-foreground">
                                {{ lineup.artists_count }}
                                {{ $t('common.artists').toLowerCase() }}
                            </p>
                        </div>
                        <ChevronRight class="h-5 w-5 text-muted-foreground" />
                    </button>

                    <div
                        v-if="lineups.length === 0"
                        class="py-4 text-center text-muted-foreground"
                    >
                        <p>{{ $t('lineups.no_lineups_found') }}</p>
                    </div>
                </div>

                <!-- Step 2: Tier Selection -->
                <div v-else class="space-y-4">
                    <div
                        v-if="isLoadingSuggestion"
                        class="flex items-center justify-center py-2 text-sm text-muted-foreground"
                    >
                        <Loader2 class="mr-2 h-4 w-4 animate-spin" />
                        {{ $t('lineups.calculating_suggestion') }}
                    </div>
                    <div class="space-y-3">
                        <label
                            v-for="tier in tierOrder"
                            :key="tier"
                            class="flex cursor-pointer items-center justify-between rounded-xl border-2 p-4 transition-all duration-200"
                            :class="[
                                selectedTier === tier
                                    ? 'border-[#EE6055] bg-[#EE6055]/5'
                                    : 'border-muted bg-card hover:border-muted-foreground/30',
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-5 w-5 items-center justify-center rounded-full border transition-colors"
                                    :class="
                                        selectedTier === tier
                                            ? 'border-[#EE6055]'
                                            : 'border-muted-foreground/30'
                                    "
                                >
                                    <div
                                        v-if="selectedTier === tier"
                                        class="h-2.5 w-2.5 rounded-full bg-[#EE6055]"
                                    />
                                </div>
                                <span class="font-medium">{{
                                    $t('lineups.tier_' + tier)
                                }}</span>

                                <Badge
                                    v-if="suggestedTier === tier"
                                    variant="secondary"
                                    class="flex h-5 items-center gap-1 border-0 bg-orange-100 px-1.5 text-[10px] text-orange-700 hover:bg-orange-100"
                                >
                                    <span class="text-[8px]">✨</span>
                                    {{ $t('lineups.add_modal_suggested') }}
                                </Badge>
                            </div>

                            <div class="flex items-center gap-2">
                                <Check
                                    v-if="selectedTier === tier"
                                    class="h-5 w-5 text-[#EE6055]"
                                />
                            </div>

                            <input
                                type="radio"
                                :value="tier"
                                v-model="selectedTier"
                                class="sr-only"
                            />
                        </label>
                    </div>

                    <Button
                        class="h-12 w-full rounded-xl border-none bg-[#EE6055] text-base font-semibold text-white shadow-lg shadow-[#EE6055]/20 transition-all hover:bg-[#D54B41] active:scale-[0.98]"
                        :disabled="!selectedTier || isSubmitting"
                        @click="handleSubmit"
                    >
                        <Loader2
                            v-if="isSubmitting"
                            class="mr-2 h-5 w-5 animate-spin"
                        />
                        {{
                            isSubmitting
                                ? $t('lineups.add_modal_adding')
                                : $t('lineups.add_modal_submit')
                        }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
