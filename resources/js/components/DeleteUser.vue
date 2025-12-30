<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Form } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { useTemplateRef } from 'vue';

// Components
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <div class="max-w-2xl">
        <Card>
            <CardHeader>
                <CardTitle>{{
                    trans('settings.delete_account_title')
                }}</CardTitle>
                <CardDescription>
                    {{ trans('settings.delete_account_subtitle') }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div
                    class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
                >
                    <div
                        class="relative space-y-0.5 text-red-600 dark:text-red-100"
                    >
                        <p class="font-medium">
                            {{ trans('settings.delete_account_warning') }}
                        </p>
                        <p class="text-sm">
                            {{
                                trans(
                                    'settings.delete_account_warning_description',
                                )
                            }}
                        </p>
                    </div>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button
                                variant="destructive"
                                data-test="delete-user-button"
                                >{{
                                    trans('settings.delete_account_button')
                                }}</Button
                            >
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="ProfileController.destroy.form()"
                                reset-on-success
                                @error="() => passwordInput?.$el?.focus()"
                                :options="{
                                    preserveScroll: true,
                                }"
                                class="space-y-6"
                                v-slot="{
                                    errors,
                                    processing,
                                    reset,
                                    clearErrors,
                                }"
                            >
                                <DialogHeader class="space-y-3">
                                    <DialogTitle>{{
                                        trans(
                                            'settings.delete_account_confirm_title',
                                        )
                                    }}</DialogTitle>
                                    <DialogDescription>
                                        {{
                                            trans(
                                                'settings.delete_account_confirm_description',
                                            )
                                        }}
                                    </DialogDescription>
                                </DialogHeader>

                                <div class="grid gap-2">
                                    <Label for="password" class="sr-only">{{
                                        trans('settings.password_current_label')
                                    }}</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        name="password"
                                        ref="passwordInput"
                                        :placeholder="
                                            trans(
                                                'settings.password_current_placeholder',
                                            )
                                        "
                                    />
                                    <InputError :message="errors.password" />
                                </div>

                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button
                                            variant="secondary"
                                            @click="
                                                () => {
                                                    clearErrors();
                                                    reset();
                                                }
                                            "
                                        >
                                            {{ trans('common.action_cancel') }}
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        :disabled="processing"
                                        data-test="confirm-delete-user-button"
                                    >
                                        {{
                                            trans(
                                                'settings.delete_account_button',
                                            )
                                        }}
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
