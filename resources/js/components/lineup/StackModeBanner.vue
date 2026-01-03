<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Layers } from 'lucide-vue-next';

interface Props {
    show: boolean;
    primaryArtistName?: string | null;
}

defineProps<Props>();

const emit = defineEmits<{
    close: [];
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
            class="fixed top-0 right-0 left-0 z-[100] flex h-16 items-center justify-between border-b-2 border-[hsl(var(--stack-purple))] bg-[hsl(var(--stack-purple-bg))] px-6 shadow-xl transition-all duration-300"
        >
            <div class="flex items-center gap-3">
                <div
                    class="rounded-full bg-[hsl(var(--stack-purple))]/10 p-2 text-[hsl(var(--stack-purple))]"
                >
                    <Layers class="h-5 w-5" />
                </div>
                <div>
                    <p class="text-sm font-bold text-foreground">
                        <template v-if="primaryArtistName">
                            {{
                                $t('lineups.show_stack_mode_adding_to', {
                                    name: primaryArtistName,
                                })
                            }}
                        </template>
                        <template v-else>
                            {{ $t('lineups.show_stack_mode_description') }}
                        </template>
                    </p>
                    <p class="text-xs font-medium text-muted-foreground">
                        {{ $t('lineups.show_stack_mode_instruction') }}
                    </p>
                </div>
            </div>
            <Button
                variant="outline"
                size="sm"
                class="border-[hsl(var(--stack-purple))]/30 font-semibold text-[hsl(var(--stack-purple))] hover:bg-[hsl(var(--stack-purple))]/10"
                @click="emit('close')"
            >
                {{ $t('lineups.show_stack_mode_done') }}
            </Button>
        </div>
    </Transition>
</template>
