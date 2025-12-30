<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Dialog, DialogContent, DialogTitle, DialogClose } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Check, Loader2, X } from 'lucide-vue-next';
import { TierType } from '@/data/types';
import ScoreBadge from '@/components/score/ScoreBadge.vue';
import { tierOrder } from '@/data/constants';

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
watch(() => props.open, (isOpen) => {
    if (isOpen) {
        selectedTier.value = props.suggestedTier;
    }
});

watch(() => props.suggestedTier, (newTier) => {
    if (props.open) {
        selectedTier.value = newTier;
    }
});

function handleAdd() {
    if (selectedTier.value && props.artist) {
        emit('add', {
            artist: props.artist,
            tier: selectedTier.value
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
        <DialogContent class="sm:max-w-md p-0 gap-0 overflow-hidden" :show-close-button="false">
            <div class="p-6 pb-2">
                <div class="flex items-center justify-between mb-4">
                     <DialogTitle class="text-xl font-bold">Select Tier</DialogTitle>
                     <DialogClose class="rounded-md p-2 hover:bg-muted transition-colors opacity-70 hover:opacity-100">
                        <X class="w-4 h-4" />
                     </DialogClose>
                </div>

                <!-- Artist Card -->
                <div v-if="artist" class="flex items-center gap-4 bg-muted/40 p-3 rounded-xl border mb-6">
                    <div class="h-12 w-12 rounded-lg bg-red-500 flex items-center justify-center text-white font-bold text-lg overflow-hidden shrink-0">
                        <img v-if="artist.image_url" :src="artist.image_url" :alt="artist.name" class="h-full w-full object-cover" />
                        <span v-else>{{ artist.name.substring(0, 2).toUpperCase() }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-lg truncate leading-tight mb-1">{{ artist.name }}</h3>
                        <ScoreBadge :score="displayScore" size="sm" />
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wider font-medium">Adding to</p>
                        <p class="text-sm font-medium truncate max-w-[120px]">{{ lineupName }}</p>
                    </div>
                </div>

                <!-- Tier Selection -->
                <div class="space-y-3">
                    <div 
                        v-for="tier in tierOrder" 
                        :key="tier"
                        class="relative"
                    >
                        <label 
                            class="flex items-center justify-between p-4 rounded-xl border-2 cursor-pointer transition-all duration-200"
                            :class="[
                                selectedTier === tier 
                                    ? 'border-[#EE6055] bg-[#EE6055]/5' 
                                    : 'border-muted hover:border-muted-foreground/30 bg-card'
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <div 
                                    class="w-5 h-5 rounded-full border flex items-center justify-center transition-colors"
                                    :class="selectedTier === tier ? 'border-[#EE6055]' : 'border-muted-foreground/30'"
                                >
                                    <div v-if="selectedTier === tier" class="w-2.5 h-2.5 rounded-full bg-[#EE6055]" />
                                </div>
                                <span class="font-medium">{{ tierLabels[tier] }}</span>
                                
                                <Badge 
                                    v-if="suggestedTier === tier" 
                                    variant="secondary" 
                                    class="bg-orange-100 text-orange-700 hover:bg-orange-100 border-0 flex items-center gap-1 text-[10px] h-5 px-1.5"
                                >
                                    <span class="text-[8px]">✨</span> Suggested
                                </Badge>
                            </div>

                            <Check 
                                v-if="selectedTier === tier" 
                                class="w-5 h-5 text-[#EE6055]" 
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
                    class="w-full h-12 text-base font-semibold rounded-xl bg-[#EE6055] hover:bg-[#D54B41] text-white border-none shadow-lg shadow-[#EE6055]/20 transition-all active:scale-[0.98]" 
                    :disabled="!selectedTier || isAdding"
                    @click="handleAdd"
                >
                    <Loader2 v-if="isAdding" class="w-5 h-5 animate-spin mr-2" />
                    {{ isAdding ? 'Adding...' : 'Add to Lineup' }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
