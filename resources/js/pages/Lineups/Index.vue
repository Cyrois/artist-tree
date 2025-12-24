<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { Button } from '@/components/ui/button';
import LineupCard from '@/components/lineup/LineupCard.vue';
import { getLineups } from '@/data/lineups';
import type { Lineup } from '@/data/types';
import { Plus } from 'lucide-vue-next';
import { trans } from 'laravel-vue-i18n';

const lineups = getLineups();

function handleLineupClick(lineup: Lineup) {
    router.visit(`/lineups/${lineup.id}`);
}

const breadcrumbs = [
    { title: trans('common.breadcrumb_dashboard'), href: '/dashboard' },
    { title: trans('common.breadcrumb_my_lineups'), href: '/lineups' },
];
</script>

<template>
    <Head :title="$t('lineups.index_page_title')" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ $t('lineups.index_title') }}</h1>
                    <p class="text-muted-foreground">{{ $t('lineups.index_subtitle') }}</p>
                </div>
                <Button>
                    <Plus class="w-4 h-4 mr-2" />
                    {{ $t('lineups.index_create_button') }}
                </Button>
            </div>

            <!-- Lineups Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <LineupCard
                    v-for="lineup in lineups"
                    :key="lineup.id"
                    :lineup="lineup"
                    @click="handleLineupClick"
                />
            </div>

            <!-- Empty State -->
            <div v-if="lineups.length === 0" class="text-center py-12">
                <p class="text-muted-foreground mb-4">{{ $t('lineups.index_empty_state_message') }}</p>
                <Button>
                    <Plus class="w-4 h-4 mr-2" />
                    {{ $t('lineups.index_create_button') }}
                </Button>
            </div>
        </div>
    </MainLayout>
</template>
