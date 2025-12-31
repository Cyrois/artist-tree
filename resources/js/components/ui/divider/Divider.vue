<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

interface Props {
    class?: HTMLAttributes['class'];
    vertical?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    vertical: false,
});
</script>

<template>
    <div
        :class="
            cn(
                'bg-border shrink-0',
                props.vertical ? 'h-full w-px' : 'h-px w-full',
                props.class,
            )
        "
        role="separator"
    />
</template>
