<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Layers } from 'lucide-vue-next';

interface Props {
    isCurrentStack: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    click: [];
}>();
</script>

<template>
    <TooltipProvider>
        <Tooltip>
            <TooltipTrigger as-child>
                <Button 
                    variant="ghost" 
                    :size="isCurrentStack ? 'sm' : 'icon'" 
                    class="h-8 transition-all"
                    :class="[
                        isCurrentStack 
                            ? 'bg-[hsl(var(--stack-purple))] text-white opacity-100 px-3 gap-2 cursor-default hover:bg-[hsl(var(--stack-purple))] hover:text-white' 
                            : 'w-8 text-[hsl(var(--stack-purple))] hover:bg-[hsl(var(--stack-purple))]/10 hover:text-[hsl(var(--stack-purple))]'
                    ]"
                    @click.stop="emit('click')"
                >
                    <Layers class="h-4 w-4" />
                    <span v-if="isCurrentStack" class="text-xs font-bold whitespace-nowrap">
                        {{ $t('lineups.show_stack_current') }}
                    </span>
                    <span class="sr-only">{{ isCurrentStack ? $t('lineups.show_stack_primary') : $t('lineups.show_stack_choose') }}</span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                <p>{{ isCurrentStack ? $t('lineups.show_stack_primary') : $t('lineups.show_stack_choose') }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
