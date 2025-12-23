<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { User, Building2 } from 'lucide-vue-next';
import { ref } from 'vue';

// Import profile settings components
import ProfileSettings from '@/components/settings/ProfileSettings.vue';
import PasswordSettings from '@/components/settings/PasswordSettings.vue';
import AppearanceSettings from '@/components/settings/AppearanceSettings.vue';
import DeleteUser from '@/components/DeleteUser.vue';

// Import organization settings components
import ScoringWeights from '@/components/settings/ScoringWeights.vue';

interface Props {
    tab?: 'profile' | 'organization';
    mustVerifyEmail?: boolean;
    status?: string;
}

const props = withDefaults(defineProps<Props>(), {
    tab: 'profile'
});

// Tab state
const activeTab = ref<'profile' | 'organization'>(props.tab);

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Settings', href: '/settings' },
];

usePage();
</script>

<template>
    <Head title="Settings - Artist-Tree" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 max-w-5xl">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold">Settings</h1>
                <p class="text-muted-foreground">Manage your profile and organization settings</p>
            </div>

            <!-- Main Tabs -->
            <div class="border-b">
                <div class="flex gap-6">
                    <button
                        :class="[
                            'pb-3 text-sm font-medium transition-colors border-b-2 -mb-px flex items-center gap-2',
                            activeTab === 'profile' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeTab = 'profile'"
                    >
                        <User class="w-4 h-4" />
                        Profile
                    </button>
                    <button
                        :class="[
                            'pb-3 text-sm font-medium transition-colors border-b-2 -mb-px flex items-center gap-2',
                            activeTab === 'organization' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                        ]"
                        @click="activeTab = 'organization'"
                    >
                        <Building2 class="w-4 h-4" />
                        Organization
                    </button>
                </div>
            </div>

            <!-- Profile Tab -->
            <div v-if="activeTab === 'profile'" class="space-y-6">
                <!-- Profile Information -->
                <ProfileSettings :must-verify-email="mustVerifyEmail ?? false" :status="status" />

                <!-- Password -->
                <PasswordSettings />

                <!-- Appearance -->
                <AppearanceSettings />

                <!-- Delete Account -->
                <DeleteUser />
            </div>

            <!-- Organization Tab -->
            <div v-if="activeTab === 'organization'" class="space-y-6">
                <!-- Organization Sub-Tabs -->
                <div class="border-b">
                    <div class="flex gap-4">
                        <button
                            :class="[
                                'pb-2 px-1 text-sm font-medium transition-colors border-b-2 -mb-px',
                                true ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                            ]"
                        >
                            Scoring Weights
                        </button>
                        <button
                            :class="[
                                'pb-2 px-1 text-sm font-medium transition-colors border-b-2 -mb-px border-transparent text-muted-foreground'
                            ]"
                            disabled
                        >
                            Team Members
                        </button>
                    </div>
                </div>

                <!-- Organization Content - For now just show scoring weights -->
                <ScoringWeights />
            </div>
        </div>
    </MainLayout>
</template>
