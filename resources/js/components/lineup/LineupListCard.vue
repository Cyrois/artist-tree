<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ChevronRight, Lightbulb, Mail, DollarSign, Plane, Leaf, Check, X } from 'lucide-vue-next';

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
    updated_at: string; // human readable
    total_artists: number;
    artists: Artist[];
}

const props = defineProps<{
    lineup: Lineup;
}>();

const tiers = ['headliner', 'sub_headliner', 'mid_tier', 'undercard'];
const tierLabels: Record<string, string> = {
    headliner: 'HEADLINER',
    sub_headliner: 'SUB-HEADLINER',
    mid_tier: 'MID-TIER',
    undercard: 'UNDERCARD',
};

const tierColors: Record<string, string> = {
    headliner: 'bg-black',
    sub_headliner: 'bg-gray-700',
    mid_tier: 'bg-gray-400',
    undercard: 'bg-gray-300',
};

const artistsByTier = computed(() => {
    const grouped: Record<string, Artist[]> = {
        headliner: [],
        sub_headliner: [],
        mid_tier: [],
        undercard: [],
    };
    
    props.lineup.artists.forEach(artist => {
        if (grouped[artist.tier]) {
            grouped[artist.tier].push(artist);
        }
    });
    return grouped;
});

function getInitials(name: string) {
    return name
        .split(' ')
        .map(n => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function getRandomColor(name: string) {
    const colors = [
        'bg-red-500', 'bg-blue-500', 'bg-green-500', 'bg-yellow-500', 
        'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-teal-500'
    ];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
}
</script>

<template>
    <Card class="overflow-hidden hover:shadow-lg transition-all duration-200 py-0 group">
        <div class="p-6">
            <!-- Header -->
            <div class="flex justify-between items-start mb-2">
                <div>
                    <h3 class="text-xl font-bold text-foreground">{{ lineup.name }}</h3>
                    <p class="text-muted-foreground text-sm mt-1">{{ lineup.description }}</p>
                    <div class="text-xs text-muted-foreground mt-4">
                        Created {{ lineup.created_at }} <span class="mx-2">&bull;</span> Updated {{ lineup.updated_at }}
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold">{{ lineup.total_artists }}</div>
                    <div class="text-xs text-muted-foreground uppercase tracking-wide">artists</div>
                </div>
            </div>

            <!-- Tiers List -->
            <div class="space-y-4 mt-8">
                <div v-for="tier in tiers" :key="tier" class="flex items-center justify-between">
                    <div class="flex items-center gap-4 flex-1">
                        <div class="w-2 h-2 rounded-full" :class="tierColors[tier]"></div> <!-- Bullet -->
                        <span class="text-xs font-bold text-muted-foreground w-24 uppercase tracking-wider">
                            {{ tierLabels[tier].replace('_', ' ') }}
                        </span>
                        
                        <!-- Avatars -->
                        <div class="flex -space-x-2">
                            <template v-if="artistsByTier[tier].length > 0">
                                <div 
                                    v-for="artist in artistsByTier[tier].slice(0, 4)" 
                                    :key="artist.id"
                                    class="w-8 h-8 rounded-full border-2 border-background flex items-center justify-center text-xs font-bold text-white relative z-0 hover:z-10 transition-all"
                                    :class="getRandomColor(artist.name)"
                                    :title="artist.name"
                                >
                                    <img 
                                        v-if="artist.image_url" 
                                        :src="artist.image_url" 
                                        class="w-full h-full rounded-full object-cover" 
                                    />
                                    <span v-else>{{ getInitials(artist.name) }}</span>
                                </div>
                                <div 
                                    v-if="artistsByTier[tier].length > 4"
                                    class="w-8 h-8 rounded-full border-2 border-background bg-muted flex items-center justify-center text-xs font-medium text-muted-foreground relative z-0"
                                >
                                    +{{ artistsByTier[tier].length - 4 }}
                                </div>
                            </template>
                            <span v-else class="text-xs text-muted-foreground italic pl-2">None</span>
                        </div>
                    </div>
                    
                    <!-- Count -->
                    <div class="text-sm font-medium text-muted-foreground">
                        {{ artistsByTier[tier].length }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Action -->
        <div 
            class="p-4 border-t flex items-center justify-between group-hover:bg-muted/50 cursor-pointer transition-all duration-200"
            @click="router.visit(`/lineups/${lineup.id}`)"
        >
            <span class="text-primary font-medium transition-all duration-200 group-hover:text-foreground group-hover:underline underline-offset-4 decoration-2">
                View & Edit
            </span>
            <ChevronRight class="w-4 h-4 text-primary transition-all duration-200 group-hover:text-foreground group-hover:translate-x-1" />
        </div>
    </Card>
</template>
