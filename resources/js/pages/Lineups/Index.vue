<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MainLayout from '@/layouts/MainLayout.vue';
import { Button } from '@/components/ui/button';
import LineupListCard from '@/components/lineup/LineupListCard.vue';
import CreateLineupModal from '@/components/lineup/CreateLineupModal.vue';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

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

const breadcrumbs = [
    { title: trans('lineups.index_title'), href: '/lineups' }
];

const showCreateModal = ref(false);

function createLineup() {
    showCreateModal.value = true;
}
</script>

<template>
    <Head :title="$t('lineups.index_title')" />
    <MainLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-8">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <h1 class="text-3xl font-bold tracking-tight">{{ $t('lineups.index_title') }}</h1>
                    <p class="text-lg text-muted-foreground">{{ $t('lineups.index_subtitle') }}</p>
                </div>
                <Button 
                    size="lg" 
                    class="gap-2 bg-[#EE6055] hover:bg-[#EE6055]/90 text-white" 
                    @click="createLineup"
                >
                    <Plus class="w-5 h-5" />
                    {{ $t('lineups.index_create_button') }}
                </Button>
            </div>

            <!-- Lineup Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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