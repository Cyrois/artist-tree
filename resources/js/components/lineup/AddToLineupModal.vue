<script setup lang="ts">
import ScoreBadge from '@/components/score/ScoreBadge.vue';
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
import { Check, Loader2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface SearchResultArtist {
    id: number | null;
    spotify_id: string;
    name: string;
    image_url: string | null;
    score: number;
    spotify_popularity?: number;
}

interface Props {
    open: boolean;
    artist: SearchResultArtist | null;
    lineupName: string;
    suggestedTier: TierType | null;
    isAdding: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:open', 'add']);

const selectedTier = ref<TierType | null>(null);

const tierLabels: Record<TierType, string> = {
    headliner: 'Headliner',
    sub_headliner: 'Sub-Headliner',
    mid_tier: 'Mid-Tier',
    undercard: 'Undercard',
};

// Reset selected tier when modal opens or suggested tier changes
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            selectedTier.value = props.suggestedTier;
        }
    },
);

watch(
    () => props.suggestedTier,
    (newTier) => {
        if (props.open) {
            selectedTier.value = newTier;
        }
    },
);

function handleAdd() {
    if (selectedTier.value && props.artist) {
        emit('add', {
            artist: props.artist,
            tier: selectedTier.value,
        });
    }
}

const displayScore = computed(() => {
    if (!props.artist) return 0;
    return props.artist.score || props.artist.spotify_popularity || 0;
});
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent
            class="gap-0 overflow-hidden p-0 sm:max-w-md"
            :show-close-button="false"
        >
            <div class="p-6 pb-2">
                <div class="mb-4 flex items-center justify-between">
                    <DialogTitle class="text-xl font-bold"
                        >Select Tier</DialogTitle
                    >
                    <DialogClose
                        class="rounded-md p-2 opacity-70 transition-colors hover:bg-muted hover:opacity-100"
                    >
                        <X class="h-4 w-4" />
                    </DialogClose>
                </div>

                <!-- Artist Card -->
                <div
                    v-if="artist"
                    class="mb-6 flex items-center gap-4 rounded-xl border bg-muted/40 p-3"
                >
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-red-500 text-lg font-bold text-white"
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
                            class="mb-1 truncate text-lg leading-tight font-bold"
                        >
                            {{ artist.name }}
                        </h3>
                        <ScoreBadge :score="displayScore" size="sm" />
                    </div>
                    <div class="shrink-0 text-right">
                        <p
                            class="text-[10px] font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            Adding to
                        </p>
                        <p class="max-w-[120px] truncate text-sm font-medium">
                            {{ lineupName }}
                        </p>
                    </div>
                </div>

                <!-- Tier Selection -->
                <div class="space-y-3">
                    <div v-for="tier in tierOrder" :key="tier" class="relative">
                        <label
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
                                    tierLabels[tier]
                                }}</span>

                                <Badge
                                    v-if="suggestedTier === tier"
                                    variant="secondary"
                                    class="flex h-5 items-center gap-1 border-0 bg-orange-100 px-1.5 text-[10px] text-orange-700 hover:bg-orange-100"
                                >
                                    <span class="text-[8px]">✨</span> Suggested
                                </Badge>
                            </div>

                            <Check
                                v-if="selectedTier === tier"
                                class="h-5 w-5 text-[#EE6055]"
                            />

                            <!-- Hidden Radio Input for accessibility -->
                            <input
                                type="radio"
                                :value="tier"
                                v-model="selectedTier"
                                class="sr-only"
                            />
                        </label>
                    </div>
                </div>
            </div>

            <div class="p-6 pt-2">
                <Button
                    class="h-12 w-full rounded-xl border-none bg-[#EE6055] text-base font-semibold text-white shadow-lg shadow-[#EE6055]/20 transition-all hover:bg-[#D54B41] active:scale-[0.98]"
                    :disabled="!selectedTier || isAdding"
                    @click="handleAdd"
                >
                    <Loader2
                        v-if="isAdding"
                        class="mr-2 h-5 w-5 animate-spin"
                    />
                    {{ isAdding ? 'Adding...' : 'Add to Lineup' }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
