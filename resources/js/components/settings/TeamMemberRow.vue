<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { avatarColors, getInitials } from '@/data/constants';
import type { TeamMember } from '@/data/types';
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
    <div
        class="flex items-center gap-4 rounded-lg p-4 transition-colors hover:bg-muted/50"
        data-slot="team-member-row"
    >
        <Avatar>
            <AvatarImage
                v-if="member.avatar"
                :src="member.avatar"
                :alt="member.name"
            />
            <AvatarFallback
                :style="{ backgroundColor: bgColor }"
                class="font-medium text-white"
            >
                {{ getInitials(member.name) }}
            </AvatarFallback>
        </Avatar>

        <div class="min-w-0 flex-1">
            <p class="truncate font-medium">{{ member.name }}</p>
            <p class="truncate text-sm text-muted-foreground">
                {{ member.email }}
            </p>
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
                            <ChevronDown class="ml-1 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem @click="emit('update-role', 'admin')">
                            Admin
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            @click="emit('update-role', 'member')"
                        >
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
                <X class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>
