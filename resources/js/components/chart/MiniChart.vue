<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    data: number[];
    height?: number;
    color?: string;
    showLabels?: boolean;
    labels?: string[];
}

const props = withDefaults(defineProps<Props>(), {
    height: 48,
    color: 'hsl(var(--chart-1))',
    showLabels: false,
    labels: () => [],
});

const maxValue = computed(() => Math.max(...props.data));
const minValue = computed(() => Math.min(...props.data));

const normalizedData = computed(() => {
    const range = maxValue.value - minValue.value;
    if (range === 0) return props.data.map(() => 50);
    return props.data.map((value) => ((value - minValue.value) / range) * 100);
});

const barWidth = computed(() => {
    const count = props.data.length;
    // Leave some gap between bars
    return `${Math.floor(100 / count) - 2}%`;
});
</script>

<template>
    <div class="flex flex-col gap-1" data-slot="mini-chart">
        <div
            class="flex items-end justify-between gap-1"
            :style="{ height: `${height}px` }"
        >
            <div
                v-for="(value, index) in normalizedData"
                :key="index"
                class="rounded-t transition-all duration-300"
                :style="{
                    height: `${Math.max(value, 5)}%`,
                    width: barWidth,
                    backgroundColor: color,
                    opacity: 0.6 + (value / 100) * 0.4,
                }"
            />
        </div>
        <div
            v-if="showLabels && labels.length > 0"
            class="flex justify-between text-xs text-muted-foreground"
        >
            <span v-for="(label, index) in labels" :key="index">{{
                label
            }}</span>
        </div>
    </div>
</template>
