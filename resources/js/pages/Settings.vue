<script setup lang="ts">
import MainLayout from '@/layouts/MainLayout.vue';
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Building2, User } from 'lucide-vue-next';
import { ref } from 'vue';

// Import profile settings components
import DeleteUser from '@/components/DeleteUser.vue';
import AppearanceSettings from '@/components/settings/AppearanceSettings.vue';
import PasswordSettings from '@/components/settings/PasswordSettings.vue';
import ProfileSettings from '@/components/settings/ProfileSettings.vue';

// Import organization settings components
import ScoringWeights from '@/components/settings/ScoringWeights.vue';

interface Props {
    tab?: 'profile' | 'organization';
    mustVerifyEmail?: boolean;
    status?: string;
}

const props = withDefaults(defineProps<Props>(), {
    tab: 'profile',
});

// Tab state
const activeTab = ref<'profile' | 'organization'>(props.tab);

const breadcrumbs = [
    { title: trans('common.breadcrumb_dashboard'), href: '/dashboard' },
    { title: trans('common.breadcrumb_settings'), href: '/settings' },
];
</script>

<template>
    <Head :title="$t('settings.page_title')" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-5xl space-y-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold">{{ $t('settings.title') }}</h1>
                <p class="text-muted-foreground">
                    {{ $t('settings.subtitle') }}
                </p>
            </div>

            <!-- Main Tabs -->
            <div class="border-b">
                <div class="flex gap-6">
                    <button
                        :class="[
                            '-mb-px flex items-center gap-2 border-b-2 pb-3 text-sm font-medium transition-colors',
                            activeTab === 'profile'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeTab = 'profile'"
                    >
                        <User class="h-4 w-4" />
                        {{ $t('settings.tab_profile') }}
                    </button>
                    <button
                        :class="[
                            '-mb-px flex items-center gap-2 border-b-2 pb-3 text-sm font-medium transition-colors',
                            activeTab === 'organization'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeTab = 'organization'"
                    >
                        <Building2 class="h-4 w-4" />
                        {{ $t('settings.tab_organization') }}
                    </button>
                </div>
            </div>

            <!-- Profile Tab -->
            <div v-if="activeTab === 'profile'" class="space-y-6">
                <!-- Profile Information -->
                <ProfileSettings
                    :must-verify-email="mustVerifyEmail ?? false"
                    :status="status"
                />

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
                                '-mb-px border-b-2 px-1 pb-2 text-sm font-medium transition-colors',
                                true
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            ]"
                        >
                            {{ $t('settings.org_tab_scoring') }}
                        </button>
                        <button
                            :class="[
                                '-mb-px border-b-2 border-transparent px-1 pb-2 text-sm font-medium text-muted-foreground transition-colors',
                            ]"
                            disabled
                        >
                            {{ $t('settings.org_tab_team') }}
                        </button>
                    </div>
                </div>

                <!-- Organization Content - For now just show scoring weights -->
                <ScoringWeights />
            </div>
        </div>
    </MainLayout>
</template>
