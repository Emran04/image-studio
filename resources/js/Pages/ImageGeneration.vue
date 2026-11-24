<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { ArrowDownLeft, ArrowRight, ArrowUp, Check, ImagePlus, Link2, LogOut, Scan, Sparkles, WandSparkles } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps({
    imageSizes: { type: Array, required: true },
    promptMaxLength: { type: Number, required: true },
    submitUrl: { type: String, required: true },
    logoutUrl: { type: String, required: true },
});

const suggestions = [
    { label: 'Cinematic landscape', prompt: 'A cinematic landscape of a lone greenhouse in a misty valley at sunrise, shot on 35mm film.' },
    { label: 'Product photography', prompt: 'Studio product photography of a cobalt glass perfume bottle on a limestone plinth, with sharp afternoon shadows.' },
    { label: 'Surreal illustration', prompt: 'A surreal illustration of a tiny paper moon floating above a dark blue sea, with a soft grain texture.' },
];

const form = useForm({ prompt: '', size: props.imageSizes[0].value, image_url: '' });
const promptLength = computed(() => Array.from(form.prompt).length);
const logoutForm = useForm({});
const messages = ref([]);
const latestMessage = ref(null);

function useSuggestion(prompt) {
    form.prompt = prompt;
    form.clearErrors('prompt');
    document.getElementById('generation-prompt')?.focus();
}

function submit() {
    if (form.processing) {
        return;
    }

    if (promptLength.value > props.promptMaxLength) {
        form.setError('prompt', `The prompt field must not be greater than ${props.promptMaxLength} characters.`);
        document.getElementById('generation-prompt')?.focus();
        return;
    }

    form.post(props.submitUrl, {
        preserveScroll: true,
        onSuccess: async (page) => {
            if (!page.flash?.submission) {
                return;
            }

            messages.value.push(page.flash.submission);
            form.reset('prompt', 'image_url');
            await nextTick();
            latestMessage.value?.scrollIntoView({ block: 'nearest' });
        },
    });
}

function sizeLabel(value) {
    return props.imageSizes.find((size) => size.value === value)?.label ?? value;
}
</script>

