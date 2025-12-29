<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { Button } from '@/components/ui/button';
import LineupListCard from '@/components/lineup/LineupListCard.vue';
import { Plus } from 'lucide-vue-next';

interface Artist {
    id: number;
    name: string;
    image_url: string | null;
    tier: string;
}

interface Lineup {
    id: number;
    name: string;
    description: string;
    created_at: string;
    updated_at: string;
    total_artists: number;
    artists: Artist[];
}

defineProps<{
    lineups: Lineup[];
}>();

const breadcrumbs = [
    { title: 'My Lineups', href: '/lineups' }
];

function createLineup() {
    // Stub for create action
    console.log('Create lineup clicked');
}
</script>

<template>
    <Head title="My Lineups" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-8">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <h1 class="text-3xl font-bold tracking-tight">My Lineups</h1>
                    <p class="text-lg text-muted-foreground">Manage your festival lineups and artist placements</p>
                </div>
                <Button size="lg" class="gap-2" disabled @click="createLineup">
                    <Plus class="w-5 h-5" />
                    Create Lineup
                </Button>
            </div>

            <!-- Lineup Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                 <LineupListCard 
                    v-for="lineup in lineups" 
                    :key="lineup.id" 
                    :lineup="lineup" 
                 />
            </div>
        </div>
    </MainLayout>
</template>