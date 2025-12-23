<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Separator } from '@/components/ui/separator';
import WeightSlider from '@/components/mockup/settings/WeightSlider.vue';
import { metricPresets } from '@/data/constants';
import { Music, Youtube, TrendingUp, AlertCircle, Check } from 'lucide-vue-next';
import { ref, computed } from 'vue';

// Weights state
const weights = ref({
    spotifyListeners: 0.4,
    spotifyPopularity: 0.3,
    youtubeSubscribers: 0.3,
});

const totalWeight = computed(() => {
    return weights.value.spotifyListeners + weights.value.spotifyPopularity + weights.value.youtubeSubscribers;
});

const isValidTotal = computed(() => Math.abs(totalWeight.value - 1) < 0.001);

// Apply preset
function applyPreset(presetKey: keyof typeof metricPresets) {
    const preset = metricPresets[presetKey];
    weights.value = { ...preset.weights };
}
</script>

<template>
    <div class="space-y-6 max-w-3xl">
        <Card>
            <CardHeader>
                <CardTitle>Metric Weights</CardTitle>
                <CardDescription>
                    Adjust how much each metric contributes to the overall artist score.
                    Weights must add up to 100%.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Presets -->
                <div>
                    <p class="text-sm font-medium mb-3">Quick Presets</p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="(preset, key) in metricPresets"
                            :key="key"
                            variant="outline"
                            size="sm"
                            @click="applyPreset(key)"
                        >
                            {{ preset.label }}
                        </Button>
                    </div>
                </div>

                <Separator />

                <!-- Weight Sliders -->
                <div class="space-y-6">
                    <WeightSlider
                        v-model="weights.spotifyListeners"
                        label="Spotify Monthly Listeners"
                        :icon="Music"
                    />
                    <WeightSlider
                        v-model="weights.spotifyPopularity"
                        label="Spotify Popularity"
                        :icon="TrendingUp"
                    />
                    <WeightSlider
                        v-model="weights.youtubeSubscribers"
                        label="YouTube Subscribers"
                        :icon="Youtube"
                    />
                </div>

                <Separator />

                <!-- Total -->
                <div class="flex items-center justify-between">
                    <span class="font-medium">Total Weight</span>
                    <div class="flex items-center gap-2">
                        <span
                            :class="[
                                'text-lg font-bold',
                                isValidTotal ? 'text-[hsl(var(--score-high))]' : 'text-[hsl(var(--score-critical))]'
                            ]"
                        >
                            {{ Math.round(totalWeight * 100) }}%
                        </span>
                        <Check v-if="isValidTotal" class="w-5 h-5 text-[hsl(var(--score-high))]" />
                    </div>
                </div>

                <!-- Validation Alert -->
                <Alert v-if="!isValidTotal" variant="destructive">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>
                        Weights must add up to 100%. Current total: {{ Math.round(totalWeight * 100) }}%
                    </AlertDescription>
                </Alert>

                <!-- Save Button -->
                <div class="flex justify-end">
                    <Button :disabled="!isValidTotal">
                        Save Changes
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
