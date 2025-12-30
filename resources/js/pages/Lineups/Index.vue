<script setup lang="ts">
import CreateLineupModal from '@/components/lineup/CreateLineupModal.vue';
import LineupListCard from '@/components/lineup/LineupListCard.vue';
import { Button } from '@/components/ui/button';
import MainLayout from '@/layouts/MainLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';

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
    lineups: { data: Lineup[] };
}>();

const breadcrumbs = [{ title: 'My Lineups', href: '/lineups' }];

const showCreateModal = ref(false);

function createLineup() {
    showCreateModal.value = true;
}
</script>

<template>
    <Head title="My Lineups" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-8">
            <!-- Header -->
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div class="space-y-1">
                    <h1 class="text-3xl font-bold tracking-tight">
                        My Lineups
                    </h1>
                    <p class="text-lg text-muted-foreground">
                        Manage your festival lineups and artist placements
                    </p>
                </div>
                <Button
                    size="lg"
                    class="gap-2 bg-[#EE6055] text-white hover:bg-[#EE6055]/90"
                    @click="createLineup"
                >
                    <Plus class="h-5 w-5" />
                    Create Lineup
                </Button>
            </div>

            <!-- Lineup Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <LineupListCard
                    v-for="lineup in lineups.data"
                    :key="lineup.id"
                    :lineup="lineup"
                />
            </div>
        </div>

        <CreateLineupModal v-model:open="showCreateModal" />
    </MainLayout>
</template>
