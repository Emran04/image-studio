<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDown, ArrowRight, ArrowUpRight, Crop, Images, Scan, TextCursorInput, WandSparkles } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineProps({
    appName: { type: String, required: true },
    workspaceUrl: { type: String, required: true },
    adminLoginUrl: { type: String, required: true },
});

const workflow = [
    { number: '01', title: 'Describe the image', detail: 'Write what you want to see, from the subject to the lighting and mood.', icon: TextCursorInput },
    { number: '02', title: 'Set the frame', detail: 'Choose square, landscape, or portrait. Add a reference image if you have one.', icon: Crop },
    { number: '03', title: 'Revisit your work', detail: 'See your prompts, images, and generation costs in one place.', icon: Images },
];
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <Head title="Stillroom · Image Studio">
            <meta head-key="description" name="description" content="Turn a written prompt into an image with Stillroom. Choose a frame, add a reference, and revisit your work in your generation history." />
        </Head>

        <header class="border-b border-border bg-secondary">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-8 lg:px-12">
                <Link href="/" aria-label="Stillroom home" class="inline-flex items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary">
                    <span class="grid size-9 shrink-0 place-items-center rounded-[10px] bg-[#24264e] text-white">
                        <Scan class="size-5" aria-hidden="true" />
                    </span>
                    <span>
                        <span class="block text-[1.05rem] leading-none font-semibold tracking-tight">Stillroom</span>
                        <span class="mt-1 block font-mono text-[0.6rem] tracking-[.15em] text-muted-foreground uppercase">Image studio</span>
                    </span>
                </Link>
                <nav aria-label="Studio access" class="flex flex-wrap items-center gap-2">
                    <Button as-child variant="ghost" size="sm" class="text-muted-foreground">
                        <Link :href="adminLoginUrl">Admin login</Link>
                    </Button>
                    <Button as-child variant="outline" size="sm" class="border-border bg-white">
                        <Link :href="workspaceUrl">
                            Open workspace
                            <ArrowUpRight class="size-3.5" aria-hidden="true" />
                        </Link>
                    </Button>
                </nav>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8 px-4 py-5 sm:gap-10 sm:px-8 sm:py-8 lg:px-12">
            <section aria-labelledby="welcome-title" class="studio-hero relative isolate grid overflow-hidden rounded-2xl border border-[#292c62] text-white md:grid-cols-[1.04fr_1fr]">
                <div class="relative z-10 flex flex-col items-start justify-center px-5 pt-9 pb-2 sm:px-9 sm:pt-12 md:py-12 lg:px-13 lg:py-16">
                    <p class="mb-5 flex items-center gap-2.5 font-mono text-[0.66rem] tracking-[.2em] text-[#ced3ff] uppercase">
                        <span class="h-px w-5 bg-[#b4bcff]" aria-hidden="true"></span>
                        The image studio
                    </p>
                    <h1 id="welcome-title" class="text-[clamp(2.35rem,6.5vw,5.5rem)] leading-[.96] font-medium tracking-[-.065em]">
                        Turn words<br />into images<span class="text-[#b9bdff]">.</span>
                    </h1>
                    <p class="mt-5 max-w-[24rem] text-sm leading-relaxed text-[#dce0fc] sm:text-base">
                        Describe what you imagine, choose a frame, and see it take shape.
                    </p>
                    <div class="mt-7 flex flex-wrap items-center gap-x-5 gap-y-3">
                        <Button as-child size="lg" class="bg-white font-semibold text-[#292d6a] hover:bg-[#e4e6ff]">
                            <Link :href="workspaceUrl">
                                <WandSparkles class="size-4" aria-hidden="true" />
                                Create an image
                                <ArrowRight class="size-3.5" aria-hidden="true" />
                            </Link>
                        </Button>
                        <a href="#workflow" class="inline-flex min-h-10 items-center gap-1.5 rounded text-sm font-medium text-[#e1e3ff] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#d3d6ff]">
                            How it works
                            <ArrowDown class="size-3.5" aria-hidden="true" />
                        </a>
                    </div>
                </div>

                <div aria-hidden="true" class="relative mx-5 mt-6 mb-5 min-h-43 overflow-hidden rounded-xl border border-[#bcc4ff]/25 bg-[#191d4c]/35 sm:mx-9 sm:mb-8 md:my-5 md:mr-5 md:ml-0 lg:my-6 lg:mr-6">
                    <svg class="absolute inset-0 size-full" viewBox="0 0 500 380" preserveAspectRatio="xMidYMid slice" fill="none">
                        <defs>
                            <pattern id="home-studio-grid" width="26" height="26" patternUnits="userSpaceOnUse">
                                <path d="M0 26V0h26" stroke="#c6ceff" stroke-opacity=".13" />
                            </pattern>
                            <radialGradient id="home-studio-glow">
                                <stop stop-color="#a1a7ff" stop-opacity=".65" />
                                <stop offset="1" stop-color="#636bde" stop-opacity="0" />
                            </radialGradient>
                            <linearGradient id="home-studio-orbit" x1="115" y1="95" x2="388" y2="287" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#f7f7ff" />
                                <stop offset=".48" stop-color="#bbc6ff" />
                                <stop offset="1" stop-color="#7d8bfb" stop-opacity=".35" />
                            </linearGradient>
                        </defs>
                        <path d="M0 0h500v380H0z" fill="url(#home-studio-grid)" />
                        <ellipse cx="256" cy="198" rx="242" ry="171" fill="url(#home-studio-glow)" />
                        <g stroke="#bfc7ff" stroke-opacity=".45" stroke-dasharray="3 6">
                            <path d="M250 25v330M43 190h414" />
                            <circle cx="250" cy="190" r="126" />
                        </g>
                        <g stroke="url(#home-studio-orbit)" stroke-width="1.15" transform="rotate(-26 250 190)">
                            <ellipse v-for="(radius, index) in [15, 31, 46, 60, 73, 84, 94, 103, 111, 118, 124]" :key="radius" cx="250" :cy="220 - index * 3.5" rx="163" :ry="radius" :opacity="1 - index * .045" />
                        </g>
                        <g fill="#f0f1ff">
                            <circle cx="114" cy="252" r="2.7" />
                            <circle cx="345" cy="112" r="3.2" />
                            <circle cx="265" cy="274" r="1.7" />
                            <path d="M410 85v16m-8-8h16M78 272v12m-6-6h12" stroke="#e5e8ff" />
                        </g>
                    </svg>
                    <span class="absolute top-3 left-3 rounded border border-[#b8c0ff]/30 bg-[#202452] px-2 py-1 font-mono text-[0.6rem] tracking-[.13em] text-[#e1e4ff] uppercase sm:top-4 sm:left-4">Studio study / 01</span>
                    <span class="absolute right-3 bottom-3 rounded border border-[#b8c0ff]/30 bg-[#202452] px-2 py-1 font-mono text-[0.6rem] tracking-[.13em] text-[#e1e4ff] uppercase sm:right-4 sm:bottom-4">Orbit field</span>
                </div>
            </section>

            <section id="workflow" aria-labelledby="workflow-title" class="grid scroll-mt-4 gap-4">
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                    <h2 id="workflow-title" class="text-xl font-semibold tracking-tight sm:text-2xl">From prompt to image</h2>
                    <p class="font-mono text-[0.65rem] tracking-[.15em] text-muted-foreground uppercase">How it works</p>
                </div>
                <ol class="grid divide-y divide-border overflow-hidden rounded-xl border border-border bg-white md:grid-cols-3 md:divide-x md:divide-y-0">
                    <li v-for="step in workflow" :key="step.number" class="grid grid-cols-[auto_1fr] items-start gap-3 px-4 py-4 sm:px-5 md:px-4 lg:px-5">
                        <span class="grid size-9 place-items-center rounded-lg border border-[#d8d9eb] bg-[#f3f3fa] text-primary">
                            <component :is="step.icon" class="size-4" aria-hidden="true" />
                        </span>
                        <div class="grid gap-1">
                            <h3 class="flex flex-wrap items-baseline gap-x-2 text-sm font-semibold">
                                <span class="font-mono text-[0.65rem] text-primary">{{ step.number }}</span>
                                {{ step.title }}
                            </h3>
                            <p class="text-sm leading-relaxed text-muted-foreground">{{ step.detail }}</p>
                        </div>
                    </li>
                </ol>
            </section>
        </main>

        <footer class="mx-auto w-full max-w-7xl px-4 sm:px-8 lg:px-12">
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border py-4 font-mono text-[0.65rem] tracking-[.14em] text-muted-foreground uppercase">
                <span>Stillroom / Image Studio</span>
                <span>Powered by PixelForge</span>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.studio-hero {
    background: radial-gradient(ellipse at 84% 60%, #454989 0%, transparent 64%), linear-gradient(115deg, #202452 28%, #2d326d 100%);
}
</style>
