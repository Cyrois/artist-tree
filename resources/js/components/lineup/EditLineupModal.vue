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
import { Loader2 } from 'lucide-vue-next';
import { watch } from 'vue';

interface Props {
    open: boolean;
    lineup: {
        id: number;
        name: string;
        description?: string | null;
    };
}

const props = defineProps<Props>();
const emit = defineEmits(['update:open']);

const form = useForm({
    name: '',
    description: '',
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            form.name = props.lineup.name;
            form.description = props.lineup.description || '';
            form.clearErrors();
        }
    },
);

function handleSubmit() {
    form.put(`/lineups/${props.lineup.id}`, {
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
                <DialogTitle class="text-2xl font-bold text-foreground">{{
                    $t('common.action_edit')
                }}</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-6 p-8 pt-6">
                <div class="space-y-2">
                    <Label
                        for="name"
                        class="text-sm font-semibold text-foreground"
                        >{{ $t('lineups.create_name_label') }}</Label
                    >
                    <Input
                        id="name"
                        v-model="form.name"
                        :placeholder="$t('lineups.create_name_placeholder')"
                        :class="[
                            'h-12 border-2 text-base transition-colors focus-visible:border-foreground focus-visible:ring-0',
                            { 'border-destructive': form.errors.name },
                        ]"
                        required
                    />
                    <div
                        v-if="form.errors.name"
                        class="mt-1 text-xs font-medium text-destructive"
                    >
                        {{ form.errors.name }}
                    </div>
                </div>

                <div class="space-y-2">
                    <Label
                        for="description"
                        class="text-sm font-semibold text-foreground"
                    >
                        {{ $t('lineups.create_description_label_optional') }}
                    </Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        :placeholder="
                            $t('lineups.create_description_placeholder')
                        "
                        class="flex min-h-[120px] w-full resize-none rounded-md border-2 border-input bg-transparent px-3 py-3 text-base shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:border-foreground focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        :class="{
                            'border-destructive': form.errors.description,
                        }"
                    />
                    <div
                        v-if="form.errors.description"
                        class="mt-1 text-xs font-medium text-destructive"
                    >
                        {{ form.errors.description }}
                    </div>
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
                        type="submit"
                        class="h-14 flex-1 border-none bg-[#EE6055] text-base font-bold text-white shadow-lg shadow-[#EE6055]/20 transition-all hover:bg-[#D54B41] active:scale-[0.98]"
                        :disabled="form.processing"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        {{ $t('common.action_save') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
