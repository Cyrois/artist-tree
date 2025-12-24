<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useAppearance } from '@/composables/useAppearance';
import { Sun, Moon, Monitor } from 'lucide-vue-next';

const { appearance, updateAppearance } = useAppearance();
</script>

<template>
    <div class="space-y-6 max-w-2xl">
        <Card>
            <CardHeader>
                <CardTitle>Theme</CardTitle>
                <CardDescription>
                    Choose your preferred color scheme for the application.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-3 gap-4">
                    <button
                        :class="[
                            'flex flex-col items-center gap-3 p-4 rounded-lg border-2 transition-colors',
                            appearance === 'light'
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-primary/50'
                        ]"
                        @click="updateAppearance('light')"
                    >
                        <div class="p-3 rounded-full bg-amber-100 dark:bg-amber-900/30">
                            <Sun class="w-6 h-6 text-amber-600" />
                        </div>
                        <span class="font-medium">Light</span>
                        <span class="text-xs text-muted-foreground">Bright and clean</span>
                    </button>

                    <button
                        :class="[
                            'flex flex-col items-center gap-3 p-4 rounded-lg border-2 transition-colors',
                            appearance === 'dark'
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-primary/50'
                        ]"
                        @click="updateAppearance('dark')"
                    >
                        <div class="p-3 rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                            <Moon class="w-6 h-6 text-indigo-600" />
                        </div>
                        <span class="font-medium">Dark</span>
                        <span class="text-xs text-muted-foreground">Easy on the eyes</span>
                    </button>

                    <button
                        :class="[
                            'flex flex-col items-center gap-3 p-4 rounded-lg border-2 transition-colors',
                            appearance === 'system'
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-primary/50'
                        ]"
                        @click="updateAppearance('system')"
                    >
                        <div class="p-3 rounded-full bg-gray-100 dark:bg-gray-800">
                            <Monitor class="w-6 h-6 text-gray-600" />
                        </div>
                        <span class="font-medium">System</span>
                        <span class="text-xs text-muted-foreground">Match device</span>
                    </button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
