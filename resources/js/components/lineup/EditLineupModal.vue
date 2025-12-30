<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
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
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <div class="flex items-center justify-between">
                    <DialogTitle
                        >{{ $t('common.action_edit') }}
                        {{ $t('common.navigation_lineups') }}</DialogTitle
                    >
                </div>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4 py-4">
                <div class="space-y-2">
                    <Label for="name">{{
                        $t('lineups.create_name_label')
                    }}</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        :placeholder="$t('lineups.create_name_placeholder')"
                        :class="{ 'border-destructive': form.errors.name }"
                        required
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="description">
                        {{ $t('lineups.create_description_label_optional') }}
                    </Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        :placeholder="
                            $t('lineups.create_description_placeholder')
                        "
                        class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        :class="{
                            'border-destructive': form.errors.description,
                        }"
                    />
                    <p
                        v-if="form.errors.description"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="$emit('update:open', false)"
                    >
                        {{ $t('common.action_cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        <Loader2
                            v-if="form.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        {{ $t('common.action_save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
