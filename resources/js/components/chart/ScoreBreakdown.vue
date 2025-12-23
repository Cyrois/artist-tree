<script setup lang="ts">
import { cn } from '@/lib/utils';
import { computed } from 'vue';

interface MetricBreakdown {
    label: string;
    value: number;
    weight: number;
    contribution: number;
}

interface Props {
    metrics: MetricBreakdown[];
}

const props = defineProps<Props>();

const totalScore = computed(() =>
    props.metrics.reduce((sum, m) => sum + m.contribution, 0)
);
</script>

<template>
    <div class="space-y-3" data-slot="score-breakdown">
        <div v-for="metric in metrics" :key="metric.label" class="space-y-1">
            <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground">{{ metric.label }}</span>
                <span class="font-medium">
                    {{ metric.contribution.toFixed(1) }}
                    <span class="text-muted-foreground text-xs">({{ (metric.weight * 100).toFixed(0) }}%)</span>
                </span>
            </div>
            <div class="h-2 w-full rounded-full bg-muted overflow-hidden">
                <div
                    class="h-full rounded-full bg-primary transition-all duration-500"
                    :style="{ width: `${(metric.contribution / totalScore) * 100}%` }"
                />
            </div>
        </div>
        <div class="flex items-center justify-between pt-2 border-t">
            <span class="font-medium">Total Score</span>
            <span class="text-lg font-bold">{{ totalScore.toFixed(0) }}</span>
        </div>
    </div>
</template>
