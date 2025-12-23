<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import MockupSidebar from '@/components/MockupSidebar.vue';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Separator } from '@/components/ui/separator';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface BreadcrumbItemType {
    title: string;
    href: string;
}

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const hasBreadcrumbs = computed(() => props.breadcrumbs.length > 0);
</script>

<template>
    <AppShell variant="sidebar">
        <MockupSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <!-- Header with breadcrumbs -->
            <header
                class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
            >
                <div class="flex items-center gap-2">
                    <SidebarTrigger class="-ml-1" />
                    <Separator v-if="hasBreadcrumbs" orientation="vertical" class="mr-2 h-4" />
                    <Breadcrumb v-if="hasBreadcrumbs">
                        <BreadcrumbList>
                            <template v-for="(item, index) in breadcrumbs" :key="index">
                                <BreadcrumbItem>
                                    <BreadcrumbLink as-child>
                                        <Link :href="item.href">{{ item.title }}</Link>
                                    </BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator v-if="index < breadcrumbs.length - 1" />
                            </template>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>
            </header>

            <!-- Main content -->
            <main class="flex-1 p-6">
                <slot />
            </main>
        </AppContent>
    </AppShell>
</template>
