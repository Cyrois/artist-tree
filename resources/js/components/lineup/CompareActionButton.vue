<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { CircleMinus, CirclePlus } from 'lucide-vue-next';

interface Props {
    isSelected: boolean;
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
                    class="h-8 w-8 text-[hsl(var(--compare-coral))] hover:bg-[hsl(var(--compare-coral))]/10 hover:text-[hsl(var(--compare-coral))]"
                    @click.stop="emit('click')"
                >
                    <CircleMinus v-if="isSelected" class="h-4 w-4" />
                    <CirclePlus v-else class="h-4 w-4" />
                    <span class="sr-only">{{
                        isSelected
                            ? $t('lineups.show_compare_remove_from')
                            : $t('lineups.show_compare_add_to')
                    }}</span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                <p>
                    {{
                        isSelected
                            ? $t('lineups.show_compare_remove_from')
                            : $t('lineups.show_compare_add_to')
                    }}
                </p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
