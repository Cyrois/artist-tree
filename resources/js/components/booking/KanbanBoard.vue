<script setup lang="ts">
import ArtistAvatar from '@/components/artist/ArtistAvatar.vue';
import { Card, CardContent } from '@/components/ui/card';
import {
    formatCurrency,
    statusConfig,
    statusOrder,
    tierConfig,
} from '@/data/constants';
import type {
    Artist,
    ArtistStatus,
    BookingStatus,
    TierType,
} from '@/data/types';
import {
    CheckCircle,
    DollarSign,
    FileSignature,
    Lightbulb,
    Mail,
    Send,
    X,
} from 'lucide-vue-next';
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
            class="w-64 flex-shrink-0"
        >
            <!-- Column Header -->
            <div
                class="flex items-center gap-2 rounded-t-lg px-3 py-2"
                :style="{ backgroundColor: statusConfig[status].bgColor }"
            >
                <component
                    :is="
                        iconMap[
                            statusConfig[status].icon as keyof typeof iconMap
                        ]
                    "
                    class="h-4 w-4"
                    :style="{ color: statusConfig[status].color }"
                />
                <span
                    class="text-sm font-medium"
                    :style="{ color: statusConfig[status].color }"
                >
                    {{ statusConfig[status].label }}
                </span>
                <span
                    class="ml-auto rounded-full bg-white/50 px-2 py-0.5 text-xs"
                >
                    {{ artistsByStatus[status].length }}
                </span>
            </div>

            <!-- Column Content -->
            <div class="min-h-[300px] space-y-2 rounded-b-lg bg-muted/30 p-2">
                <Card
                    v-for="artist in artistsByStatus[status]"
                    :key="artist.id"
                    class="cursor-pointer transition-shadow hover:shadow-md"
                    @click="emit('artist-click', artist)"
                >
                    <CardContent class="p-3">
                        <div class="flex items-center gap-3">
                            <ArtistAvatar :artist="artist" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ artist.name }}
                                </p>
                                <p
                                    class="text-xs"
                                    :style="{
                                        color: tierConfig[
                                            getArtistTier(artist.id)
                                        ].color,
                                    }"
                                >
                                    {{
                                        tierConfig[getArtistTier(artist.id)]
                                            .label
                                    }}
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="statuses[artist.id]?.fee"
                            class="mt-2 border-t pt-2 text-sm font-medium"
                        >
                            {{ formatCurrency(statuses[artist.id].fee!) }}
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty state -->
                <div
                    v-if="artistsByStatus[status].length === 0"
                    class="flex h-20 items-center justify-center rounded-lg border-2 border-dashed text-sm text-muted-foreground"
                >
                    Drop artists here
                </div>
            </div>
        </div>
    </div>
</template>
