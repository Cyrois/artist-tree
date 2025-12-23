<script setup lang="ts">
import { cn } from '@/lib/utils';
import { computed, type Component } from 'vue';

interface Props {
    label: string;
    icon: Component;
    modelValue: number;
    min?: number;
    max?: number;
    step?: number;
}

const props = withDefaults(defineProps<Props>(), {
    min: 0,
    max: 1,
    step: 0.05,
});

const emit = defineEmits<{
    'update:modelValue': [value: number];
}>();

const percentage = computed(() => Math.round(props.modelValue * 100));

function handleInput(event: Event) {
    const target = event.target as HTMLInputElement;
    emit('update:modelValue', parseFloat(target.value));
}
</script>

<template>
    <div class="space-y-3" data-slot="weight-slider">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <component :is="icon" class="w-4 h-4 text-muted-foreground" />
                <span class="font-medium text-sm">{{ label }}</span>
            </div>
            <span class="text-sm font-bold tabular-nums">{{ percentage }}%</span>
        </div>

        <div class="relative">
            <input
                type="range"
                :value="modelValue"
                :min="min"
                :max="max"
                :step="step"
                class="w-full h-2 bg-muted rounded-full appearance-none cursor-pointer
                       [&::-webkit-slider-thumb]:appearance-none
                       [&::-webkit-slider-thumb]:w-5
                       [&::-webkit-slider-thumb]:h-5
                       [&::-webkit-slider-thumb]:rounded-full
                       [&::-webkit-slider-thumb]:bg-primary
                       [&::-webkit-slider-thumb]:shadow-md
                       [&::-webkit-slider-thumb]:cursor-pointer
                       [&::-webkit-slider-thumb]:border-2
                       [&::-webkit-slider-thumb]:border-background
                       [&::-moz-range-thumb]:w-5
                       [&::-moz-range-thumb]:h-5
                       [&::-moz-range-thumb]:rounded-full
                       [&::-moz-range-thumb]:bg-primary
                       [&::-moz-range-thumb]:border-0
                       [&::-moz-range-thumb]:cursor-pointer"
                @input="handleInput"
            />
            <div
                class="absolute top-0 left-0 h-2 bg-primary rounded-full pointer-events-none"
                :style="{ width: `${percentage}%` }"
            />
        </div>
    </div>
</template>
