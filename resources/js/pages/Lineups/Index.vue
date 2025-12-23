<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { Button } from '@/components/ui/button';
import LineupCard from '@/components/lineup/LineupCard.vue';
import { getLineups } from '@/data/lineups';
import type { Lineup } from '@/data/types';
import { trans } from 'laravel-vue-i18n';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';

const lineups = getLineups();

function handleLineupClick(lineup: Lineup) {
    router.visit(`/lineups/${lineup.id}`);
}

const breadcrumbs = computed(() => [
    { title: trans('navigation.dashboard'), href: '/dashboard' },
    { title: trans('navigation.my_lineups'), href: '/lineups' },
]);
</script>

<template>
    <Head title="My Lineups - Artist-Tree" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ trans('lineups.title') }}</h1>
                    <p class="text-muted-foreground">{{ trans('lineups.description') }}</p>
                </div>
                <Button>
                    <Plus class="w-4 h-4 mr-2" />
                    {{ trans('lineups.create_button') }}
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
                <p class="text-muted-foreground mb-4">{{ trans('lineups.no_lineups') }}</p>
                <Button>
                    <Plus class="w-4 h-4 mr-2" />
                    {{ trans('lineups.create_button') }}
                </Button>
            </div>
        </div>
    </MainLayout>
</template>