<template>
    <div class="min-h-dvh bg-background text-foreground md:grid md:grid-cols-[14.5rem_minmax(0,1fr)]">
        <Head title="Image Generation · Stillroom" />

        <aside class="flex flex-col gap-4 border-b border-border bg-[#eeeee9] px-4 py-4 sm:px-6 md:sticky md:top-0 md:h-dvh md:gap-10 md:border-r md:border-b-0 md:px-4 md:py-6">
            <Link href="/" class="flex w-fit items-center gap-3 rounded-lg px-1 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary">
                <span class="grid size-9 place-items-center rounded-[10px] bg-[#24264e] text-white">
                    <Scan class="size-5" aria-hidden="true" />
                </span>
                <span>
                    <span class="block text-[1.05rem] leading-none font-semibold tracking-tight">Stillroom</span>
                    <span class="mt-1 block font-mono text-[0.6rem] tracking-[.15em] text-muted-foreground uppercase">Image studio</span>
                </span>
            </Link>

            <nav aria-label="Workspace">
                <p class="mb-2 px-2 font-mono text-[0.65rem] font-medium tracking-[.16em] text-muted-foreground uppercase md:mb-3">Workspace</p>
                <ul>
                    <li>
                        <Link href="/app" aria-current="page" class="flex items-center gap-2.5 rounded-lg border border-[#d8d9eb] bg-[#e2e3f3] px-3 py-2.5 text-sm font-semibold text-[#292d6a] hover:bg-[#d9dcf2] focus-visible:outline-2 focus-visible:outline-primary">
                            <ImagePlus class="size-4 shrink-0" aria-hidden="true" />
                            Image Generation
                            <span class="ml-auto size-1.5 rounded-full bg-primary" aria-hidden="true"></span>
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="mt-auto hidden items-center gap-2 border-t border-border px-1 pt-4 text-xs text-muted-foreground md:flex">
                <span class="size-2 rounded-full border border-[#777ac2]" aria-hidden="true"></span>
                Local workspace
            </div>
        </aside>

        <main class="flex min-w-0 flex-col">
            <header class="flex min-h-14 items-center justify-between gap-2 border-b border-border px-4 sm:px-8">
                <div class="flex items-center gap-2 text-sm">
                    <span class="hidden text-muted-foreground sm:inline">Workspace</span>
                    <span class="hidden text-muted-foreground sm:inline" aria-hidden="true">/</span>
                    <span class="font-medium">Image Generation</span>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span class="hidden items-center gap-1.5 rounded-full border border-border bg-white px-2.5 py-1 font-mono text-[0.6rem] tracking-wider text-muted-foreground uppercase lg:flex">
                        <Sparkles class="size-3 text-primary" aria-hidden="true" />
                        Prompt studio
                    </span>
                    <form :action="logoutUrl" method="post" @submit.prevent="logoutForm.post(logoutUrl)">
                        <Button type="submit" variant="outline" size="sm" class="border-border bg-white" :disabled="logoutForm.processing">
                            <LogOut class="size-4" aria-hidden="true" />
                            {{ logoutForm.processing ? 'Signing out…' : 'Log out' }}
                        </Button>
                    </form>
                </div>
            </header>

            <div class="mx-auto flex w-full max-w-[65rem] flex-1 flex-col gap-5 px-4 py-5 sm:px-8 md:py-7">
                <section aria-labelledby="generation-title" class="studio-hero relative isolate flex min-h-[15.5rem] items-center overflow-hidden rounded-2xl border border-[#292c62] bg-[#202452] px-5 py-8 text-white sm:min-h-[17rem] sm:px-9">
                    <svg class="pointer-events-none absolute top-1/2 right-[-12rem] h-[21rem] w-[32rem] max-w-none -translate-y-1/2 opacity-45 sm:right-[-5rem] sm:opacity-80 lg:right-0 lg:opacity-100" viewBox="0 0 540 350" fill="none" aria-hidden="true">
                        <defs>
                            <radialGradient id="studio-halo">
                                <stop stop-color="#a7a9ff" stop-opacity=".65" />
                                <stop offset="1" stop-color="#6c71e0" stop-opacity="0" />
                            </radialGradient>
                            <linearGradient id="studio-orbit" x1="147" y1="75" x2="432" y2="278" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#eef1ff" />
                                <stop offset=".55" stop-color="#9faaff" />
                                <stop offset="1" stop-color="#626ce0" stop-opacity=".25" />
                            </linearGradient>
                        </defs>
                        <ellipse cx="306" cy="179" rx="223" ry="174" fill="url(#studio-halo)" />
                        <g stroke="#c3c7ff" stroke-opacity=".28" stroke-dasharray="3 5">
                            <path d="M308 28v294M130 175h356" />
                            <circle cx="308" cy="175" r="124" />
                        </g>
                        <g transform="rotate(-28 308 175)" stroke="url(#studio-orbit)" stroke-width="1.2">
                            <ellipse v-for="(radius, i) in [27, 39, 52, 64, 76, 88, 99, 110, 120]" :key="radius" cx="308" :cy="204 - i * 6" rx="147" :ry="radius" :opacity="1 - i * .045" />
                        </g>
                        <g fill="#ebefff">
                            <circle cx="173" cy="190" r="3" />
                            <circle cx="402" cy="104" r="3.5" />
                            <circle cx="308" cy="175" r="2.5" />
                            <path d="M463 58v17m-8.5-8.5h17M200 289v13m-6.5-6.5h13" stroke="#ebefff" stroke-width="1.3" />
                        </g>
                    </svg>

                    <div class="relative max-w-[25rem]">
                        <p class="mb-3.5 flex items-center gap-2 font-mono text-[0.65rem] tracking-[.2em] text-[#ced3ff] uppercase">
                            <span class="h-px w-5 bg-[#b4bcff]" aria-hidden="true"></span>
                            The generation desk
                        </p>
                        <h1 id="generation-title" class="text-[clamp(2.2rem,4.8vw,4rem)] leading-[.98] font-medium tracking-[-.065em]">
                            What will you<br class="hidden sm:block" /> imagine today?
                        </h1>
                        <p class="mt-4 max-w-75 text-sm leading-relaxed text-[#dce0fc]">
                            Describe an image, pick your dimensions, and add a reference if you have one.
                        </p>
                    </div>
                </section>

                <section v-if="messages.length === 0" aria-label="Prompt ideas" class="flex flex-wrap items-center gap-2">
                    <span class="mr-1 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                        <ArrowDownLeft class="size-3.5" aria-hidden="true" />
                        Try an idea
                    </span>
                    <Button v-for="suggestion in suggestions" :key="suggestion.label" type="button" variant="outline" size="sm" class="rounded-full border-border bg-white px-3 text-xs" @click="useSuggestion(suggestion.prompt)">
                        {{ suggestion.label }}
                        <ArrowRight class="size-3 text-muted-foreground" aria-hidden="true" />
                    </Button>
                </section>

                <div v-else aria-label="Conversation" role="log" class="grid max-h-72 gap-3 overflow-y-auto rounded-xl px-1 py-1">
                    <article v-for="(message, index) in messages" :key="index" class="grid gap-2">
                        <div class="ml-auto grid w-fit max-w-[90%] gap-1.5 rounded-xl rounded-br-sm bg-[#e8eafa] px-4 py-3">
                            <p class="text-xs font-semibold text-primary">You · {{ sizeLabel(message.size) }}</p>
                            <p class="text-sm wrap-anywhere whitespace-pre-wrap">{{ message.prompt }}</p>
                            <p v-if="message.image_url" class="text-xs wrap-anywhere text-muted-foreground">Reference: {{ message.image_url }}</p>
                        </div>
                        <div class="flex w-fit items-start gap-2 rounded-xl rounded-bl-sm border border-border bg-white px-3 py-2.5 text-sm">
                            <Check class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                            <p>Generation status: <span class="font-medium">{{ message.status }}</span></p>
                        </div>
                    </article>
                    <div ref="latestMessage" aria-hidden="true"></div>
                </div>

                <form aria-label="Image generation prompt" :aria-busy="form.processing" novalidate class="grid gap-3.5 rounded-2xl border border-border bg-white p-4 shadow-[0_5px_20px_-13px_#25285340] sm:p-5" @submit.prevent="submit">
                    <div class="grid gap-1.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label for="generation-prompt" class="text-sm font-semibold">Prompt</label>
                            <span id="generation-prompt-limit" class="font-mono text-xs" :class="promptLength > promptMaxLength ? 'text-destructive' : 'text-muted-foreground'">{{ promptLength }} / {{ promptMaxLength }}</span>
                        </div>
                        <Textarea id="generation-prompt" v-model="form.prompt" :aria-invalid="!!form.errors.prompt" :aria-describedby="form.errors.prompt ? 'generation-prompt-error generation-prompt-limit' : 'generation-prompt-limit'" required class="field-sizing-fixed min-h-[5.25rem] resize-y bg-[#f8f8f6]" placeholder="A quiet observatory under a sky full of stars…" @input="form.clearErrors('prompt')" />
                        <p v-if="form.errors.prompt" id="generation-prompt-error" class="text-xs text-destructive" role="alert">{{ form.errors.prompt }}</p>
                    </div>

                    <div class="grid items-end gap-3 sm:grid-cols-[minmax(10.5rem,1fr)_minmax(0,1.55fr)] lg:grid-cols-[minmax(11rem,1fr)_minmax(0,1.55fr)_auto]">
                        <div class="grid min-w-0 gap-1.5 self-start [&_[data-slot=native-select-wrapper]]:w-full">
                            <label for="generation-size" class="text-xs font-semibold">Image size</label>
                            <NativeSelect id="generation-size" v-model="form.size" class="w-full bg-[#f8f8f6]" :aria-invalid="!!form.errors.size" :aria-describedby="form.errors.size ? 'generation-size-error' : undefined" @change="form.clearErrors('size')">
                                <NativeSelectOption v-for="size in imageSizes" :key="size.value" :value="size.value">
                                    {{ size.label }} · {{ size.dimensions }}
                                </NativeSelectOption>
                            </NativeSelect>
                            <p v-if="form.errors.size" id="generation-size-error" class="text-xs text-destructive" role="alert">{{ form.errors.size }}</p>
                        </div>

                        <div class="grid min-w-0 gap-1.5 self-start">
                            <label for="generation-reference" class="flex items-center gap-1.5 text-xs font-semibold">
                                Reference image URL <span class="font-normal text-muted-foreground">Optional</span>
                            </label>
                            <div class="relative">
                                <Link2 class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                                <Input id="generation-reference" v-model="form.image_url" type="url" maxlength="2048" placeholder="https://example.com/image.png" class="bg-[#f8f8f6] pl-9" :aria-invalid="!!form.errors.image_url" :aria-describedby="form.errors.image_url ? 'generation-reference-error' : undefined" @input="form.clearErrors('image_url')" />
                            </div>
                            <p v-if="form.errors.image_url" id="generation-reference-error" class="text-xs text-destructive" role="alert">{{ form.errors.image_url }}</p>
                        </div>

                        <Button type="submit" :disabled="form.processing" class="min-h-9 sm:col-span-2 sm:justify-self-end lg:col-span-1 lg:self-start lg:mt-[1.375rem]">
                            <WandSparkles class="size-4" aria-hidden="true" />
                            {{ form.processing ? 'Submitting…' : 'Submit prompt' }}
                            <ArrowUp class="size-3.5" aria-hidden="true" />
                        </Button>
                    </div>
                    <p v-if="form.errors.generation" class="text-xs text-destructive" role="alert">{{ form.errors.generation }}</p>
                </form>

                <p class="-mt-2 text-center text-xs leading-relaxed text-muted-foreground">The status shown is the one PixelForge returned at submission.</p>
            </div>
        </main>
    </div>
</template>

<style scoped>
.studio-hero {
    background-image:
        linear-gradient(90deg, #202452 16%, transparent 88%),
        repeating-linear-gradient(0deg, transparent 0 27px, #a6adff16 27px 28px),
        repeating-linear-gradient(90deg, transparent 0 27px, #a6adff16 27px 28px),
        linear-gradient(110deg, #202452 30%, #363b7e 100%);
}
</style>
