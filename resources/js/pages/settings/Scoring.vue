<script setup lang="ts">
import TeamMemberRow from '@/components/settings/TeamMemberRow.vue';
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
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { useAppearance } from '@/composables/useAppearance';
import { metricPresets } from '@/data/constants';
import type { TeamMember } from '@/data/types';
import MainLayout from '@/layouts/MainLayout.vue';
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import {
    AlertCircle,
    Check,
    Monitor,
    Moon,
    Music,
    Plus,
    Sun,
    TrendingUp,
    Youtube,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

// Tab state
const activeTab = ref<'weights' | 'team' | 'appearance'>('weights');

// Theme state
const { appearance, updateAppearance } = useAppearance();

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

// Mock team members
const teamMembers = ref<TeamMember[]>([
    { id: 1, name: 'Alex Johnson', email: 'alex@example.com', role: 'owner' },
    { id: 2, name: 'Sarah Chen', email: 'sarah@example.com', role: 'admin' },
    { id: 3, name: 'Mike Williams', email: 'mike@example.com', role: 'member' },
]);

const inviteEmail = ref('');

function inviteMember() {
    if (inviteEmail.value) {
        teamMembers.value.push({
            id: Date.now(),
            name: inviteEmail.value.split('@')[0],
            email: inviteEmail.value,
            role: 'member',
        });
        inviteEmail.value = '';
    }
}

function removeMember(id: number) {
    teamMembers.value = teamMembers.value.filter((m) => m.id !== id);
}

function updateMemberRole(id: number, role: 'admin' | 'member') {
    const member = teamMembers.value.find((m) => m.id === id);
    if (member) {
        member.role = role;
    }
}

const breadcrumbs = [
    { title: trans('common.breadcrumb_dashboard'), href: '/dashboard' },
    { title: trans('common.breadcrumb_settings'), href: '/settings/scoring' },
];
</script>

<template>
    <Head :title="$t('settings.scoring_page_title')" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-4xl space-y-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold">
                    {{ $t('settings.scoring_title') }}
                </h1>
                <p class="text-muted-foreground">
                    {{ $t('settings.scoring_subtitle') }}
                </p>
            </div>

            <!-- Tabs -->
            <div class="border-b">
                <div class="flex gap-6">
                    <button
                        :class="[
                            '-mb-px border-b-2 pb-3 text-sm font-medium transition-colors',
                            activeTab === 'weights'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeTab = 'weights'"
                    >
                        {{ $t('settings.scoring_tab_weights') }}
                    </button>
                    <button
                        :class="[
                            '-mb-px border-b-2 pb-3 text-sm font-medium transition-colors',
                            activeTab === 'team'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeTab = 'team'"
                    >
                        {{ $t('settings.scoring_tab_team') }}
                    </button>
                    <button
                        :class="[
                            '-mb-px border-b-2 pb-3 text-sm font-medium transition-colors',
                            activeTab === 'appearance'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeTab = 'appearance'"
                    >
                        {{ $t('settings.scoring_tab_appearance') }}
                    </button>
                </div>
            </div>

            <!-- Scoring Weights Tab -->
            <div v-if="activeTab === 'weights'" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{{
                            $t('settings.scoring_weights_title')
                        }}</CardTitle>
                        <CardDescription>
                            {{ $t('settings.scoring_weights_subtitle') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Presets -->
                        <div>
                            <p class="mb-3 text-sm font-medium">
                                {{ $t('settings.scoring_presets_title') }}
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
                                :label="
                                    $t('settings.scoring_spotify_listeners')
                                "
                                :icon="Music"
                            />
                            <WeightSlider
                                v-model="weights.spotifyPopularity"
                                :label="
                                    $t('settings.scoring_spotify_popularity')
                                "
                                :icon="TrendingUp"
                            />
                            <WeightSlider
                                v-model="weights.youtubeSubscribers"
                                :label="
                                    $t('settings.scoring_youtube_subscribers')
                                "
                                :icon="Youtube"
                            />
                        </div>

                        <Separator />

                        <!-- Total -->
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{
                                $t('settings.scoring_total_weight')
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
                                    $t('settings.scoring_weights_error', {
                                        total: Math.round(
                                            totalWeight * 100,
                                        ).toString(),
                                    })
                                }}
                            </AlertDescription>
                        </Alert>

                        <!-- Save Button -->
                        <div class="flex justify-end">
                            <Button :disabled="!isValidTotal">
                                {{ $t('settings.scoring_save_button') }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Team Members Tab -->
            <div v-if="activeTab === 'team'" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ $t('settings.team_title') }}</CardTitle>
                        <CardDescription>
                            {{ $t('settings.team_subtitle') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Member List -->
                        <div class="divide-y rounded-lg border">
                            <TeamMemberRow
                                v-for="member in teamMembers"
                                :key="member.id"
                                :member="member"
                                :can-edit="true"
                                @update-role="
                                    (role) => updateMemberRole(member.id, role)
                                "
                                @remove="removeMember(member.id)"
                            />
                        </div>

                        <Separator />

                        <!-- Invite Member -->
                        <div>
                            <p class="mb-3 text-sm font-medium">
                                {{ $t('settings.team_invite_title') }}
                            </p>
                            <div class="flex gap-2">
                                <Input
                                    v-model="inviteEmail"
                                    type="email"
                                    :placeholder="
                                        $t('settings.team_invite_placeholder')
                                    "
                                    class="flex-1"
                                    @keyup.enter="inviteMember"
                                />
                                <Button
                                    @click="inviteMember"
                                    :disabled="!inviteEmail"
                                >
                                    <Plus class="mr-2 h-4 w-4" />
                                    {{ $t('settings.team_invite_button') }}
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Appearance Tab -->
            <div v-if="activeTab === 'appearance'" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ $t('settings.theme_title') }}</CardTitle>
                        <CardDescription>
                            {{ $t('settings.theme_subtitle') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-3 gap-4">
                            <button
                                :class="[
                                    'flex flex-col items-center gap-3 rounded-lg border-2 p-4 transition-colors',
                                    appearance === 'light'
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:border-primary/50',
                                ]"
                                @click="updateAppearance('light')"
                            >
                                <div
                                    class="rounded-full bg-amber-100 p-3 dark:bg-amber-900/30"
                                >
                                    <Sun class="h-6 w-6 text-amber-600" />
                                </div>
                                <span class="font-medium">{{
                                    $t('settings.theme_light')
                                }}</span>
                                <span class="text-xs text-muted-foreground">{{
                                    $t('settings.theme_light_description')
                                }}</span>
                            </button>

                            <button
                                :class="[
                                    'flex flex-col items-center gap-3 rounded-lg border-2 p-4 transition-colors',
                                    appearance === 'dark'
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:border-primary/50',
                                ]"
                                @click="updateAppearance('dark')"
                            >
                                <div
                                    class="rounded-full bg-indigo-100 p-3 dark:bg-indigo-900/30"
                                >
                                    <Moon class="h-6 w-6 text-indigo-600" />
                                </div>
                                <span class="font-medium">{{
                                    $t('settings.theme_dark')
                                }}</span>
                                <span class="text-xs text-muted-foreground">{{
                                    $t('settings.theme_dark_description')
                                }}</span>
                            </button>

                            <button
                                :class="[
                                    'flex flex-col items-center gap-3 rounded-lg border-2 p-4 transition-colors',
                                    appearance === 'system'
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:border-primary/50',
                                ]"
                                @click="updateAppearance('system')"
                            >
                                <div
                                    class="rounded-full bg-gray-100 p-3 dark:bg-gray-800"
                                >
                                    <Monitor class="h-6 w-6 text-gray-600" />
                                </div>
                                <span class="font-medium">{{
                                    $t('settings.theme_system')
                                }}</span>
                                <span class="text-xs text-muted-foreground">{{
                                    $t('settings.theme_system_description')
                                }}</span>
                            </button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </MainLayout>
</template>
