<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { AlertTriangle, Loader2 } from 'lucide-vue-next';

interface Props {
    open: boolean;
    lineupId: number;
    artist: {
        id: number;
        name: string;
    } | null;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:open']);

const form = useForm({});

function handleRemove() {
    if (!props.artist) return;

    form.delete(`/lineups/${props.lineupId}/artists/${props.artist.id}`, {
        onSuccess: () => {
            emit('update:open', false);
        },
    });
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
                    {{ $t('lineups.remove_artist_title') }}
                </DialogTitle>
            </DialogHeader>

            <div class="space-y-6 p-8 pt-6">
                <div class="text-base text-muted-foreground">
                    <p>
                        {{ $t('lineups.remove_artist_message_prefix') }}
                        <span class="font-bold text-foreground">{{
                            artist?.name
                        }}</span>
                        {{ $t('lineups.remove_artist_message_suffix') }}
                    </p>
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
                        :disabled="form.processing"
                        @click="handleRemove"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        {{ $t('lineups.remove_artist_confirm') }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
