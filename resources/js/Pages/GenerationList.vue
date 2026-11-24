<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowLeft, ArrowRight, ArrowUpRight, ImageOff, Images, Plus, RefreshCw } from '@lucide/vue';
import { computed, ref } from 'vue';
import WorkspaceCredits from '@/components/WorkspaceCredits.vue';
import WorkspaceLayout from '@/components/WorkspaceLayout.vue';
import { Button } from '@/components/ui/button';
import { actualGenerationCost, estimateGenerationCost } from '@/lib/generationCost';

const props = defineProps({
    createUrl: { type: String, required: true },
    indexUrl: { type: String, required: true },
    logoutUrl: { type: String, required: true },
    credits: { type: Object, required: true },
    generations: { type: Object, required: true },
    pricing: { type: Object, default: null },
    markupPercent: { type: Number, required: true },
});

const statuses = {
    queued: { label: 'Queued', color: 'bg-[#e8eafa] text-[#343a80]', detail: 'Waiting for PixelForge to start.' },
    processing: { label: 'Processing', color: 'bg-[#e8eafa] text-[#343a80]', detail: 'PixelForge is generating your image.' },
    succeeded: { label: 'Ready', color: 'bg-[#e5efe4] text-[#2f6038]', detail: 'Image preview unavailable.' },
    failed: { label: 'Failed', color: 'bg-[#fae9e6] text-[#963828]', detail: 'PixelForge did not produce an image.' },
};

const refreshForm = useForm({});
const activeGeneration = ref(null);
const unavailableImages = ref(new Set());

const entries = computed(() => props.generations.data.map((entry) => {
    const options = { ...entry, markupPercent: props.markupPercent };
    const pricing = props.pricing && { ...props.pricing, rates: entry.rates ?? props.pricing.rates };

    return {
        ...entry,
        estimatedCost: estimateGenerationCost(pricing, options),
        actualCost: actualGenerationCost(options),
    };
}));

function statusFor(entry) {
    return statuses[entry.status] ?? { label: 'Unknown', color: 'bg-muted text-muted-foreground', detail: 'Image preview unavailable.' };
}

function refresh(entry) {
    activeGeneration.value = entry.id;
    refreshForm.clearErrors();
    refreshForm.post(entry.refreshUrl, { preserveScroll: true });
}
</script>

