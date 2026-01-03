<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { ArrowUpCircle, CircleMinus } from 'lucide-vue-next';

const emit = defineEmits<{
    promote: [];
    remove: [];
}>();
</script>

<template>
    <div class="flex gap-1">
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8 text-[hsl(var(--stack-purple))] hover:bg-[hsl(var(--stack-purple))]/10"
                        @click.stop="emit('promote')"
                    >
                        <ArrowUpCircle class="h-4 w-4" />
                        <span class="sr-only">{{
                            $t('lineups.show_stack_promote')
                        }}</span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>
                    <p>{{ $t('lineups.show_stack_promote') }}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>

        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8 text-[hsl(var(--stack-purple))] hover:bg-[hsl(var(--stack-purple))]/10"
                        @click.stop="emit('remove')"
                    >
                        <CircleMinus class="h-4 w-4" />
                        <span class="sr-only">{{
                            $t('lineups.show_stack_remove_alt')
                        }}</span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>
                    <p>{{ $t('lineups.show_stack_remove_alt') }}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </div>
</template>
