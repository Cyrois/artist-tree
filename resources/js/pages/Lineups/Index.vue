<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { Button } from '@/components/ui/button';
import LineupCard from '@/components/lineup/LineupCard.vue';
import { getLineups } from '@/data/lineups';
import type { Lineup } from '@/data/types';
import { Plus } from 'lucide-vue-next';

const lineups = getLineups();

function handleLineupClick(lineup: Lineup) {
    router.visit(`/lineups/${lineup.id}`);
}

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'My Lineups', href: '/lineups' },
];
</script>

<template>
    <Head title="My Lineups - Artist-Tree" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">My Lineups</h1>
                    <p class="text-muted-foreground">Manage your festival lineups</p>
                </div>
                <Button>
                    <Plus class="w-4 h-4 mr-2" />
                    Create Lineup
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
                <p class="text-muted-foreground mb-4">No lineups yet. Create your first lineup!</p>
                <Button>
                    <Plus class="w-4 h-4 mr-2" />
                    Create Lineup
                </Button>
            </div>
        </div>
    </MainLayout>
</template>