<template>
    <WorkspaceLayout active="index" :create-url="createUrl" :index-url="indexUrl" :logout-url="logoutUrl">
        <Head title="Generation List · Stillroom" />

        <div class="mx-auto grid w-full max-w-[75rem] gap-6 px-4 py-5 sm:px-8 md:py-7">
            <section aria-labelledby="archive-title" class="relative isolate flex flex-wrap items-end justify-between gap-5 overflow-hidden rounded-2xl border border-[#292c62] bg-[#202452] px-5 py-7 text-white sm:px-8 sm:py-9">
                <div aria-hidden="true" class="archive-grid pointer-events-none absolute inset-0 -z-10"></div>
                <div class="grid gap-2.5">
                    <p class="flex items-center gap-2 font-mono text-[0.65rem] tracking-[.2em] text-[#ced3ff] uppercase">
                        <span class="h-px w-5 bg-[#b4bcff]" aria-hidden="true"></span> The image archive
                    </p>
                    <h1 id="archive-title" class="text-[clamp(2.1rem,4.5vw,3.6rem)] leading-none font-medium tracking-[-.06em]">Generation List</h1>
                    <p class="max-w-md text-sm leading-relaxed text-[#dce0fc]">Revisit your generated images and check the progress of pending requests.</p>
                </div>
                <Button as-child variant="outline" class="border-white/25 bg-white/10 text-white hover:bg-white/20 hover:text-white">
                    <Link :href="createUrl">
                        <Plus class="size-4" aria-hidden="true" />
                        New generation
                    </Link>
                </Button>
            </section>

            <WorkspaceCredits :credits="credits" />

            <section v-if="entries.length" aria-labelledby="history-title" class="grid gap-3">
                <h2 id="history-title" class="flex items-center gap-2 px-1 font-mono text-[0.65rem] font-medium tracking-[.16em] text-muted-foreground uppercase">
                    <ArrowDownLeft class="size-3.5" aria-hidden="true" />
                    Newest first
                </h2>
                <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <li v-for="(entry, index) in entries" :key="entry.id" class="grid content-start overflow-hidden rounded-xl border border-border bg-white shadow-xs">
                        <a v-if="entry.imageUrl && !unavailableImages.has(entry.imageUrl)" :href="entry.imageUrl" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" aria-label="Open generated image in a new tab" class="group relative grid aspect-[4/3] place-items-center bg-[#eeeee9] focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary">
                            <img :src="entry.imageUrl" :alt="entry.prompt.slice(0, 160)" :loading="index < 3 ? 'eager' : 'lazy'" decoding="async" referrerpolicy="no-referrer" class="absolute inset-0 size-full object-contain" @error="unavailableImages.add(entry.imageUrl)" />
                            <ArrowUpRight class="absolute top-3 right-3 size-7 rounded-md bg-white/90 p-1.5 text-primary shadow-xs group-hover:bg-white" aria-hidden="true" />
                        </a>
                        <div v-else class="archive-placeholder grid aspect-[4/3] place-items-center border-b border-border p-4 text-center">
                            <div class="grid justify-items-center gap-2">
                                <span class="grid size-10 place-items-center rounded-lg border border-border bg-white text-primary">
                                    <component :is="entry.refreshUrl ? Images : ImageOff" class="size-5" aria-hidden="true" />
                                </span>
                                <p class="text-xs text-muted-foreground">{{ entry.expired ? 'Image link expired.' : statusFor(entry).detail }}</p>
                            </div>
                        </div>

                        <div class="grid gap-2 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusFor(entry).color">{{ statusFor(entry).label }}</span>
                                <span class="font-mono text-[0.65rem] text-muted-foreground">{{ entry.size }}</span>
                            </div>
                            <p class="line-clamp-3 text-sm leading-relaxed wrap-anywhere">{{ entry.prompt }}</p>
                            <dl class="grid gap-1 rounded-lg bg-[#f2f2f6] px-3 py-2 text-xs">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <dt class="text-muted-foreground">Estimated cost</dt>
                                    <dd v-if="entry.estimatedCost" class="font-mono tabular-nums">{{ entry.estimatedCost.min }}–{{ entry.estimatedCost.max }}</dd>
                                    <dd v-else class="text-muted-foreground">Unavailable</dd>
                                </div>
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <dt class="text-muted-foreground">Actual cost</dt>
                                    <dd v-if="entry.actualCost !== null" class="font-mono font-semibold text-primary tabular-nums">{{ entry.actualCost }}</dd>
                                    <dd v-else class="text-muted-foreground">{{ entry.refreshUrl ? 'Pending usage' : 'Unavailable' }}</dd>
                                </div>
                            </dl>
                            <div class="flex min-h-8 flex-wrap items-center justify-between gap-2 border-t border-border pt-2">
                                <time v-if="entry.createdAt" :datetime="entry.createdAt" class="font-mono text-[0.65rem] text-muted-foreground">{{ entry.createdAt }}</time>
                                <Button v-if="entry.refreshUrl" type="button" variant="outline" size="sm" class="ml-auto" :disabled="refreshForm.processing" @click="refresh(entry)">
                                    <RefreshCw class="size-3.5" aria-hidden="true" />
                                    {{ refreshForm.processing && activeGeneration === entry.id ? 'Checking…' : 'Check status' }}
                                </Button>
                            </div>
                            <p v-if="activeGeneration === entry.id && refreshForm.errors.refresh" class="text-xs text-destructive" role="alert">{{ refreshForm.errors.refresh }}</p>
                        </div>
                    </li>
                </ul>
            </section>

            <div v-else class="grid justify-items-center gap-3 rounded-xl border border-dashed border-[#c9cadc] bg-white px-5 py-12 text-center">
                <span class="grid size-11 place-items-center rounded-xl bg-[#e8eafa] text-primary"><Images class="size-5" aria-hidden="true" /></span>
                <h2 class="text-lg font-semibold tracking-tight">{{ generations.current_page > 1 ? 'No generations on this page' : 'No generations yet' }}</h2>
                <p v-if="generations.current_page === 1" class="max-w-sm text-sm text-muted-foreground">Images you request in the studio will appear here.</p>
                <Button v-if="generations.current_page === 1" as-child variant="outline">
                    <Link :href="createUrl">Go to the studio <ArrowRight class="size-4" aria-hidden="true" /></Link>
                </Button>
            </div>

            <nav v-if="generations.prev_page_url || generations.next_page_url" aria-label="Generation pages" class="flex flex-wrap items-center gap-2">
                <Button v-if="generations.prev_page_url" as-child variant="outline">
                    <Link :href="generations.prev_page_url"><ArrowLeft class="size-4" aria-hidden="true" /> Newer</Link>
                </Button>
                <Button v-if="generations.next_page_url" as-child variant="outline" class="ml-auto">
                    <Link :href="generations.next_page_url">Older <ArrowRight class="size-4" aria-hidden="true" /></Link>
                </Button>
            </nav>
        </div>
    </WorkspaceLayout>
</template>

<style scoped>
.archive-grid {
    background-image:
        radial-gradient(ellipse at 91% 120%, #656bb99c, transparent 65%),
        repeating-linear-gradient(90deg, transparent 0 26px, #a6adff20 26px 27px),
        repeating-linear-gradient(0deg, transparent 0 26px, #a6adff20 26px 27px);
}

.archive-placeholder {
    background-color: #f2f2f6;
    background-image: radial-gradient(#50578524 1px, transparent 1px);
    background-size: 12px 12px;
}
</style>
