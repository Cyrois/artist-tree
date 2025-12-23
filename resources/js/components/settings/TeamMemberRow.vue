<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { getInitials, avatarColors } from '@/data/constants';
import type { TeamMember } from '@/data/types';
import { cn } from '@/lib/utils';
import { ChevronDown, X } from 'lucide-vue-next';

interface Props {
    member: TeamMember;
    canEdit?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canEdit: false,
});

const emit = defineEmits<{
    'update-role': [role: 'admin' | 'member'];
    remove: [];
}>();

const roleLabels = {
    owner: 'Owner',
    admin: 'Admin',
    member: 'Member',
};

const roleBadgeVariants = {
    owner: 'default',
    admin: 'secondary',
    member: 'outline',
} as const;

const bgColor = avatarColors[props.member.id % avatarColors.length];
</script>

<template>
    <div class="flex items-center gap-4 p-4 rounded-lg hover:bg-muted/50 transition-colors" data-slot="team-member-row">
        <Avatar>
            <AvatarImage v-if="member.avatar" :src="member.avatar" :alt="member.name" />
            <AvatarFallback :style="{ backgroundColor: bgColor }" class="text-white font-medium">
                {{ getInitials(member.name) }}
            </AvatarFallback>
        </Avatar>

        <div class="flex-1 min-w-0">
            <p class="font-medium truncate">{{ member.name }}</p>
            <p class="text-sm text-muted-foreground truncate">{{ member.email }}</p>
        </div>

        <div class="flex items-center gap-2">
            <!-- Role Badge/Dropdown -->
            <template v-if="member.role === 'owner' || !canEdit">
                <Badge :variant="roleBadgeVariants[member.role]">
                    {{ roleLabels[member.role] }}
                </Badge>
            </template>
            <template v-else>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm">
                            {{ roleLabels[member.role] }}
                            <ChevronDown class="w-4 h-4 ml-1" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem @click="emit('update-role', 'admin')">
                            Admin
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="emit('update-role', 'member')">
                            Member
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>

            <!-- Remove button -->
            <Button
                v-if="canEdit && member.role !== 'owner'"
                variant="ghost"
                size="icon"
                class="text-destructive hover:text-destructive"
                @click="emit('remove')"
            >
                <X class="w-4 h-4" />
            </Button>
        </div>
    </div>
</template>
