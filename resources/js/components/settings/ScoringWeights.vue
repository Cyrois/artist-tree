<script setup lang="ts">
import WeightSlider from '@/components/settings/WeightSlider.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { metricPresets } from '@/data/constants';
import { trans } from 'laravel-vue-i18n';
import {
    AlertCircle,
    Check,
    Music,
    TrendingUp,
    Youtube,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

// Weights state
const weights = ref({
    spotifyListeners: 0.4,
    spotifyPopularity: 0.3,
    youtubeSubscribers: 0.3,
});

const totalWeight = computed(() => {
    return (
        weights.value.spotifyListeners +
        weights.value.spotifyPopularity +
        weights.value.youtubeSubscribers
    );
});

const isValidTotal = computed(() => Math.abs(totalWeight.value - 1) < 0.001);

// Apply preset
function applyPreset(presetKey: keyof typeof metricPresets) {
    const preset = metricPresets[presetKey];
    weights.value = { ...preset.weights };
}
</script>

<template>
    <div class="max-w-3xl space-y-6">
        <Card>
            <CardHeader>
                <CardTitle>{{
                    trans('settings.scoring_weights_title')
                }}</CardTitle>
                <CardDescription>
                    {{ trans('settings.scoring_weights_subtitle') }}
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Presets -->
                <div>
                    <p class="mb-3 text-sm font-medium">
                        {{ trans('settings.scoring_presets_title') }}
                    </p>
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
                        :label="trans('settings.scoring_spotify_listeners')"
                        :icon="Music"
                    />
                    <WeightSlider
                        v-model="weights.spotifyPopularity"
                        :label="trans('settings.scoring_spotify_popularity')"
                        :icon="TrendingUp"
                    />
                    <WeightSlider
                        v-model="weights.youtubeSubscribers"
                        :label="trans('settings.scoring_youtube_subscribers')"
                        :icon="Youtube"
                    />
                </div>

                <Separator />

                <!-- Total -->
                <div class="flex items-center justify-between">
                    <span class="font-medium">{{
                        trans('settings.scoring_total_weight')
                    }}</span>
                    <div class="flex items-center gap-2">
                        <span
                            :class="[
                                'text-lg font-bold',
                                isValidTotal
                                    ? 'text-[hsl(var(--score-high))]'
                                    : 'text-[hsl(var(--score-critical))]',
                            ]"
                        >
                            {{ Math.round(totalWeight * 100) }}%
                        </span>
                        <Check
                            v-if="isValidTotal"
                            class="h-5 w-5 text-[hsl(var(--score-high))]"
                        />
                    </div>
                </div>

                <!-- Validation Alert -->
                <Alert v-if="!isValidTotal" variant="destructive">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>
                        {{
                            trans('settings.scoring_weights_error', {
                                total: Math.round(totalWeight * 100),
                            })
                        }}
                    </AlertDescription>
                </Alert>

                <!-- Save Button -->
                <div class="flex justify-end">
                    <Button :disabled="!isValidTotal">
                        {{ trans('settings.scoring_save_button') }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
