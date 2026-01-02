<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Layers, Plus } from 'lucide-vue-next';

interface Props {
    isAddingToStack: boolean;
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
                    size="icon" 
                    class="h-8 w-8 text-[hsl(var(--stack-purple))] hover:bg-[hsl(var(--stack-purple))]/10 hover:text-[hsl(var(--stack-purple))]" 
                    @click.stop="emit('click')"
                >
                    <div v-if="isAddingToStack" class="relative flex items-center justify-center">
                        <Layers class="h-4 w-4" />
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="rounded-full bg-background p-[0.5px]">
                                <Plus class="h-2 w-2 stroke-[4]" />
                            </div>
                        </div>
                    </div>
                    <Layers v-else class="h-4 w-4" />
                    <span class="sr-only">{{ isAddingToStack ? $t('lineups.show_stack_add_to') : $t('lineups.show_stack_choose') }}</span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                <p>{{ isAddingToStack ? $t('lineups.show_stack_add_to') : $t('lineups.show_stack_choose') }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
