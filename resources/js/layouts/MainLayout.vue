<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/vue3';
import { CheckCircle } from 'lucide-vue-next';
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
        <AppSidebar />
        <AppContent variant="sidebar" class="[overflow-x:clip]">
            <!-- Header with breadcrumbs -->
            <header
                class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
            >
                <div class="flex items-center gap-2">
                    <SidebarTrigger class="-ml-1" />
                    <Separator
                        v-if="hasBreadcrumbs"
                        orientation="vertical"
                        class="mr-2 h-4"
                    />
                    <Breadcrumb v-if="hasBreadcrumbs">
                        <BreadcrumbList>
                            <template
                                v-for="(item, index) in breadcrumbs"
                                :key="index"
                            >
                                <BreadcrumbItem>
                                    <BreadcrumbLink as-child>
                                        <Link :href="item.href">{{
                                            item.title
                                        }}</Link>
                                    </BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator
                                    v-if="index < breadcrumbs.length - 1"
                                />
                            </template>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>
            </header>

            <!-- Main content -->
            <main class="flex-1 p-6">
                <div v-if="$page.props.flash.success" class="mb-6">
                    <Alert
                        variant="default"
                        class="border-green-500/50 bg-green-500/10 text-green-600 dark:border-green-500/30 dark:text-green-400"
                    >
                        <CheckCircle class="h-4 w-4" />
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>
                            {{ $page.props.flash.success }}
                        </AlertDescription>
                    </Alert>
                </div>
                <slot />
            </main>
        </AppContent>
    </AppShell>
</template>
