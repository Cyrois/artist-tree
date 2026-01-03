<script setup lang="ts">
import ArtistAvatar from '@/components/artist/ArtistAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Artist } from '@/data/types';
import { GitCompare } from 'lucide-vue-next';

interface Props {
    show: boolean;
    selectedArtists: Artist[];
}

defineProps<Props>();

const emit = defineEmits<{
    close: [];
    clear: [];
    submit: [];
}>();
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="-translate-y-full"
        enter-to-class="translate-y-0"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="translate-y-0"
        leave-to-class="-translate-y-full"
    >
        <div
            v-if="show"
            class="fixed top-0 right-0 left-0 z-[100] flex h-16 items-center justify-between border-b-2 border-[hsl(var(--compare-coral))] bg-[hsl(var(--compare-coral-bg))] px-6 shadow-xl transition-all duration-300"
        >
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-3">
                    <div
                        class="rounded-full bg-[hsl(var(--compare-coral))]/10 p-2 text-[hsl(var(--compare-coral))]"
                    >
                        <GitCompare class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-sm font-bold text-foreground">
                            {{ $t('lineups.show_compare_mode_description') }}
                        </p>
                        <p class="text-xs font-medium text-muted-foreground">
                            {{ $t('lineups.show_compare_mode_instruction') }}
                        </p>
                    </div>
                </div>

                <div class="hidden items-center gap-3 md:flex">
                    <div class="flex -space-x-2">
                        <ArtistAvatar
                            v-for="artist in selectedArtists"
                            :key="artist.id"
                            :artist="artist"
                            size="sm"
                            class="border-2 border-[hsl(var(--compare-coral-bg))]"
                        />
                    </div>
                    <Badge
                        v-if="selectedArtists.length > 0"
                        variant="outline"
                        class="border-[hsl(var(--compare-coral))]/30 bg-[hsl(var(--compare-coral))]/10 text-[hsl(var(--compare-coral))] font-semibold"
                    >
                        {{ selectedArtists.length }}/4
                    </Badge>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button
                    v-if="selectedArtists.length > 0"
                    variant="ghost"
                    size="sm"
                    class="font-semibold text-[hsl(var(--compare-coral))] hover:bg-[hsl(var(--compare-coral))]/10"
                    @click="emit('clear')"
                >
                    {{ $t('lineups.show_compare_clear') }}
                </Button>
                <div class="h-4 w-px bg-[hsl(var(--compare-coral))]/20" v-if="selectedArtists.length > 0"></div>
                <Button
                    variant="outline"
                    size="sm"
                    class="border-[hsl(var(--compare-coral))]/30 font-semibold text-[hsl(var(--compare-coral))] hover:bg-[hsl(var(--compare-coral))]/10"
                    @click="emit('close')"
                >
                    {{ $t('lineups.show_compare_mode_done') }}
                </Button>
                <Button
                    size="sm"
                    class="bg-[hsl(var(--compare-coral))] font-semibold text-white hover:bg-[hsl(var(--compare-coral))]/90"
                    :disabled="selectedArtists.length < 2"
                    @click="emit('submit')"
                >
                    {{ $t('lineups.show_compare_submit') }}
                </Button>
            </div>
        </div>
    </Transition>
</template>
