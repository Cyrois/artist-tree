<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { avatarColors, getInitials } from '@/data/constants';
import type { Artist } from '@/data/types';
import { cn } from '@/lib/utils';
import { computed } from 'vue';

interface Props {
    artist: Artist;
    size?: 'sm' | 'md' | 'lg' | 'xl';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
});

const initials = computed(() => getInitials(props.artist.name));

const bgColor = computed(
    () => avatarColors[props.artist.id % avatarColors.length],
);

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'xl':
            return 'h-40 w-40 text-5xl';
        case 'lg':
            return 'h-20 w-20 text-2xl';
        case 'md':
            return 'h-16 w-16 text-xl';
        case 'sm':
        default:
            return 'h-12 w-12 text-sm';
    }
});
</script>

<template>
    <Avatar :class="cn('rounded-xl', sizeClasses)" data-slot="artist-avatar">
        <AvatarImage
            v-if="artist.image"
            :src="artist.image"
            :alt="artist.name"
            class="object-cover"
        />
        <AvatarFallback
            class="rounded-xl font-bold text-white"
            :style="{ backgroundColor: bgColor }"
        >
            {{ initials }}
        </AvatarFallback>
    </Avatar>
</template>
