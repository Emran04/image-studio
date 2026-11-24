<script setup>
import { useForm } from '@inertiajs/vue3';
import { Coins, Plus } from '@lucide/vue';
import {
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
    AlertDialogTrigger,
} from 'reka-ui';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { formatCreditUsd } from '@/lib/generationCost';

const props = defineProps({
    credits: { type: Object, required: true },
});

const isOpen = ref(false);
const purchaseForm = useForm({});

function confirmPurchase() {
    if (purchaseForm.processing) {
        return;
    }

    purchaseForm.post(props.credits.purchaseUrl, {
        preserveScroll: true,
        onSuccess: () => { isOpen.value = false; },
    });
}
</script>

<template>
    <section aria-labelledby="workspace-credits" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#d8d9eb] bg-[#f3f3fa] px-3 py-2.5 sm:px-4">
        <div class="flex items-center gap-2.5">
            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-[#e2e3f3] text-primary">
                <Coins class="size-4" aria-hidden="true" />
            </span>
            <div class="grid gap-0.5">
                <h2 id="workspace-credits" class="font-mono text-[0.65rem] leading-tight font-medium tracking-[.12em] text-[#343a80] uppercase">Credit balance</h2>
                <div class="grid gap-0.5 text-muted-foreground" role="status">
                    <p class="text-sm leading-tight">
                        <span class="font-semibold text-foreground tabular-nums">{{ formatCreditUsd(credits.balance) }}</span>
                        available
                    </p>
                    <p class="text-xs tabular-nums">
                        {{ formatCreditUsd(credits.used) }} used<span v-if="credits.reserved"> · {{ formatCreditUsd(credits.reserved) }} reserved</span>
                    </p>
                </div>
            </div>
        </div>

        <AlertDialogRoot v-model:open="isOpen">
            <AlertDialogTrigger as-child>
                <Button id="buy-credits" type="button" variant="outline" size="sm" class="border-[#d8d9eb] bg-white text-primary">
                    <Plus class="size-3.5" aria-hidden="true" />
                    Buy credits
                </Button>
            </AlertDialogTrigger>

            <AlertDialogPortal>
                <AlertDialogOverlay class="fixed inset-0 z-50 bg-[#1b1d3d]/55" />
                <AlertDialogContent class="fixed top-1/2 left-1/2 z-50 grid max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-sm -translate-x-1/2 -translate-y-1/2 gap-4 overflow-y-auto rounded-2xl border border-[#d8d9eb] bg-white p-5 text-foreground shadow-xl sm:p-6" @escape-key-down="purchaseForm.processing && $event.preventDefault()">
                    <p class="flex items-center gap-2 font-mono text-[0.65rem] font-medium tracking-[.14em] text-primary uppercase">
                        <Coins class="size-4" aria-hidden="true" />
                        Demo credit pack
                    </p>
                    <div class="grid gap-2">
                        <AlertDialogTitle class="text-xl leading-tight font-semibold tracking-tight">Add {{ formatCreditUsd(credits.packSize, 2) }} in credits?</AlertDialogTitle>
                        <AlertDialogDescription class="text-sm leading-relaxed text-muted-foreground">
                            This will add {{ formatCreditUsd(credits.packSize, 2) }} in demo credits to your account. No payment will be taken.
                        </AlertDialogDescription>
                    </div>
                    <form :action="credits.purchaseUrl" method="post" :aria-busy="purchaseForm.processing" class="flex flex-wrap justify-end gap-2" @submit.prevent="confirmPurchase">
                        <AlertDialogCancel as-child>
                            <Button id="cancel-credit-purchase" type="button" variant="outline" :disabled="purchaseForm.processing">Cancel</Button>
                        </AlertDialogCancel>
                        <Button type="submit" :disabled="purchaseForm.processing">
                            {{ purchaseForm.processing ? 'Adding…' : 'Confirm purchase' }}
                        </Button>
                    </form>
                </AlertDialogContent>
            </AlertDialogPortal>
        </AlertDialogRoot>
    </section>
</template>
