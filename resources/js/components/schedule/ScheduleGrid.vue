<script setup lang="ts">
import { Button } from '@/components/ui/button';
import ArtistAvatar from '@/components/artist/ArtistAvatar.vue';
import { scheduleDays, scheduleStages } from '@/data/constants';
import type { Artist, ScheduleSlot } from '@/data/types';
import { ref, computed } from 'vue';

interface Props {
    artists: Artist[];
    schedule: Record<number, ScheduleSlot>;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'artist-click': [artist: Artist];
}>();

const selectedDay = ref('Saturday');

// Time slots from 12:00 to 24:00
const timeSlots = [
    '12:00', '13:00', '14:00', '15:00', '16:00', '17:00',
    '18:00', '19:00', '20:00', '21:00', '22:00', '23:00', '00:00'
];

// Get scheduled artists for selected day
const scheduledArtists = computed(() => {
    return props.artists
        .filter((artist) => {
            const slot = props.schedule[artist.id];
            return slot && slot.day === selectedDay.value;
        })
        .map((artist) => ({
            artist,
            slot: props.schedule[artist.id],
        }));
});

// Group by stage
const artistsByStage = computed(() => {
    const grouped: Record<string, { artist: Artist; slot: ScheduleSlot }[]> = {};
    scheduleStages.forEach((stage) => {
        grouped[stage] = scheduledArtists.value.filter((a) => a.slot.stage === stage);
    });
    return grouped;
});

// Calculate position based on time
function getTimePosition(startTime: string): number {
    const [hours, minutes] = startTime.split(':').map(Number);
    const startHour = 12;
    const totalMinutes = (hours - startHour) * 60 + minutes;
    if (hours < startHour) {
        // After midnight
        return ((24 - startHour + hours) * 60 + minutes) / (13 * 60) * 100;
    }
    return (totalMinutes / (13 * 60)) * 100;
}

// Calculate width based on duration
function getDurationWidth(duration: number): number {
    return (duration / (13 * 60)) * 100;
}
</script>

<template>
    <div class="space-y-4" data-slot="schedule-grid">
        <!-- Day Tabs -->
        <div class="flex gap-2">
            <Button
                v-for="day in scheduleDays"
                :key="day"
                :variant="selectedDay === day ? 'default' : 'outline'"
                @click="selectedDay = day"
            >
                {{ day }}
            </Button>
        </div>

        <!-- Schedule Grid -->
        <div class="border rounded-xl overflow-hidden">
            <!-- Time Header -->
            <div class="flex border-b bg-muted/50">
                <div class="w-32 flex-shrink-0 p-3 font-medium border-r">Stage</div>
                <div class="flex-1 flex">
                    <div
                        v-for="time in timeSlots"
                        :key="time"
                        class="flex-1 p-2 text-center text-xs text-muted-foreground border-r last:border-r-0"
                    >
                        {{ time }}
                    </div>
                </div>
            </div>

            <!-- Stage Rows -->
            <div
                v-for="stage in scheduleStages"
                :key="stage"
                class="flex border-b last:border-b-0 min-h-[80px]"
            >
                <!-- Stage Name -->
                <div class="w-32 flex-shrink-0 p-3 font-medium border-r bg-muted/30 flex items-center">
                    {{ stage }}
                </div>

                <!-- Timeline -->
                <div class="flex-1 relative">
                    <!-- Grid lines -->
                    <div class="absolute inset-0 flex">
                        <div
                            v-for="time in timeSlots"
                            :key="time"
                            class="flex-1 border-r last:border-r-0 border-dashed border-muted"
                        />
                    </div>

                    <!-- Artist slots -->
                    <div
                        v-for="{ artist, slot } in artistsByStage[stage]"
                        :key="artist.id"
                        class="absolute top-2 bottom-2 rounded-lg p-2 cursor-pointer hover:shadow-md transition-shadow bg-primary text-primary-foreground flex items-center gap-2 overflow-hidden"
                        :style="{
                            left: `${getTimePosition(slot.startTime)}%`,
                            width: `${getDurationWidth(slot.duration)}%`,
                            minWidth: '100px'
                        }"
                        @click="emit('artist-click', artist)"
                    >
                        <ArtistAvatar :artist="artist" size="sm" class="flex-shrink-0" />
                        <div class="min-w-0">
                            <p class="font-medium text-sm truncate">{{ artist.name }}</p>
                            <p class="text-xs opacity-80">{{ slot.startTime }} ({{ slot.duration }}min)</p>
                        </div>
                    </div>

                    <!-- Empty state if no artists -->
                    <div
                        v-if="artistsByStage[stage].length === 0"
                        class="absolute inset-0 flex items-center justify-center text-muted-foreground text-sm"
                    >
                        No artists scheduled
                    </div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-4 text-sm text-muted-foreground">
            <span>Click on an artist to edit their schedule</span>
        </div>
    </div>
</template>
