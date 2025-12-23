<script setup lang="ts">
import { cn } from '@/lib/utils';
import { computed } from 'vue';

interface Props {
    score: number;
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'sm',
});

const scoreStyle = computed(() => {
    if (props.score >= 85) {
        return {
            bg: 'bg-[hsl(var(--score-high-bg))]',
            text: 'text-[hsl(var(--score-high))]',
        };
    }
    if (props.score >= 70) {
        return {
            bg: 'bg-[hsl(var(--score-medium-bg))]',
            text: 'text-[hsl(var(--score-medium))]',
        };
    }
    if (props.score >= 55) {
        return {
            bg: 'bg-[hsl(var(--score-low-bg))]',
            text: 'text-[hsl(var(--score-low))]',
        };
    }
    return {
        bg: 'bg-[hsl(var(--score-critical-bg))]',
        text: 'text-[hsl(var(--score-critical))]',
    };
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'lg':
            return 'px-4 py-2 text-xl';
        case 'md':
            return 'px-3 py-1.5 text-sm';
        case 'sm':
        default:
            return 'px-2.5 py-1 text-xs';
    }
});
</script>

<template>
    <div
        :class="cn('inline-flex items-center justify-center rounded-full font-bold', scoreStyle.bg, scoreStyle.text, sizeClasses)"
        data-slot="score-badge"
    >
        {{ score }}
    </div>
</template>
