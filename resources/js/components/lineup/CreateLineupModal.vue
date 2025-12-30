<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';

interface Props {
    open: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const form = useForm({
    name: '',
    description: '',
});

function submit() {
    form.post('/lineups', {
        onSuccess: () => {
            form.reset();
            emit('update:open', false);
        },
    });
}

function close() {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="overflow-hidden border-none p-0 shadow-2xl sm:max-w-[480px]"
        >
            <DialogHeader class="p-8 pb-0">
                <DialogTitle class="text-2xl font-bold text-foreground">{{ $t('lineups.create_title') }}</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-6 p-8 pt-6">
                <div class="space-y-2">
                    <Label for="name" required class="text-sm font-semibold text-foreground">{{ $t('lineups.create_name_label') }}</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        :placeholder="$t('lineups.create_name_placeholder')"
                        required
                        class="h-12 border-2 text-base transition-colors focus-visible:border-foreground focus-visible:ring-0"
                    />
                    <div
                        v-if="form.errors.name"
                        class="mt-1 text-xs font-medium text-destructive"
                    >
                        {{ form.errors.name }}
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="description" class="text-sm font-semibold text-foreground">{{ $t('lineups.create_description_label_optional') }}</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        :placeholder="$t('lineups.create_description_placeholder')"
                        class="flex min-h-[120px] w-full rounded-md border-2 border-input bg-transparent px-3 py-3 text-base shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:border-foreground transition-colors disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                    ></textarea>
                    <div
                        v-if="form.errors.description"
                        class="mt-1 text-xs font-medium text-destructive"
                    >
                        {{ form.errors.description }}
                    </div>
                </div>

                <div class="bg-[#F8F9FA] dark:bg-muted/30 rounded-xl p-6 space-y-4">
                    <p class="text-sm font-semibold text-foreground">{{ $t('lineups.create_tiers_intro') }}</p>
                    <div class="grid grid-cols-2 gap-y-3">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full bg-black dark:bg-foreground"></div>
                            <span class="text-[10px] font-black uppercase tracking-[0.1em] text-muted-foreground">{{ $t('lineups.tier_headliner') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full bg-gray-700 dark:bg-gray-400"></div>
                            <span class="text-[10px] font-black uppercase tracking-[0.1em] text-muted-foreground">{{ $t('lineups.tier_sub_headliner') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full bg-gray-400 dark:bg-gray-600"></div>
                            <span class="text-[10px] font-black uppercase tracking-[0.1em] text-muted-foreground">{{ $t('lineups.tier_mid_tier') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-800"></div>
                            <span class="text-[10px] font-black uppercase tracking-[0.1em] text-muted-foreground">{{ $t('lineups.tier_undercard') }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-4 pt-2">
                    <Button
                        type="button"
                        variant="secondary"
                        class="h-14 flex-1 border-none bg-[#F1F3F5] text-base font-bold text-[#495057] transition-all hover:bg-[#E9ECEF] dark:bg-muted dark:text-foreground dark:hover:bg-muted/80"
                        @click="close"
                    >
                        {{ $t('common.action_cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        class="h-14 flex-1 border-none bg-[#EE6055] text-base font-bold text-white shadow-lg shadow-[#EE6055]/20 transition-all hover:bg-[#D54B41] active:scale-[0.98]"
                        :disabled="form.processing || form.name.length < 5"
                    >
                        {{ $t('lineups.create_submit_button') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
