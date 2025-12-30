<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useAppearance } from '@/composables/useAppearance';
import { trans } from 'laravel-vue-i18n';
import { Monitor, Moon, Sun } from 'lucide-vue-next';

const { appearance, updateAppearance } = useAppearance();
</script>

<template>
    <div class="max-w-2xl space-y-6">
        <Card>
            <CardHeader>
                <CardTitle>{{ trans('settings.theme_title') }}</CardTitle>
                <CardDescription>
                    {{ trans('settings.theme_subtitle') }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-3 gap-4">
                    <button
                        :class="[
                            'flex flex-col items-center gap-3 rounded-lg border-2 p-4 transition-colors',
                            appearance === 'light'
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-primary/50',
                        ]"
                        @click="updateAppearance('light')"
                    >
                        <div
                            class="rounded-full bg-amber-100 p-3 dark:bg-amber-900/30"
                        >
                            <Sun class="h-6 w-6 text-amber-600" />
                        </div>
                        <span class="font-medium">{{
                            trans('settings.theme_light')
                        }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            trans('settings.theme_light_description')
                        }}</span>
                    </button>

                    <button
                        :class="[
                            'flex flex-col items-center gap-3 rounded-lg border-2 p-4 transition-colors',
                            appearance === 'dark'
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-primary/50',
                        ]"
                        @click="updateAppearance('dark')"
                    >
                        <div
                            class="rounded-full bg-indigo-100 p-3 dark:bg-indigo-900/30"
                        >
                            <Moon class="h-6 w-6 text-indigo-600" />
                        </div>
                        <span class="font-medium">{{
                            trans('settings.theme_dark')
                        }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            trans('settings.theme_dark_description')
                        }}</span>
                    </button>

                    <button
                        :class="[
                            'flex flex-col items-center gap-3 rounded-lg border-2 p-4 transition-colors',
                            appearance === 'system'
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-primary/50',
                        ]"
                        @click="updateAppearance('system')"
                    >
                        <div
                            class="rounded-full bg-gray-100 p-3 dark:bg-gray-800"
                        >
                            <Monitor class="h-6 w-6 text-gray-600" />
                        </div>
                        <span class="font-medium">{{
                            trans('settings.theme_system')
                        }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            trans('settings.theme_system_description')
                        }}</span>
                    </button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
