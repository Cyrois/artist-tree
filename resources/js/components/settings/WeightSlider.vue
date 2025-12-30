<script setup lang="ts">
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
                <component :is="icon" class="h-4 w-4 text-muted-foreground" />
                <span class="text-sm font-medium">{{ label }}</span>
            </div>
            <span class="text-sm font-bold tabular-nums"
                >{{ percentage }}%</span
            >
        </div>

        <div class="relative">
            <input
                type="range"
                :value="modelValue"
                :min="min"
                :max="max"
                :step="step"
                class="h-2 w-full cursor-pointer appearance-none rounded-full bg-muted [&::-moz-range-thumb]:h-5 [&::-moz-range-thumb]:w-5 [&::-moz-range-thumb]:cursor-pointer [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:bg-primary [&::-webkit-slider-thumb]:h-5 [&::-webkit-slider-thumb]:w-5 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-background [&::-webkit-slider-thumb]:bg-primary [&::-webkit-slider-thumb]:shadow-md"
                @input="handleInput"
            />
            <div
                class="pointer-events-none absolute top-0 left-0 h-2 rounded-full bg-primary"
                :style="{ width: `${percentage}%` }"
            />
        </div>
    </div>
</template>
