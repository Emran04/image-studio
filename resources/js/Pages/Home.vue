<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    appName: { type: String, required: true },
    workspaceUrl: { type: String, required: true },
});

const stack = [
    { name: 'Laravel', role: 'Routes & server data', url: 'https://laravel.com/docs/13.x' },
    { name: 'Inertia', role: 'Page navigation', url: 'https://inertiajs.com/docs/v3' },
    { name: 'Vue', role: 'Interface & components', url: 'https://vuejs.org/guide/introduction.html' },
];
</script>

<template>
    <div class="min-h-screen bg-[#f6f4ea] text-[#192d26] selection:bg-[#d8ed95]">
        <Head :title="`Starter · ${appName}`" />

        <div class="mx-auto flex min-h-screen max-w-6xl flex-col px-6 sm:px-10">
            <header class="flex flex-wrap items-center justify-between gap-4 border-b border-[#192d26]/20 py-6">
                <div class="flex items-center gap-3 font-semibold">
                    <svg aria-hidden="true" class="size-8 shrink-0 rounded-full bg-[#192d26] p-1.5 text-[#deef9f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M12 2v20M2 12h20M5 5l14 14M19 5 5 19" />
                    </svg>
                    {{ appName }}
                </div>
                <Link :href="workspaceUrl" class="rounded-md border border-[#192d26]/20 px-4 py-2 text-sm font-semibold hover:bg-[#e9ebda]">Open workspace <span aria-hidden="true">↗</span></Link>
            </header>

            <main class="grid flex-1 items-center gap-10 py-12 md:grid-cols-2 lg:gap-20 lg:py-20">
                <section aria-labelledby="welcome-title">
                    <p class="mb-5 font-mono text-xs tracking-widest uppercase">01 / Welcome</p>
                    <h1 id="welcome-title" class="font-serif text-[clamp(2.85rem,6.6vw,5.25rem)] leading-[.98] tracking-tight">
                        Your app starts here.
                    </h1>
                    <p class="mt-6 max-w-md text-base leading-relaxed text-[#46584e] sm:text-lg">
                        Laravel handles the routes and data. Inertia passes them to Vue, so you can build interactive pages without a separate API.
                    </p>
                    <p class="mt-7 max-w-full text-sm leading-relaxed">
                        Edit <code class="rounded bg-[#e9ebda] px-2 py-1 text-xs break-all">resources/js/Pages/Home.vue</code> to get started.
                    </p>
                </section>

                <section
                    class="relative isolate overflow-hidden rounded-2xl bg-[#192d26] p-6 text-[#f6f4ea] sm:p-8"
                    aria-labelledby="stack-title"
                >
                    <div aria-hidden="true" class="pointer-events-none absolute -top-24 -right-18 -z-10 size-68 rounded-full border border-[#deef9f]/20 shadow-[0_0_0_24px_#deef9f12,0_0_0_48px_#deef9f0a]"></div>
                    <p class="font-mono text-xs tracking-widest text-[#deef9f] uppercase">02 / Under the hood</p>
                    <h2 id="stack-title" class="mt-3 mb-6 font-serif text-3xl">The stack</h2>
                    <ul class="divide-y divide-white/20 border-y border-white/20">
                        <li v-for="(tool, index) in stack" :key="tool.name">
                            <a
                                :href="tool.url"
                                :aria-label="`${tool.name} documentation`"
                                class="group flex items-center gap-3 py-3.5 outline-[#deef9f] outline-offset-4 hover:text-[#deef9f] focus-visible:outline-2"
                            >
                                <span class="font-mono text-xs text-[#deef9f]">0{{ index + 1 }}</span>
                                <span class="flex flex-1 flex-wrap items-baseline justify-between gap-x-3">
                                    <span class="font-semibold">{{ tool.name }}</span>
                                    <span class="text-sm text-[#cad8c9]">{{ tool.role }}</span>
                                </span>
                                <span aria-hidden="true">↗</span>
                            </a>
                        </li>
                    </ul>
                </section>
            </main>

            <footer class="flex flex-wrap justify-between gap-2 border-t border-[#192d26]/20 py-5 font-mono text-xs tracking-widest uppercase">
                <span>Laravel · Inertia · Vue</span>
                <span>Starter / 01</span>
            </footer>
        </div>
    </div>
</template>
