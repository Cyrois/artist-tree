<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import ArtistAvatar from '@/components/artist/ArtistAvatar.vue';
import { statusConfig, statusOrder, formatCurrency } from '@/data/constants';
import { tierConfig } from '@/data/constants';
import type { Artist, ArtistStatus, BookingStatus, TierType } from '@/data/types';
import { Lightbulb, Mail, DollarSign, Send, FileSignature, CheckCircle, X } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    artists: Artist[];
    statuses: Record<number, ArtistStatus>;
    artistTiers: Record<number, TierType>;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'artist-click': [artist: Artist];
}>();

const iconMap = {
    Lightbulb,
    Mail,
    DollarSign,
    Send,
    FileSignature,
    CheckCircle,
    X,
};

// Group artists by status
const artistsByStatus = computed(() => {
    const grouped: Record<BookingStatus, Artist[]> = {
        idea: [],
        outreach: [],
        negotiating: [],
        contract_sent: [],
        contract_signed: [],
        confirmed: [],
        declined: [],
    };

    props.artists.forEach((artist) => {
        const status = props.statuses[artist.id]?.status ?? 'idea';
        grouped[status].push(artist);
    });

    return grouped;
});

function getArtistTier(artistId: number): TierType {
    return props.artistTiers[artistId] ?? 'undercard';
}
</script>

<template>
    <div class="flex gap-4 overflow-x-auto pb-4" data-slot="kanban-board">
        <div
            v-for="status in statusOrder"
            :key="status"
            class="flex-shrink-0 w-64"
        >
            <!-- Column Header -->
            <div
                class="flex items-center gap-2 px-3 py-2 rounded-t-lg"
                :style="{ backgroundColor: statusConfig[status].bgColor }"
            >
                <component
                    :is="iconMap[statusConfig[status].icon as keyof typeof iconMap]"
                    class="w-4 h-4"
                    :style="{ color: statusConfig[status].color }"
                />
                <span class="font-medium text-sm" :style="{ color: statusConfig[status].color }">
                    {{ statusConfig[status].label }}
                </span>
                <span class="ml-auto text-xs bg-white/50 px-2 py-0.5 rounded-full">
                    {{ artistsByStatus[status].length }}
                </span>
            </div>

            <!-- Column Content -->
            <div class="min-h-[300px] bg-muted/30 rounded-b-lg p-2 space-y-2">
                <Card
                    v-for="artist in artistsByStatus[status]"
                    :key="artist.id"
                    class="cursor-pointer hover:shadow-md transition-shadow"
                    @click="emit('artist-click', artist)"
                >
                    <CardContent class="p-3">
                        <div class="flex items-center gap-3">
                            <ArtistAvatar :artist="artist" size="sm" />
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-sm truncate">{{ artist.name }}</p>
                                <p
                                    class="text-xs"
                                    :style="{ color: tierConfig[getArtistTier(artist.id)].color }"
                                >
                                    {{ tierConfig[getArtistTier(artist.id)].label }}
                                </p>
                            </div>
                        </div>
                        <div v-if="statuses[artist.id]?.fee" class="mt-2 pt-2 border-t text-sm font-medium">
                            {{ formatCurrency(statuses[artist.id].fee!) }}
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty state -->
                <div
                    v-if="artistsByStatus[status].length === 0"
                    class="h-20 border-2 border-dashed rounded-lg flex items-center justify-center text-muted-foreground text-sm"
                >
                    Drop artists here
                </div>
            </div>
        </div>
    </div>
</template>
