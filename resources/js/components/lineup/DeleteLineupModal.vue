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
import { AlertTriangle, Loader2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    open: boolean;
    lineup: {
        id: number;
        name: string;
    };
}

const props = defineProps<Props>();
const emit = defineEmits(['update:open']);

const confirmation = ref('');
const form = useForm({});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            confirmation.value = '';
        }
    },
);

function handleDelete() {
    if (confirmation.value.toLowerCase() !== 'delete') return;

    // TODO: Connect to backend
    // form.delete(`/lineups/${props.lineup.id}`, {
    //     onSuccess: () => {
    //         emit('update:open', false);
    //     },
    // });

    // For now, just close modal as requested
    console.log('Delete confirmed for lineup:', props.lineup.id);
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent
            class="overflow-hidden border-none p-0 shadow-2xl sm:max-w-[480px]"
        >
            <DialogHeader class="p-8 pb-0">
                <DialogTitle
                    class="flex items-center gap-2 text-2xl font-bold text-destructive"
                >
                    <AlertTriangle class="h-6 w-6" />
                    {{ $t('lineups.delete_title') }}
                </DialogTitle>
            </DialogHeader>

            <div class="space-y-6 p-8 pt-6">
                <div
                    class="rounded-lg bg-destructive/10 p-4 text-sm text-destructive dark:bg-destructive/20"
                >
                    <p class="font-medium">
                        {{ $t('lineups.delete_warning_title') }}
                    </p>
                    <p class="mt-1 opacity-90">
                        {{
                            $t('lineups.delete_warning_message', {
                                name: lineup.name,
                            })
                        }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label
                        for="confirmation"
                        class="text-sm font-semibold text-foreground"
                    >
                        {{ $t('lineups.delete_confirmation_label') }}
                    </Label>
                    <Input
                        id="confirmation"
                        v-model="confirmation"
                        placeholder="delete"
                        class="h-12 border-2 text-base transition-colors focus-visible:border-destructive focus-visible:ring-0"
                    />
                </div>

                <div class="flex gap-4 pt-2">
                    <Button
                        type="button"
                        variant="secondary"
                        class="h-14 flex-1 border-none bg-[#F1F3F5] text-base font-bold text-[#495057] transition-all hover:bg-[#E9ECEF] dark:bg-muted dark:text-foreground dark:hover:bg-muted/80"
                        @click="$emit('update:open', false)"
                    >
                        {{ $t('common.action_cancel') }}
                    </Button>
                    <Button
                        type="button"
                        class="h-14 flex-1 border-none bg-destructive text-base font-bold text-white shadow-lg shadow-destructive/20 transition-all hover:bg-destructive/90 active:scale-[0.98]"
                        :disabled="
                            confirmation.toLowerCase() !== 'delete' ||
                            form.processing
                        "
                        @click="handleDelete"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        {{ $t('lineups.delete_button') }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
