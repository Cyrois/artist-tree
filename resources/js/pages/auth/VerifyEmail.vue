<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { Form, Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        :title="trans('auth.verify_email.title')"
        :description="trans('auth.verify_email.description')"
    >
        <Head :title="trans('auth.verify_email.title')" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ trans('auth.verify_email.verification_sent') }}
        </div>

        <Form
            v-bind="send.form()"
            class="space-y-6 text-center"
            v-slot="{ processing }"
        >
            <Button :disabled="processing" variant="secondary">
                <Spinner v-if="processing" />
                {{ trans('auth.verify_email.resend_button') }}
            </Button>

            <TextLink
                :href="logout()"
                as="button"
                class="mx-auto block text-sm"
            >
                {{ trans('auth.verify_email.logout') }}
            </TextLink>
        </Form>
    </AuthLayout>
</template>
