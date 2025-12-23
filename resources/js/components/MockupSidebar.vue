<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarGroup,
    SidebarGroupContent,
} from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, Home, List, Search, Settings } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';

const { updateAppearance } = useAppearance();

// Initialize theme to light if not set
onMounted(() => {
    const stored = localStorage.getItem('appearance');
    if (!stored) {
        updateAppearance('light');
    }
});

const page = usePage();
const currentPath = computed(() => page.url);

const navItems = [
    { id: 'dashboard', href: '/mockup', icon: Home, label: 'Dashboard' },
    { id: 'search', href: '/mockup/search', icon: Search, label: 'Search Artists' },
    { id: 'lineups', href: '/mockup/lineups', icon: List, label: 'My Lineups' },
    { id: 'settings', href: '/mockup/settings', icon: Settings, label: 'Settings' },
];

function isActive(href: string): boolean {
    if (href === '/mockup') {
        return currentPath.value === '/mockup' || currentPath.value === '/mockup/';
    }
    return currentPath.value.startsWith(href);
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/mockup">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in navItems" :key="item.id">
                            <SidebarMenuButton
                                as-child
                                :class="
                                    cn(
                                        'w-full',
                                        isActive(item.href) && 'bg-sidebar-accent text-sidebar-accent-foreground'
                                    )
                                "
                            >
                                <Link :href="item.href" class="flex items-center gap-3">
                                    <component :is="item.icon" class="h-5 w-5" />
                                    <span>{{ item.label }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <div class="p-4">
                <div
                    class="rounded-xl border p-4"
                    :class="cn('bg-sidebar-accent/50 border-sidebar-border')"
                >
                    <div class="flex items-center gap-3 mb-2">
                        <Building2 class="h-4 w-4 text-sidebar-primary" />
                        <span class="text-sm font-medium text-sidebar-foreground">My Organization</span>
                    </div>
                    <p class="text-xs text-sidebar-foreground/60">Free Plan - 3 lineups</p>
                </div>
            </div>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
