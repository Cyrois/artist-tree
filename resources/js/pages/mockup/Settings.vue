<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MockupLayout from '@/layouts/MockupLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Separator } from '@/components/ui/separator';
import WeightSlider from '@/components/mockup/settings/WeightSlider.vue';
import TeamMemberRow from '@/components/mockup/settings/TeamMemberRow.vue';
import { useAppearance } from '@/composables/useAppearance';
import { metricPresets } from '@/data/constants';
import type { TeamMember } from '@/data/types';
import { Music, Youtube, TrendingUp, Plus, AlertCircle, Check, Sun, Moon, Monitor } from 'lucide-vue-next';
import { ref, computed } from 'vue';

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
    return weights.value.spotifyListeners + weights.value.spotifyPopularity + weights.value.youtubeSubscribers;
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
    { title: 'Dashboard', href: '/mockup' },
    { title: 'Settings', href: '/mockup/settings' },
];
</script>

<template>
    <Head title="Settings - Artist-Tree" />
    <MockupLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 max-w-4xl">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold">Organization Settings</h1>
                <p class="text-muted-foreground">Manage your scoring weights and team members</p>
            </div>

            <!-- Tabs -->
            <div class="border-b">
                <div class="flex gap-6">
                    <button
                        :class="[
                            'pb-3 text-sm font-medium transition-colors border-b-2 -mb-px',
                            activeTab === 'weights' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeTab = 'weights'"
                    >
                        Scoring Weights
                    </button>
                    <button
                        :class="[
                            'pb-3 text-sm font-medium transition-colors border-b-2 -mb-px',
                            activeTab === 'team' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeTab = 'team'"
                    >
                        Team Members
                    </button>
                    <button
                        :class="[
                            'pb-3 text-sm font-medium transition-colors border-b-2 -mb-px',
                            activeTab === 'appearance' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeTab = 'appearance'"
                    >
                        Appearance
                    </button>
                </div>
            </div>

            <!-- Scoring Weights Tab -->
            <div v-if="activeTab === 'weights'" class="space-y-6">
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

            <!-- Team Members Tab -->
            <div v-if="activeTab === 'team'" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Team Members</CardTitle>
                        <CardDescription>
                            Manage who has access to your organization's lineups and settings.
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
                                @update-role="(role) => updateMemberRole(member.id, role)"
                                @remove="removeMember(member.id)"
                            />
                        </div>

                        <Separator />

                        <!-- Invite Member -->
                        <div>
                            <p class="text-sm font-medium mb-3">Invite New Member</p>
                            <div class="flex gap-2">
                                <Input
                                    v-model="inviteEmail"
                                    type="email"
                                    placeholder="Enter email address..."
                                    class="flex-1"
                                    @keyup.enter="inviteMember"
                                />
                                <Button @click="inviteMember" :disabled="!inviteEmail">
                                    <Plus class="w-4 h-4 mr-2" />
                                    Invite
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
                        <CardTitle>Theme</CardTitle>
                        <CardDescription>
                            Choose your preferred color scheme for the application.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-3 gap-4">
                            <button
                                :class="[
                                    'flex flex-col items-center gap-3 p-4 rounded-lg border-2 transition-colors',
                                    appearance === 'light'
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:border-primary/50'
                                ]"
                                @click="updateAppearance('light')"
                            >
                                <div class="p-3 rounded-full bg-amber-100 dark:bg-amber-900/30">
                                    <Sun class="w-6 h-6 text-amber-600" />
                                </div>
                                <span class="font-medium">Light</span>
                                <span class="text-xs text-muted-foreground">Bright and clean</span>
                            </button>

                            <button
                                :class="[
                                    'flex flex-col items-center gap-3 p-4 rounded-lg border-2 transition-colors',
                                    appearance === 'dark'
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:border-primary/50'
                                ]"
                                @click="updateAppearance('dark')"
                            >
                                <div class="p-3 rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                                    <Moon class="w-6 h-6 text-indigo-600" />
                                </div>
                                <span class="font-medium">Dark</span>
                                <span class="text-xs text-muted-foreground">Easy on the eyes</span>
                            </button>

                            <button
                                :class="[
                                    'flex flex-col items-center gap-3 p-4 rounded-lg border-2 transition-colors',
                                    appearance === 'system'
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:border-primary/50'
                                ]"
                                @click="updateAppearance('system')"
                            >
                                <div class="p-3 rounded-full bg-gray-100 dark:bg-gray-800">
                                    <Monitor class="w-6 h-6 text-gray-600" />
                                </div>
                                <span class="font-medium">System</span>
                                <span class="text-xs text-muted-foreground">Match device</span>
                            </button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </MockupLayout>
</template>
